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
 *     marque de l'hôte, avant que Vik ne lise `$_REQUEST` — c'est-à-dire
 *     avant son pré-traitement sur `init`, pas seulement avant le
 *     shortcode (voir la couche 1b ci-dessous). `$app->input` est lié par
 *     référence à `$_REQUEST` (`JInput::__construct()`), et les vues lisent
 *     par `VikRequest::getString|getInt|getVar(…, 'request')`. Une fois le
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
// Placé sur `init`, priorité 1. Vik ne rend pas sa vue pendant
// `the_content` : sur une page portant son shortcode, sa propre clôture
// `init` de priorité 10 (`vikbooking.php:152`, VIKBOOKING_SITE_PREPROCESS)
// injecte les attributs du shortcode par `def()` puis appelle
// `VikBookingBody::process()`, qui exécute le contrôleur et met le HTML en
// réserve ; le shortcode ne fait ensuite que restituer cette réserve.
// Établi par trace d'exécution le 24 septembre 2026
// (constat-correctif-room-filter.md) : l'ancien accrochage sur
// `template_redirect` retirait bien le paramètre, mais une fois la vue déjà
// rendue. Priorité 1, avant ce `def()` : le paramètre étranger retiré, c'est
// le défaut du shortcode, la chambre de la page, que Vik injecte.

add_action( 'init', 'lme_brands_enforce_shortcode_room_scope', 1 );

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
	lme_brands_strip_foreign_listing_view( $expected_brand );               // availability, roomslist
}

/**
 * Vues de liste demandées par la requête : `?view=availability` ou
 * `?view=roomslist` ajouté à l'URL de n'importe quelle page Vik remplace la
 * vue de la page (le `def()` de Vik cède devant la requête, comme pour
 * `roomid`). Sans sélection, ces deux vues listent toutes les chambres
 * actives, les deux marques confondues (`site/views/availability/view.html.php:37`,
 * `site/views/roomslist/view.html.php:55`), et aucune n'a de crochet de
 * filtrage. Retirer `room_ids` ou `category_id` ne suffit donc pas : c'est
 * précisément ce qui fait lister toutes les chambres.
 *
 * La vue demandée n'est gardée que si la sélection restante, après les
 * retraits ci-dessus, désigne au moins une chambre et uniquement des
 * chambres de la marque de l'hôte. Sinon le paramètre `view` est retiré, et
 * la page retombe sur sa propre vue, celle de son shortcode. Constat du
 * 24 septembre 2026 : aucune page publiée ne porte nativement l'une de ces
 * deux vues (constat-correctif-room-filter.md).
 *
 * @param string $expected_brand
 */
function lme_brands_strip_foreign_listing_view( $expected_brand ) {
	if ( ! isset( $_REQUEST['view'] ) || ! is_string( $_REQUEST['view'] ) ) {
		return;
	}

	// Normalisé comme le filtre `cmd` de Vik (`libraries/adapter/input/filter.php:191`,
	// caractères hors de A-Z, 0-9, `_`, `.`, `-` retirés, points de tête ôtés),
	// puis en minuscules : `view=availability%20` rend bien la vue availability,
	// constaté le 24 septembre 2026. Comparer la valeur brute la laissait passer.
	$view = strtolower( ltrim( (string) preg_replace( '/[^A-Z0-9_.-]/i', '', $_REQUEST['view'] ), '.' ) );

	if ( 'availability' === $view ) {
		$room_ids = isset( $_REQUEST['room_ids'] ) ? lme_brands_parse_id_list( $_REQUEST['room_ids'] ) : array();
	} elseif ( 'roomslist' === $view ) {
		$category_id = isset( $_REQUEST['category_id'] ) && ! is_array( $_REQUEST['category_id'] ) ? (int) $_REQUEST['category_id'] : 0;
		$room_ids    = $category_id > 0
			? lme_brands_room_ids_matching_category( lme_brands_room_category_tokens(), $category_id )
			: array();
	} else {
		return;
	}

	$foreign_room = null;

	foreach ( $room_ids as $room_id ) {
		$resolved = lme_brands_resolve_room_or_log( $room_id );

		if ( 'ok' !== $resolved['status'] || $resolved['brand_key'] !== $expected_brand ) {
			$foreign_room = $room_id;
			break;
		}
	}

	if ( array() !== $room_ids && null === $foreign_room ) {
		return;
	}

	lme_brands_log(
		'warning',
		'foreign_view_param_stripped',
		sprintf(
			"Paramètre 'view=%s' retiré de la requête : %s. La page retombe sur la vue de son shortcode.",
			$view,
			null === $foreign_room
				? 'sans sélection, la vue listerait les chambres de toutes les marques'
				: sprintf( "la sélection contient la chambre #%d, hors de la marque de l'hôte courant (%s)", $foreign_room, $expected_brand )
		),
		array(
			'view'           => $view,
			'room_ids'       => $room_ids,
			'foreign_room'   => $foreign_room,
			'expected_brand' => $expected_brand,
		)
	);

	lme_brands_strip_request_param( 'view' );
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

	if ( did_action( 'vikbooking_before_dispatch' ) ) {
		// Le contrôleur de Vik a déjà rendu sa vue : le retrait arrive trop
		// tard et ne change rien à la page. C'est exactement le défaut que
		// l'accrochage sur `template_redirect` produisait en silence.
		lme_brands_log(
			'error',
			'foreign_room_param_stripped_too_late',
			sprintf( "Paramètre '%s' retiré après le rendu de la vue Vik : la page propose peut-être encore la chambre étrangère.", $name ),
			array( 'param' => $name )
		);
	}
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
