<?php
/**
 * Plugin Name: lme-mail-guard
 * Description: Détourne tout e-mail sortant vers une adresse fourre-tout unique partout sauf en production, pour recetter sans écrire à de vrais clients. docs/briefs/brief-redirection-emails-hors-production.md.
 * Version:     1.0.0
 *
 * WordPress ne charge automatiquement que les fichiers PHP posés à la
 * racine de mu-plugins/, jamais ses sous-dossiers (mu-plugins/README.md).
 * Ce fichier n'est qu'un point d'entrée : il pointe vers le vrai plugin,
 * rangé dans lme-mail-guard/, comme lme-brands.php le fait pour lme-brands/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lme-mail-guard/lme-mail-guard.php';
