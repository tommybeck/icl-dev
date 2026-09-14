<?php
/**
 * lme-brands — filtrage des chambres par marque, couche de présentation.
 * Chapitre 4.3 du brief, phase 2.
 *
 * Deux mécanismes, parce que Vik n'expose qu'un seul vrai point d'accroche
 * de présentation (constat-phase-0.md Q4) :
 *
 *   - vue `search` : le filtre natif `vikbooking_apply_search_results_filtering`
 *     retire une annonce des résultats quand le rappel retourne exactement
 *     `false`. C'est le seul écran couvert par un hook.
 *
 *   - vues `roomdetails`, `availability`, `roomslist` : aucun hook n'existe
 *     (vérifié dans le code, Q4). Leur filtrage tient à un attribut de
 *     shortcode (`roomid`, `room_ids`, `category_id`), et cet attribut est
 *     un défaut au sens de `JInput::def()` : il cède devant un paramètre
 *     GET ou POST de même nom, démontré en production dans
 *     constat-perimetre-tunnel.md §6 —
 *     `linstantcle.ch/fr/a-huis-clos/?roomid=2` rend L'Aparté au lieu de À
 *     Huis Clos. On ne peut donc pas filtrer un résultat qui n'existe pas
 *     encore ; on retire l'anomalie à sa source, en supprimant de la
 *     requête tout paramètre qui viserait une chambre étrangère à la
 *     marque de l'hôte, avant que le shortcode ne lise `$_REQUEST`
 *     (`vikbooking.php:265-273` : `$input = $app->input` est lié par
 *     référence à `$_REQUEST`, `JInput::__construct()`). Une fois le
 *     paramètre retiré, `def()` applique le véritable défaut du
 *     shortcode — celui que la page a été construite pour montrer.
 *
 * Ce que cette couche NE fait PAS, volontairement :
 *
 *   - elle ne corrige pas le défaut *natif* d'une page (un shortcode
 *     `roomid="10"` posé sur une page reste `roomid="10"` sur les quatre
 *     hôtes qui partagent la même installation, constat-perimetre-tunnel.md
 *     §1) : c'est le périmètre du verrou d'hôte (brief-verrou-hote-reservation.md),
 *     un chantier distinct, pas celui-ci ;
 *   - elle n'est donc pas opposable (constat-phase-0.md Q4 : « la couche 1
 *     n'est donc opposable sur aucun écran »). La seule garantie réelle est
 *     includes/booking-guard.php, sur le chemin de création de réservation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Couche 1a : vue `search`, hook natif -----------------------------------

add_filter( 'vikbooking_apply_search_results_filtering', 'lme_brands_filter_search_results', 10, 3 );

/**
 * @param mixed $value           Valeur reçue (null en l'absence d'un autre
 *                                greffon sur ce filtre) ; à retourner telle
 *                                quelle pour garder l'annonce.
 * @param array $room             Ligne jointe dispcost + rooms de l'annonce
 *                                candidate (site/views/search/view.html.php:590) ;
 *                                porte `idroom`.
 * @param array $result_filters
 * @return mixed false pour retirer l'annonce, $value sinon.
 */
function lme_brands_filter_search_results( $value, $room, $result_filters ) {
	$expected_brand = lme_brands_current_request_brand_key();

	if ( null === $expected_brand ) {
		// Hôte qui n'est celui d'aucune marque du registre (staging, accès
		// direct par IP...) : on ne devine rien, comportement natif inchangé.
		return $value;
	}

	if ( ! is_array( $room ) || ! isset( $room['idroom'] ) ) {
		return $value;
	}

	$resolved = lme_brands_resolve_room_or_log( (int) $room['idroom'] );

	if ( 'ok' === $resolved['status'] && $resolved['brand_key'] === $expected_brand ) {
		return $value;
	}

	return false;
}

// --- Couche 1b : vues sans hook natif ---------------------------------------
//
// Placé sur `template_redirect`, priorité 0 : Vik exécute son shortcode
// pendant le rendu du contenu (`the_content`), donc bien après. Le même
// hook est utilisé par le précédent api-host.php pour verrouiller
// api.linstantcle.ch (constat-perimetre-tunnel.md §1), à la même priorité.

add_action( 'template_redirect', 'lme_brands_enforce_shortcode_room_scope', 0 );

function lme_brands_enforce_shortcode_room_scope() {
	if ( is_admin() ) {
		return;
	}

	$expected_brand = lme_brands_current_request_brand_key();

	if ( null === $expected_brand ) {
		// Hôte non enregistré : aucun contexte de marque à faire respecter.
		return;
	}

	lme_brands_strip_foreign_single_room( 'roomid', $expected_brand );      // roomdetails
	lme_brands_strip_foreign_room_list( 'room_ids', $expected_brand );      // availability
	lme_brands_strip_foreign_category( 'category_id', $expected_brand );   // roomslist
}

/**
 * roomdetails : un seul identifiant de chambre.
 *
 * @param string $param
 * @param string $expected_brand
 */
function lme_brands_strip_foreign_single_room( $param, $expected_brand ) {
	if ( ! isset( $_REQUEST[ $param ] ) || is_array( $_REQUEST[ $param ] ) || '' === $_REQUEST[ $param ] ) {
		return;
	}

	$room_id  = (int) $_REQUEST[ $param ];
	$resolved = lme_brands_resolve_room_or_log( $room_id );

	if ( 'ok' === $resolved['status'] && $resolved['brand_key'] === $expected_brand ) {
		return;
	}

	if ( 'ok' === $resolved['status'] ) {
		// Chambre connue, mais d'une autre marque : le seul cas que
		// lme_brands_resolve_room_or_log() ne journalise pas déjà lui-même.
		lme_brands_log(
			'warning',
			'foreign_room_param_stripped',
			sprintf(
				"Paramètre '%s=%d' retiré de la requête (vue roomdetails) : chambre de la marque '%s', étrangère à la marque de l'hôte courant (%s).",
				$param,
				$room_id,
				$resolved['brand_key'],
				$expected_brand
			),
			array(
				'param'          => $param,
				'room_id'        => $room_id,
				'resolved_brand' => $resolved['brand_key'],
				'expected_brand' => $expected_brand,
			)
		);
	}

	lme_brands_strip_request_param( $param );
}

/**
 * availability : liste d'identifiants, `room_ids`, en tableau
 * (`room_ids[]=...`) ou en chaîne délimitée. Un seul identifiant étranger
 * dans la liste fait retirer la liste entière plutôt que la réduire en
 * silence — chapitre 6 du brief, aucun échec silencieux.
 *
 * @param string $param
 * @param string $expected_brand
 */
function lme_brands_strip_foreign_room_list( $param, $expected_brand ) {
	if ( ! isset( $_REQUEST[ $param ] ) ) {
		return;
	}

	$room_ids = lme_brands_parse_id_list( $_REQUEST[ $param ] );

	foreach ( $room_ids as $room_id ) {
		$resolved = lme_brands_resolve_room_or_log( $room_id );

		if ( 'ok' === $resolved['status'] && $resolved['brand_key'] === $expected_brand ) {
			continue;
		}

		if ( 'ok' === $resolved['status'] ) {
			lme_brands_log(
				'warning',
				'foreign_room_param_stripped',
				sprintf(
					"Paramètre '%s' retiré de la requête (vue availability) : contient la chambre #%d, de la marque '%s', étrangère à la marque de l'hôte courant (%s).",
					$param,
					$room_id,
					$resolved['brand_key'],
					$expected_brand
				),
				array(
					'param'          => $param,
					'room_ids'       => $room_ids,
					'foreign_room'   => $room_id,
					'resolved_brand' => $resolved['brand_key'],
					'expected_brand' => $expected_brand,
				)
			);
		}

		lme_brands_strip_request_param( $param );

		return;
	}
}

/**
 * roomslist : `category_id`, une catégorie Vik. Aucune marque n'est
 * attachée à une catégorie dans le registre — la catégorie est une notion
 * propre à Vik (`sir_vikbooking_rooms.idcat`), pas une donnée de marque, et
 * rien n'autorise à en deviner une (règle absolue n°5 de CLAUDE.md). On
 * descend donc au niveau des chambres que Vik range dans cette catégorie,
 * via le registre, et on applique la même règle que pour `roomid` et
 * `room_ids`. Une catégorie qui ne contient aucune chambre du registre (ou
 * aucune donnée Vik disponible) passe : rien à protéger.
 *
 * @param string $param
 * @param string $expected_brand
 */
function lme_brands_strip_foreign_category( $param, $expected_brand ) {
	if ( ! isset( $_REQUEST[ $param ] ) || is_array( $_REQUEST[ $param ] ) || '' === $_REQUEST[ $param ] ) {
		return;
	}

	$category_id       = (int) $_REQUEST[ $param ];
	$room_ids_in_this  = lme_brands_room_ids_matching_category( lme_brands_room_category_tokens(), $category_id );

	foreach ( $room_ids_in_this as $room_id ) {
		$resolved = lme_brands_resolve_room_or_log( $room_id );

		if ( 'ok' === $resolved['status'] && $resolved['brand_key'] === $expected_brand ) {
			continue;
		}

		if ( 'ok' === $resolved['status'] ) {
			lme_brands_log(
				'warning',
				'foreign_room_param_stripped',
				sprintf(
					"Paramètre '%s=%d' retiré de la requête (vue roomslist) : cette catégorie Vik contient la chambre #%d, de la marque '%s', étrangère à la marque de l'hôte courant (%s).",
					$param,
					$category_id,
					$room_id,
					$resolved['brand_key'],
					$expected_brand
				),
				array(
					'param'          => $param,
					'category_id'    => $category_id,
					'foreign_room'   => $room_id,
					'resolved_brand' => $resolved['brand_key'],
					'expected_brand' => $expected_brand,
				)
			);
		}

		lme_brands_strip_request_param( $param );

		return;
	}
}

/**
 * @param string $name
 */
function lme_brands_strip_request_param( $name ) {
	unset( $_GET[ $name ], $_POST[ $name ], $_REQUEST[ $name ] );
}

/**
 * Chambres Vik dont `sir_vikbooking_rooms.idcat` contient chaque jeton de
 * catégorie, sous la forme `room_id => jetons[]`. Même format que le
 * filtrage natif de la vue `search` (constat-phase-0.md Q4 : `idcat` est une
 * chaîne de jetons séparés par `;`, dénormalisée, sans table de liaison).
 *
 * Lecture seule, jamais en cache au-delà de la requête courante : c'est une
 * donnée Vik, pas une donnée du registre lme-brands.
 *
 * @return array
 */
function lme_brands_room_category_tokens() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	global $wpdb;

	$table  = $wpdb->prefix . 'vikbooking_rooms';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

	if ( ! $exists ) {
		$map = array();
		return $map;
	}

	$rows = $wpdb->get_results( "SELECT id, idcat FROM {$table}" ); // Nom de table issu de $wpdb->prefix, aucune entrée utilisateur.
	$map  = array();

	foreach ( $rows as $row ) {
		$map[ (int) $row->id ] = array_values( array_filter( explode( ';', (string) $row->idcat ) ) );
	}

	return $map;
}
