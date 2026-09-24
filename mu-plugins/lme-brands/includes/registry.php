<?php
/**
 * lme-brands — chargement et résolution du registre. Chapitre 4.2 du brief.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Charge le registre depuis config/brands.php, applique le filtre de
 * surcouche, puis le valide. Un registre invalide ne plante jamais le
 * site : il journalise chaque anomalie en erreur et retourne une coquille
 * vide, pour qu'aucune chambre ne résolve jamais vers une marque devinée.
 *
 * @return array
 */
function lme_brands_get_config() {
	static $config = null;

	if ( null !== $config ) {
		return $config;
	}

	$default  = require LME_BRANDS_DIR . '/config/brands.php';
	$filtered = apply_filters( 'lme_brands', $default );
	$errors   = lme_brands_validate_config( $filtered );

	if ( ! empty( $errors ) ) {
		foreach ( $errors as $error ) {
			lme_brands_log( 'error', 'config_invalid', $error );
		}

		$config = array(
			'brands' => array(),
			'rooms'  => array(),
		);

		return $config;
	}

	$config = $filtered;

	return $config;
}

/**
 * Résout la marque d'une chambre et journalise les cas anormaux. C'est le
 * point d'entrée à utiliser partout ailleurs dans le plugin (et dans les
 * phases suivantes) plutôt que lme_brands_resolve_room() directement :
 * il garantit que chapitre 6 est respecté à chaque appel.
 *
 * @param int|string $room_id
 * @return array Voir lme_brands_resolve_room().
 */
function lme_brands_resolve_room_or_log( $room_id ) {
	$config   = lme_brands_get_config();
	$resolved = lme_brands_resolve_room( $config, $room_id );

	if ( 'unknown' === $resolved['status'] ) {
		lme_brands_log(
			'error',
			'unknown_room',
			sprintf( 'Chambre Vik #%d absente du registre lme-brands.', $resolved['room_id'] ),
			array( 'room_id' => $resolved['room_id'] )
		);
	}

	return $resolved;
}

/**
 * @param string $host
 * @return string|null la clé de marque, ou null si l'hôte n'est celui
 *                      d'aucune marque du registre.
 */
function lme_brands_resolve_brand_by_host_cached( $host ) {
	return lme_brands_resolve_brand_by_host( lme_brands_get_config(), $host );
}

/**
 * Hôte HTTP de la requête courante, normalisé (minuscule, sans port), ou
 * null si absent. Point de normalisation partagé par la réécriture d'URL
 * (includes/url-rewrite.php), le filtrage de présentation et la garde de
 * réservation (phase 2) : trois lecteurs, une seule façon de décider QUELLE
 * marque gouverne la requête (éventuellement forcée par le levier).
 *
 * Correctif du 22 septembre 2026 : cette fonction ne sert plus de cible à
 * la réécriture d'URL, seulement de déclencheur. Voir
 * lme_brands_current_raw_http_host() ci-dessous et
 * lme_brands_resolve_url_rewrite_target() (includes/core.php).
 *
 * Chantier B8 : sur `staging10.linstantcle.ch`, cet hôte ne correspond à
 * aucune marque du registre, et c'est voulu (chapitre 4.2 du brief : « on ne
 * devine jamais une marque »). Ça laisse la préproduction dans l'incapacité
 * d'exercer le chemin Sexcape Room. Le levier `LME_BRANDS_HOST_OVERRIDE`,
 * défini dans le `wp-config.php` de la préproduction et absent partout
 * ailleurs, force l'hôte vu par toute la résolution de marque — sans changer
 * un octet du registre — mais seulement quand `wp_get_environment_type()`
 * vaut exactement `'staging'`. La décision elle-même est prise par la
 * fonction pure lme_brands_resolve_effective_http_host() (includes/core.php),
 * testable sans WordPress : c'est elle qui garantit que le levier reste
 * inerte en production, quelle que soit la constante qui y traînerait par
 * erreur.
 *
 * Résolu une seule fois par requête, comme
 * lme_brands_current_request_brand_host() dans includes/url-rewrite.php :
 * l'hôte HTTP ne change pas en cours de requête, et ça évite qu'un emploi du
 * levier journalise une ligne à chaque appel plutôt qu'une par requête.
 *
 * @return string|null
 */
function lme_brands_current_http_host() {
	static $resolved = false;
	static $host     = null;

	if ( $resolved ) {
		return $host;
	}
	$resolved = true;

	$request_host = lme_brands_normalize_http_host(
		isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : null
	);

	$override = defined( 'LME_BRANDS_HOST_OVERRIDE' ) ? LME_BRANDS_HOST_OVERRIDE : null;
	$outcome  = lme_brands_resolve_effective_http_host( $request_host, wp_get_environment_type(), $override );

	if ( $outcome['override_used'] ) {
		lme_brands_log(
			'warning',
			'host_override_used',
			sprintf(
				"Levier de préproduction actif : hôte de résolution de marque forcé à '%s' par LME_BRANDS_HOST_OVERRIDE (hôte réel de la requête : %s).",
				$outcome['host'],
				null === $request_host ? '(absent)' : $request_host
			),
			array(
				'override_host' => $outcome['host'],
				'request_host'  => $request_host,
			)
		);
	}

	$host = $outcome['host'];

	return $host;
}

/**
 * Hôte HTTP réel de la requête courante, normalisé comme
 * lme_brands_current_http_host() mais **jamais** soumis au levier de
 * préproduction B8 : c'est l'hôte qui sert effectivement la requête.
 *
 * Ajouté le 22 septembre 2026 (docs/briefs/brief-correctif-levier-et-fatal-paiement.md
 * chapitre 2) pour includes/url-rewrite.php, seul appelant : la cible d'une
 * réécriture d'URL doit toujours être cet hôte réel, jamais l'hôte
 * potentiellement forcé de lme_brands_current_http_host() ni un `host`
 * lu dans le registre — voir lme_brands_resolve_url_rewrite_target()
 * (includes/core.php) pour la décision elle-même, et le constat pour la
 * preuve que confondre les deux hôtes a fait charger les feuilles de style
 * de la préproduction depuis la production.
 *
 * Résolu une seule fois par requête, comme les autres lecteurs d'hôte de ce
 * fichier.
 *
 * @return string|null
 */
function lme_brands_current_raw_http_host() {
	static $resolved = false;
	static $host     = null;

	if ( $resolved ) {
		return $host;
	}
	$resolved = true;

	$host = lme_brands_normalize_http_host(
		isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : null
	);

	return $host;
}

/**
 * Marque résolue depuis l'hôte HTTP courant, sans restriction de contexte.
 *
 * Contrairement à lme_brands_current_request_brand_host() dans
 * url-rewrite.php, qui exclut volontairement l'administration, l'API REST
 * et le cron (chapitre 4.1 du brief : réécrire option_siteurl à ces
 * endroits casserait l'administration), cette fonction ne fait aucune
 * exclusion de contexte. Le filtrage de présentation et la garde de
 * réservation n'en ont pas besoin : ils ne s'exécutent de toute façon que
 * sur des requêtes front-end réelles (`init` hors administration, ou le hook de
 * création de réservation, qui ne se déclenche jamais en administration).
 *
 * Un hôte qui n'est celui d'aucune marque du registre (staging, accès
 * direct par IP) retourne null : on ne devine jamais une marque, ni ici ni
 * ailleurs.
 *
 * @return string|null
 */
function lme_brands_current_request_brand_key() {
	$host = lme_brands_current_http_host();

	if ( null === $host ) {
		return null;
	}

	return lme_brands_resolve_brand_by_host( lme_brands_get_config(), $host );
}

/**
 * Identifiants des chambres d'une réservation, relus dans
 * `sir_vikbooking_ordersrooms` par `idorder`. Partagé par includes/mail-brand.php
 * (phase 3) et includes/payment-brand.php (phase 4) : les deux résolvent la
 * marque d'une réservation existante de la même façon, depuis ses chambres,
 * jamais depuis l'hôte de la requête (chapitre 4.4 et 4.5 du brief — un envoi
 * ou un paiement peut être déclenché hors du contexte de l'hôte qui a créé la
 * réservation).
 *
 * Lecture seule, en cache pour la durée de la requête : `sendBookingEmail()`
 * boucle sur ses destinataires (`['guest', 'admin']` dans la plupart des
 * appels) et déclenche son hook une fois par destinataire, ce qui ferait
 * autrement deux requêtes identiques pour un seul envoi.
 *
 * @param int $idorder
 * @return int[]
 */
function lme_brands_booking_room_ids( $idorder ) {
	static $cache = array();

	$idorder = (int) $idorder;

	if ( isset( $cache[ $idorder ] ) ) {
		return $cache[ $idorder ];
	}

	global $wpdb;

	$table  = $wpdb->prefix . 'vikbooking_ordersrooms';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

	if ( ! $exists ) {
		lme_brands_log(
			'error',
			'vik_ordersrooms_missing',
			sprintf( "La table %s est introuvable : impossible de résoudre la marque de la réservation #%d.", $table, $idorder ),
			array( 'table' => $table, 'booking_id' => $idorder )
		);

		$cache[ $idorder ] = array();

		return $cache[ $idorder ];
	}

	// Nom de table issu de $wpdb->prefix, aucune entrée utilisateur ;
	// $idorder passe par prepare().
	$rows = $wpdb->get_col( $wpdb->prepare( "SELECT idroom FROM {$table} WHERE idorder = %d ORDER BY id ASC", $idorder ) );

	$ids = array();
	foreach ( (array) $rows as $row ) {
		$ids[] = (int) $row;
	}

	$cache[ $idorder ] = array_values( array_unique( $ids ) );

	return $cache[ $idorder ];
}
