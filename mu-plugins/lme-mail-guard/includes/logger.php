<?php
/**
 * lme-mail-guard — journalisation.
 *
 * Même forme de ligne que lme-brands/includes/logger.php, préfixe propre
 * à ce greffon, pour qu'une lecture de debug.log distingue immédiatement
 * lequel des deux a parlé. Pas de limitation de débit ni d'action
 * d'alerte ici : ce greffon ne journalise que deux situations précises
 * (chapitre 2.b du brief, et l'abandon d'envoi de la règle absolue n°6),
 * jamais à chaque e-mail détourné — la volumétrie qui justifierait une
 * limitation de débit n'existe pas ici.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LME_MAIL_GUARD_LOG_PREFIX', '[lme-mail-guard]' );

/**
 * @param string $level   'warning' ou 'error'. Toute autre valeur est
 *                        traitée comme 'error' : aucun échec silencieux,
 *                        y compris ici.
 * @param string $code    identifiant court et stable de la situation.
 * @param string $message texte lisible, en français.
 * @param array  $context données structurées utiles au diagnostic.
 */
function lme_mail_guard_log( $level, $code, $message, array $context = array() ) {
	$valid_levels = array( 'warning', 'error' );

	if ( ! in_array( $level, $valid_levels, true ) ) {
		$context['niveau_recu'] = $level;
		$message                = "Niveau de journalisation invalide, traité comme erreur. {$message}";
		$level                  = 'error';
	}

	$line = sprintf(
		'%s [%s] [%s] %s %s',
		LME_MAIL_GUARD_LOG_PREFIX,
		strtoupper( $level ),
		$code,
		$message,
		empty( $context ) ? '' : wp_json_encode( $context )
	);

	error_log( $line );
}
