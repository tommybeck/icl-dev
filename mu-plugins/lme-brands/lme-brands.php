<?php
/**
 * lme-brands — bootstrap.
 *
 * Ordre de chargement volontaire : core (pur, sans WordPress) avant logger
 * (qui en dépend), logger avant registry (qui journalise), registry avant
 * url-rewrite, room-filter, booking-guard, mail-brand et health-screen (qui
 * consomment tous le registre et sa résolution de marque).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LME_BRANDS_DIR', __DIR__ );

if ( ! defined( 'LME_BRANDS_ALERT_WINDOW' ) ) {
	// Fenêtre de limitation de débit des alertes : chapitre 6 du brief.
	define( 'LME_BRANDS_ALERT_WINDOW', 15 * MINUTE_IN_SECONDS );
}

require_once LME_BRANDS_DIR . '/includes/core.php';
require_once LME_BRANDS_DIR . '/includes/logger.php';
require_once LME_BRANDS_DIR . '/includes/registry.php';
require_once LME_BRANDS_DIR . '/includes/url-rewrite.php';
require_once LME_BRANDS_DIR . '/includes/room-filter.php';
require_once LME_BRANDS_DIR . '/includes/booking-guard.php';
require_once LME_BRANDS_DIR . '/includes/mail-brand.php';
require_once LME_BRANDS_DIR . '/includes/health-screen.php';
