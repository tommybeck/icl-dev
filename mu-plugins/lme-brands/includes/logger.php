<?php
/**
 * lme-brands — journalisation. Chapitre 6 du brief.
 *
 * Une seule fonction de journalisation, préfixe unique, deux niveaux
 * (avertissement, erreur), plus alerte au-delà de l'avertissement, avec
 * limitation de débit pour éviter la pluie de messages.
 *
 * Le transport de l'alerte n'est pas ici : cette fonction se contente de
 * déclencher l'action `lme_brands_alert`. Câbler cette action vers Telegram
 * (ou autre chose) est un geste fait sur le serveur, avec un jeton que
 * Thomas seul détient — jamais dans ce dépôt (règle absolue n°2 de CLAUDE.md).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LME_BRANDS_LOG_PREFIX', '[lme-brands]' );

/**
 * @param string $level   'warning' ou 'error'. Toute autre valeur est
 *                        traitée comme 'error' : aucun échec silencieux,
 *                        y compris ici.
 * @param string $code    identifiant court et stable de la situation,
 *                        ex. 'unknown_room'.
 * @param string $message texte lisible, en français.
 * @param array  $context données structurées utiles au diagnostic.
 */
function lme_brands_log( $level, $code, $message, array $context = array() ) {
	$valid_levels = array( 'warning', 'error' );

	if ( ! in_array( $level, $valid_levels, true ) ) {
		$context['niveau_recu'] = $level;
		$message                = "Niveau de journalisation invalide, traité comme erreur. {$message}";
		$level                  = 'error';
	}

	$line = sprintf(
		'%s [%s] [%s] %s %s',
		LME_BRANDS_LOG_PREFIX,
		strtoupper( $level ),
		$code,
		$message,
		empty( $context ) ? '' : wp_json_encode( $context )
	);

	error_log( $line );

	if ( 'error' !== $level ) {
		return;
	}

	$state = get_option( 'lme_brands_alert_state', array() );
	if ( ! is_array( $state ) ) {
		$state = array();
	}

	$gate = lme_brands_rate_limit_gate( $state, $code, time(), LME_BRANDS_ALERT_WINDOW );

	update_option( 'lme_brands_alert_state', $gate['state'], false );

	if ( $gate['should_alert'] ) {
		/**
		 * @param string $level   toujours 'error' ici.
		 * @param string $code
		 * @param string $message
		 * @param array  $context
		 */
		do_action( 'lme_brands_alert', $level, $code, $message, $context );
	}
}
