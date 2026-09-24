<?php
/**
 * lme-mail-guard — accroche WordPress.
 *
 * Trois crochets natifs du cœur, tous lus dans wp-includes/pluggable.php
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
 *   - `phpmailer_init` (do_action_ref_array( 'phpmailer_init', … ), juste
 *     avant $phpmailer->send(), relu le 24 septembre 2026, pluggable.php:622
 *     et :628) : hors production, vide To, Cc et Bcc et ne remet que
 *     l'adresse fourre-tout, pour qu'aucun greffon ne puisse ajouter un
 *     destinataire après le filtre `wp_mail`. Placé en dernier, voir
 *     lme_mail_guard_hook_phpmailer_last().
 *
 * Priorité PHP_INT_MAX sur les trois : ce greffon doit avoir le dernier mot
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

/**
 * Adresses To, Cc et Bcc portées par l'objet PHPMailer. Les trois accesseurs
 * rendent des paires [adresse, nom] (PHPMailer 6, embarqué par le cœur).
 *
 * @param object $phpmailer
 * @return string[]
 */
function lme_mail_guard_phpmailer_addresses( $phpmailer ) {
	$addresses = array();

	foreach ( array( 'getToAddresses', 'getCcAddresses', 'getBccAddresses' ) as $getter ) {
		foreach ( (array) $phpmailer->$getter() as $pair ) {
			if ( is_array( $pair ) && isset( $pair[0] ) ) {
				$addresses[] = (string) $pair[0];
			}
		}
	}

	return $addresses;
}

/**
 * Dernier mot sur les destinataires, hors production : vide To, Cc et Bcc de
 * PHPMailer et ne remet que l'adresse fourre-tout, pour qu'aucun greffon ne
 * puisse ajouter un destinataire après le filtre `wp_mail` ci-dessus.
 * `phpmailer_init` est le dernier crochet de wp_mail() avant
 * `$phpmailer->send()` (wp-includes/pluggable.php) ; lme_mail_guard_hook_phpmailer_last()
 * y place ce rappel en dernier.
 *
 * Ne touche ni From, ni Sender, ni Reply-To : clearAllRecipients() ne vide
 * que To, Cc et Bcc, jamais les adresses de réponse (lme-mail-guard.php).
 *
 * Ce que ce crochet ne couvre pas : un greffon qui envoie sans passer par
 * wp_mail(), avec sa propre instance de PHPMailer ou une API (MailPoet par
 * son service), ou qui modifie les destinataires après phpmailer_init.
 * docs/briefs/constat-integrations-sortantes.md §4.
 *
 * @param object $phpmailer Instance passée par référence par wp_mail().
 */
function lme_mail_guard_enforce_phpmailer_recipients( $phpmailer ) {
	if ( ! lme_mail_guard_current_should_redirect() ) {
		return;
	}

	$required = array( 'getToAddresses', 'getCcAddresses', 'getBccAddresses', 'clearAllRecipients', 'addAddress' );

	foreach ( $required as $method ) {
		if ( ! is_object( $phpmailer ) || ! method_exists( $phpmailer, $method ) ) {
			lme_mail_guard_log(
				'error',
				'phpmailer_unexpected',
				sprintf(
					"Hors production, l'objet reçu par phpmailer_init n'a pas la méthode %s() : destinataires non vérifiés au dernier moment, seul le filtre wp_mail les a réécrits.",
					$method
				),
				array(
					'classe' => is_object( $phpmailer ) ? get_class( $phpmailer ) : gettype( $phpmailer ),
					'host'   => lme_mail_guard_current_host(),
				)
			);
			return;
		}
	}

	$plan = lme_mail_guard_plan_final_recipients(
		lme_mail_guard_phpmailer_addresses( $phpmailer ),
		lme_mail_guard_resolved_catchall()
	);

	$phpmailer->clearAllRecipients();

	foreach ( $plan['keep'] as $address ) {
		// wp_mail() crée PHPMailer avec les exceptions actives, et
		// phpmailer_init est appelé hors de son try (pluggable.php:622) : une
		// exception ici serait fatale. L'adresse a déjà été acceptée par
		// wp_mail() plus haut, ce repli ne devrait jamais servir.
		try {
			$phpmailer->addAddress( $address );
		} catch ( \Exception $e ) {
			lme_mail_guard_log(
				'error',
				'catchall_rejected',
				"Hors production, PHPMailer refuse l'adresse fourre-tout à phpmailer_init : aucun destinataire, PHPMailer refusera l'envoi.",
				array(
					'exception' => $e->getMessage(),
					'host'      => lme_mail_guard_current_host(),
				)
			);
		}
	}

	if ( ! empty( $plan['dropped'] ) ) {
		lme_mail_guard_log(
			'warning',
			'recipients_added_after_filter',
			sprintf(
				"Hors production, %d destinataire(s) autre(s) que l'adresse fourre-tout présent(s) sur PHPMailer à phpmailer_init, ajouté(s) après le filtre wp_mail : retiré(s) avant l'envoi.",
				count( $plan['dropped'] )
			),
			array(
				'domaines' => lme_mail_guard_address_domains( $plan['dropped'] ),
				'host'     => lme_mail_guard_current_host(),
			)
		);
	}

	if ( empty( $plan['keep'] ) ) {
		lme_mail_guard_log(
			'error',
			'catchall_not_configured',
			"Hors production, phpmailer_init atteint sans adresse fourre-tout configurée (pre_wp_mail court-circuité ailleurs ?) : tous les destinataires sont retirés, PHPMailer refusera l'envoi.",
			array(
				'host' => lme_mail_guard_current_host(),
			)
		);
	}
}

/**
 * Place lme_mail_guard_enforce_phpmailer_recipients() en dernier sur
 * `phpmailer_init`. À priorité égale, WordPress exécute les rappels dans
 * l'ordre d'enregistrement, et un mu-plugin s'enregistre avant tout greffon :
 * PHP_INT_MAX seul laisserait passer après nous un greffon posé à la même
 * priorité. Enregistré dès le chargement, pour les envois antérieurs à
 * `wp_loaded`, puis retiré et remis sur `wp_loaded`, une fois tous les
 * greffons et le thème chargés, pour repasser en queue.
 */
function lme_mail_guard_hook_phpmailer_last() {
	remove_action( 'phpmailer_init', 'lme_mail_guard_enforce_phpmailer_recipients', PHP_INT_MAX );
	add_action( 'phpmailer_init', 'lme_mail_guard_enforce_phpmailer_recipients', PHP_INT_MAX );
}
lme_mail_guard_hook_phpmailer_last();
add_action( 'wp_loaded', 'lme_mail_guard_hook_phpmailer_last', PHP_INT_MAX );
