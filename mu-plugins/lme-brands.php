<?php
/**
 * Plugin Name: lme-brands
 * Description: Registre des marques, résolution de marque depuis la chambre Vik, réécriture d'URL consciente de l'hôte, écran de santé, journalisation. Phase 1 du chantier réservation Sexcape Room (docs/briefs/sexcape-room-reservation.md).
 * Version:     1.0.0
 *
 * WordPress ne charge automatiquement que les fichiers PHP posés à la
 * racine de mu-plugins/, jamais ses sous-dossiers (voir mu-plugins/README.md).
 * Ce fichier n'est qu'un point d'entrée : il pointe vers le vrai plugin,
 * rangé dans lme-brands/, pour garder mu-plugins/ lisible à mesure que
 * d'autres mu-plugins s'y ajoutent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lme-brands/lme-brands.php';
