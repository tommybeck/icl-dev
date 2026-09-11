<?php
/**
 * lme-brands — réécriture d'URL consciente de l'hôte. Chapitre 4.1 du brief.
 *
 * Quand la requête arrive sur l'hôte d'une marque du registre, home_url(),
 * site_url(), content_url(), upload_dir() et wp_get_attachment_url()
 * produisent des URL sur cet hôte. Jamais en administration, jamais sur
 * l'API REST, jamais en cron : réécrire option_siteurl à ces endroits
 * casserait l'administration elle-même (garde-fou explicite du brief).
 *
 * Les filtres sont posés ici, au chargement du mu-plugin, donc avant tout
 * code de thème ou de plugin tiers. C'est ce qui garantit qu'ils sont en
 * place avant que Vik Booking mémorise JUri::base() pour la durée de la
 * requête (constat-phase-0.md, Q5).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return bool false en administration, en API REST ou en cron : partout
 *              ailleurs, la réécriture peut s'appliquer si l'hôte correspond
 *              à une marque.
 */
function lme_brands_should_rewrite_host() {
	if ( is_admin() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return false;
	}

	return true;
}

/**
 * Hôte de marque à utiliser pour la requête courante, ou null si aucune
 * réécriture ne s'applique (contexte exclu, ou hôte qui ne correspond à
 * aucune marque du registre). Résolu une seule fois par requête.
 *
 * @return string|null
 */
function lme_brands_current_request_brand_host() {
	static $resolved = false;
	static $host     = null;

	if ( $resolved ) {
		return $host;
	}
	$resolved = true;

	if ( ! lme_brands_should_rewrite_host() ) {
		return null;
	}

	if ( empty( $_SERVER['HTTP_HOST'] ) ) {
		return null;
	}

	$request_host = strtolower( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) );
	$request_host = preg_replace( '/:\d+$/', '', $request_host );

	$config    = lme_brands_get_config();
	$brand_key = lme_brands_resolve_brand_by_host( $config, $request_host );

	if ( null === $brand_key ) {
		// Hôte qui n'est celui d'aucune marque enregistrée (ex. staging,
		// accès direct par IP) : on ne devine rien, WordPress garde son
		// comportement par défaut.
		return null;
	}

	$host = $config['brands'][ $brand_key ]['host'];

	return $host;
}

function lme_brands_filter_option_home( $value ) {
	$host = lme_brands_current_request_brand_host();
	return null === $host ? $value : lme_brands_swap_url_host( $value, $host );
}
add_filter( 'option_home', 'lme_brands_filter_option_home' );

function lme_brands_filter_option_siteurl( $value ) {
	$host = lme_brands_current_request_brand_host();
	return null === $host ? $value : lme_brands_swap_url_host( $value, $host );
}
add_filter( 'option_siteurl', 'lme_brands_filter_option_siteurl' );

function lme_brands_filter_content_url( $url ) {
	$host = lme_brands_current_request_brand_host();
	return null === $host ? $url : lme_brands_swap_url_host( $url, $host );
}
add_filter( 'content_url', 'lme_brands_filter_content_url' );

function lme_brands_filter_upload_dir( $uploads ) {
	$host = lme_brands_current_request_brand_host();

	if ( null === $host || ! is_array( $uploads ) ) {
		return $uploads;
	}

	if ( ! empty( $uploads['url'] ) ) {
		$uploads['url'] = lme_brands_swap_url_host( $uploads['url'], $host );
	}

	if ( ! empty( $uploads['baseurl'] ) ) {
		$uploads['baseurl'] = lme_brands_swap_url_host( $uploads['baseurl'], $host );
	}

	return $uploads;
}
add_filter( 'upload_dir', 'lme_brands_filter_upload_dir' );

function lme_brands_filter_wp_get_attachment_url( $url ) {
	$host = lme_brands_current_request_brand_host();
	return null === $host ? $url : lme_brands_swap_url_host( $url, $host );
}
add_filter( 'wp_get_attachment_url', 'lme_brands_filter_wp_get_attachment_url' );
