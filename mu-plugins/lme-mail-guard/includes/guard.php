<?php
/**
 * lme-mail-guard — accroche WordPress.
 *
 * Deux crochets natifs du cœur, tous deux lus dans wp-includes/pluggable.php
 * (wp_mail(), lu sur le serveur linstantcle.ch le 22 septembre 2026, PHP
 * 8.2.33 / WordPress 6.9) plutôt que supposés :
 *
 *   - `wp_mail` (apply_filters( 'wp_mail', compact(...) ), depuis 2.2.0) :
 *     un seul argument, le tableau $atts au complet (to, subject, message,
 *     headers, attachments, embeds). Sert ici à réécrire `to` et `headers`
 *     quand un e-mail part vers l'adresse fourre-tout.
 *   - `pre_wp_mail` (apply_filters( 'pre_wp_mail', null, $atts ), depuis
 *     5.7.0), appelé juste après `wp_mail` avec le $atts déjà filtré : rendre
 *     autre chose que null court-circuite wp_mail(), qui retourne alors
 *     cette valeur sans jamais construire ni envoyer de message. C'est le
 *     seul moyen natif d'« abandonner l'envoi » du brief (l'adresse
 *     fourre-tout absente hors production) sans faire partir un message
 *     avec zéro destinataire vers PHPMailer.
 *
 * Priorité PHP_INT_MAX sur les deux : ce greffon doit avoir le dernier mot
 * sur `to` et `headers`, après tout autre code qui aurait pu y toucher
 * (Vik, MailPoet, WPForms, ou un futur greffon) — jamais l'inverse.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hôte HTTP de la requête courante, normalisé, résolu une seule fois par
 * requête — même raison que lme_brands_current_http_host() :  l'hôte ne
 * change pas en cours de requête.
 *
 * @return string|null
 */
function lme_mail_guard_current_host() {
	static $resolved = false;
	static $host     = null;

	if ( $resolved ) {
		return $host;
	}
	$resolved = true;

	$host = lme_mail_guard_normalize_host(
		isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : null
	);

	return $host;
}

/**
 * @return bool Valeur de FORCE_EMAIL_REDIRECT si elle est définie et vaut
 *              exactement true, false sinon (non définie, ou définie à
 *              autre chose que true).
 */
function lme_mail_guard_force_redirect_flag() {
	return defined( 'FORCE_EMAIL_REDIRECT' ) && true === FORCE_EMAIL_REDIRECT;
}

/**
 * @return string|null L'adresse fourre-tout, ou null si elle n'est pas
 *                      configurée — repli inerte, chapitre « adresse
 *                      fourre-tout » du brief.
 */
function lme_mail_guard_resolved_catchall() {
	return lme_mail_guard_resolve_catchall(
		defined( 'LME_MAIL_GUARD_CATCHALL_EMAIL' ),
		defined( 'LME_MAIL_GUARD_CATCHALL_EMAIL' ) ? LME_MAIL_GUARD_CATCHALL_EMAIL : null
	);
}

/**
 * @return bool Décision de détournement pour la requête courante,
 *              chapitres 2.a et 2.a (échappatoire) du brief.
 */
function lme_mail_guard_current_should_redirect() {
	return lme_mail_guard_should_redirect(
		wp_get_environment_type(),
		lme_mail_guard_current_host(),
		lme_mail_guard_default_production_hosts(),
		lme_mail_guard_force_redirect_flag()
	);
}

/**
 * Chapitre 2.b du brief : si un message est détourné (ou abandonné) alors
 * que l'hôte de la requête résout une marque du registre lme-brands, un
 * client attendait ce message. Couplage volontairement faible : une
 * simple vérification function_exists(), jamais un require — ce greffon
 * fonctionne seul, sans lme-brands, et se contente de se taire sur ce
 * point précis s'il est absent.
 *
 * @param mixed $to Valeur brute de $args['to'] ou $atts['to'], avant tout détournement.
 */
function lme_mail_guard_maybe_warn_brand_host( $to ) {
	if ( ! function_exists( 'lme_brands_current_request_brand_key' ) ) {
		return;
	}

	$brand_key = lme_brands_current_request_brand_key();

	if ( null === $brand_key ) {
		return;
	}

	lme_mail_guard_log(
		'warning',
		'production_brand_host_redirected',
		sprintf(
			"Un message destiné à '%s' a été détourné (ou abandonné) hors production alors que l'hôte de la requête résout la marque '%s' du registre lme-brands : un client attendait ce message.",
			lme_mail_guard_format_original_to( $to ),
			$brand_key
		),
		array(
			'brand_key' => $brand_key,
			'host'      => lme_mail_guard_current_host(),
		)
	);
}

/**
 * Chemin du journal de transcription d'enveloppe, chapitre 2 du brief
 * docs/briefs/brief-recette-automatisee.md. Un fichier dédié, distinct de
 * debug.log : ce qu'il porte (l'adresse réelle du destinataire d'origine)
 * est une donnée personnelle qui doit pouvoir être identifiée et purgée
 * d'un seul geste avec la préproduction, jamais mélangée aux lignes
 * d'avertissement ordinaires de lme_mail_guard_log().
 *
 * @return string
 */
function lme_mail_guard_envelope_log_path() {
	return WP_CONTENT_DIR . '/lme-mail-guard-envelopes.log';
}

/**
 * Écrit une ligne de transcription d'enveloppe si, et seulement si,
 * `$should_redirect` vaut true — la même valeur, calculée une seule fois par
 * lme_mail_guard_filter_wp_mail() et partagée avec la décision de
 * détournement, jamais un second test qui pourrait diverger d'elle
 * (chapitre 2 du brief). Une transcription ne s'active donc jamais quand le
 * contexte est reconnu comme production, exactement comme le détournement.
 *
 * Ne lit que `to`, `headers` et `subject` de $args : le corps et les pièces
 * jointes ne sont jamais transmis à lme_mail_guard_format_envelope_line(),
 * qui ne les accepte de toute façon pas en paramètre.
 *
 * @param array $args            Voir la docblock de wp_mail(), valeurs
 *                                d'origine, avant toute réécriture.
 * @param bool  $should_redirect Décision déjà prise par l'appelant.
 */
function lme_mail_guard_maybe_transcribe_envelope( array $args, $should_redirect ) {
	if ( ! $should_redirect ) {
		return;
	}

	$line = lme_mail_guard_format_envelope_line(
		gmdate( 'c' ),
		$args['to'],
		lme_mail_guard_normalize_headers( isset( $args['headers'] ) ? $args['headers'] : '' ),
		isset( $args['subject'] ) ? $args['subject'] : '',
		lme_mail_guard_current_host()
	);

	$written = @error_log( $line . "\n", 3, lme_mail_guard_envelope_log_path() );

	if ( ! $written ) {
		lme_mail_guard_log(
			'error',
			'envelope_transcript_write_failed',
			sprintf(
				"Écriture de la transcription d'enveloppe impossible dans %s : les vérifications 5 et 7 de la recette resteraient aveugles tant que ce n'est pas corrigé.",
				lme_mail_guard_envelope_log_path()
			)
		);
	}
}

/**
 * Réécrit `to` et `headers` quand l'e-mail doit être détourné et qu'une
 * adresse fourre-tout est configurée. Ne touche à rien d'autre : ni
 * `subject`, ni `message`, ni `attachments`, ni `embeds`, ni — chapitre
 * dédié de includes/core.php — `From`, `Sender` ou `Reply-To`.
 *
 * @param array $args Voir la docblock de wp_mail() : to, subject, message,
 *                     headers, attachments, embeds.
 * @return array
 */
function lme_mail_guard_filter_wp_mail( $args ) {
	$should_redirect = lme_mail_guard_current_should_redirect();

	lme_mail_guard_maybe_transcribe_envelope( $args, $should_redirect );

	if ( ! $should_redirect ) {
		return $args;
	}

	$catchall = lme_mail_guard_resolved_catchall();

	if ( null === $catchall ) {
		// Pas d'adresse fourre-tout configurée : rien à réécrire ici, le
		// crochet pre_wp_mail (ci-dessous) abandonnera l'envoi.
		return $args;
	}

	lme_mail_guard_maybe_warn_brand_host( $args['to'] );

	$args['headers'] = lme_mail_guard_build_redirected_headers( $args['headers'], $args['to'] );
	$args['to']      = $catchall;

	return $args;
}
add_filter( 'wp_mail', 'lme_mail_guard_filter_wp_mail', PHP_INT_MAX );

/**
 * Abandonne l'envoi (repli inerte) quand l'e-mail doit être détourné mais
 * qu'aucune adresse fourre-tout n'est configurée — jamais un envoi vers
 * une adresse par défaut. Règle absolue n°6 de CLAUDE.md : cet abandon est
 * une erreur journalisée, pas un échec silencieux.
 *
 * Respecte tout court-circuit déjà posé par un autre greffon
 * ($pre !== null) : ne jamais écraser une décision prise ailleurs.
 *
 * @param mixed $pre  null par défaut ; toute autre valeur court-circuite déjà wp_mail().
 * @param array $atts Voir la docblock de wp_mail().
 * @return mixed
 */
function lme_mail_guard_maybe_abort_send( $pre, $atts ) {
	if ( null !== $pre ) {
		return $pre;
	}

	if ( ! lme_mail_guard_current_should_redirect() ) {
		return null;
	}

	if ( null !== lme_mail_guard_resolved_catchall() ) {
		return null;
	}

	lme_mail_guard_maybe_warn_brand_host( $atts['to'] );

	lme_mail_guard_log(
		'error',
		'catchall_not_configured',
		"Hors production, aucune adresse fourre-tout n'est configurée (constante LME_MAIL_GUARD_CATCHALL_EMAIL absente ou vide) : l'envoi est abandonné plutôt que livré à un client ou à une adresse par défaut.",
		array(
			'host' => lme_mail_guard_current_host(),
		)
	);

	return false;
}
add_filter( 'pre_wp_mail', 'lme_mail_guard_maybe_abort_send', PHP_INT_MAX, 2 );
