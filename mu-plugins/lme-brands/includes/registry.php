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
			'brands'            => array(),
			'rooms'             => array(),
			'excluded_room_ids' => array(),
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
	} elseif ( 'excluded' === $resolved['status'] ) {
		lme_brands_log(
			'warning',
			'excluded_room',
			sprintf( 'Chambre Vik #%d volontairement exclue du registre (chambre de test, jamais vendue).', $resolved['room_id'] ),
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
