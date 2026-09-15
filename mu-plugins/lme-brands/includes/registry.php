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
 * réservation (phase 2) : trois lecteurs, une seule façon de lire l'hôte.
 *
 * @return string|null
 */
function lme_brands_current_http_host() {
	if ( empty( $_SERVER['HTTP_HOST'] ) ) {
		return null;
	}

	$host = strtolower( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) );

	return preg_replace( '/:\d+$/', '', $host );
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
 * sur des requêtes front-end réelles (template_redirect, ou le hook de
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
