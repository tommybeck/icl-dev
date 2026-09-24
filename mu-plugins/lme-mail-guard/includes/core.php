<?php
/**
 * lme-mail-guard — décisions pures, sans WordPress.
 *
 * Aucune fonction de ce fichier ne lit `$_SERVER`, une constante ou
 * `wp_get_environment_type()` : tout est reçu en paramètre par l'appelant
 * (includes/guard.php, seul habilité à ces lectures). C'est ce qui rend
 * vérifiable, sans site WordPress, la règle la plus sensible de ce
 * greffon : qu'un e-mail ne parte vers un client que si les deux
 * conditions de production sont réunies. Même principe que
 * lme-brands/includes/core.php pour LME_BRANDS_HOST_OVERRIDE.
 *
 * Ce fichier ne touche ni From, ni Sender, ni Reply-To d'aucun message :
 * l'identité d'envoi par marque est posée en amont par
 * mu-plugins/lme-brands/includes/mail-brand.php, sur les crochets de Vik,
 * bien avant que wp_mail() du cœur WordPress ne s'exécute. La vérification
 * n°5 de la recette lit les en-têtes d'une confirmation : elle reste
 * valable telle quelle, le message arrivant simplement dans la boîte
 * fourre-tout au lieu de celle du client. Personne ne doit ajouter ici une
 * réécriture d'expéditeur : ce serait un changement de portée, pas une
 * évolution de ce fichier.
 */

/**
 * Décide si l'environnement courant est la production, au sens strict
 * exigé par docs/briefs/brief-redirection-emails-hors-production.md
 * chapitre 2.a : deux conditions quand l'hôte est connu, jamais une seule.
 *
 * Ne pas se fier à `option_home` (ou à toute valeur qu'un autre greffon
 * réécrit, comme lme-brands le fait pour cette option) : `$host` doit
 * venir de `$_SERVER['HTTP_HOST']`, normalisé, jamais d'une option ou
 * d'une URL calculée.
 *
 * Réserve levée le 22 septembre 2026 (plan-de-marche.md §B9e) : quand
 * l'hôte est absent — WP-CLI, ou un cron déclenché en ligne de commande,
 * aucun des deux n'ayant de requête HTTP donc pas de `HTTP_HOST` — il n'y
 * a rien à comparer à la liste des hôtes de production, et exiger quand
 * même une correspondance revenait à ne jamais reconnaître la production
 * dans ce contexte. `wp_get_environment_type()` seul décide alors : sur
 * cette installation, établi par lecture directe de wp-config.php et de
 * wp-includes/load.php le 22 septembre 2026 (docs/briefs/constat-mail-guard.md
 * chapitre 1), la production ne définit pas `WP_ENVIRONMENT_TYPE` et
 * `wp_get_environment_type()` y retombe sur son défaut `'production'` —
 * donc cette branche reconnaît bien la production réelle, pas une
 * hypothèse. La préproduction, où la constante vaut `'staging'`, continue
 * de tout détourner : le premier `if` de cette fonction l'a déjà exclue
 * avant qu'on regarde l'hôte.
 *
 * @param string      $environment_type Valeur de wp_get_environment_type().
 * @param string|null $host             Hôte HTTP courant, déjà normalisé
 *                                       (minuscules, sans port) par
 *                                       lme_mail_guard_normalize_host(), ou
 *                                       null si absent (CLI, cron sans
 *                                       requête HTTP).
 * @param string[]    $production_hosts Liste des hôtes de production.
 * @return bool
 */
function lme_mail_guard_is_production_context( $environment_type, $host, array $production_hosts ) {
	if ( 'production' !== $environment_type ) {
		return false;
	}

	if ( ! is_string( $host ) || '' === $host ) {
		// Hors requête HTTP : pas d'hôte à vérifier, wp_get_environment_type()
		// tranche seul. Ne jamais retourner false ici — un rappel Vik lancé
		// par le cron de production disparaîtrait sans avertissement
		// (plan-de-marche.md §B9e).
		return true;
	}

	$normalized_list = array_map( 'strtolower', $production_hosts );

	return in_array( $host, $normalized_list, true );
}

/**
 * Décide si un e-mail sortant doit être détourné vers l'adresse
 * fourre-tout, en composant la décision de production avec l'échappatoire
 * `FORCE_EMAIL_REDIRECT` (brief, chapitre 2.a) : utile pour le cas du
 * clone qui garde le domaine de production (l'hôte matche la liste, mais
 * ce n'est pas vraiment la production) — Thomas force alors la
 * redirection malgré les deux conditions.
 *
 * @param string      $environment_type
 * @param string|null $host
 * @param string[]    $production_hosts
 * @param bool        $force_redirect Valeur de la constante
 *                                     FORCE_EMAIL_REDIRECT si elle est
 *                                     définie et vaut exactement true,
 *                                     false sinon.
 * @return bool
 */
function lme_mail_guard_should_redirect( $environment_type, $host, array $production_hosts, $force_redirect ) {
	if ( true === $force_redirect ) {
		return true;
	}

	return ! lme_mail_guard_is_production_context( $environment_type, $host, $production_hosts );
}

/**
 * Normalise un hôte HTTP brut ($_SERVER['HTTP_HOST']) : minuscules, port
 * retiré. Même règle que lme_brands_normalize_http_host()
 * (mu-plugins/lme-brands/includes/core.php) : une seule façon de
 * comprendre ce qu'est un hôte HTTP « propre », pour que les deux
 * greffons ne divergent jamais sur ce point.
 *
 * @param mixed $raw_host
 * @return string|null
 */
function lme_mail_guard_normalize_host( $raw_host ) {
	if ( ! is_string( $raw_host ) || '' === $raw_host ) {
		return null;
	}

	$host = strtolower( $raw_host );
	$host = preg_replace( '/:\d+$/', '', $host );

	return '' === $host ? null : $host;
}

/**
 * Les hôtes de l'installation WordPress qui porte ce greffon, établis par
 * lecture directe du serveur (SSH, en lecture seule) le 22 septembre 2026 :
 *
 *   - `linstantcle.ch`, `reservation.sexcaperoom.ch`,
 *     `maisonnette-enchantee.ch` et `api.linstantcle.ch` partagent le même
 *     inode pour `index.php` et `.htaccess` (119038289 sur gfram1004 ce
 *     jour-là) : un seul répertoire, un seul wp-content/, un seul
 *     wp_mail() — fait déjà établi dans
 *     docs/briefs/brief-verrou-hote-reservation.md ligne 66, revérifié ici
 *     par la même méthode (`ls -i`) avant d'être encodé dans ce fichier.
 *   - `www.linstantcle.ch` n'a pas de répertoire propre (pas de domaine
 *     garé séparé), mais répond en 301 vers `https://www.linstantcle.ch/fr/`
 *     — donc servi par cette même installation WordPress, sous ce même
 *     hôte, plutôt que redirigé vers le domaine nu par le serveur. Un
 *     visiteur peut donc réserver, et déclencher un envoi, sous cet hôte.
 *   - À l'inverse, `maisonnette-enchantee.ch` (nu et en www) redirige en
 *     301 vers `https://linstantcle.ch/` — donc absorbé par la redirection
 *     canonique de WordPress sur une requête GET. Gardé dans la liste tout
 *     de même : cette redirection ne s'applique pas aux requêtes POST
 *     (comportement documenté du cœur WordPress, `redirect_canonical()`),
 *     ni à l'API REST ni à `admin-ajax.php`, des chemins qu'un envoi
 *     déclenché par Vik ou un formulaire peut emprunter sous cet hôte.
 *   - `admin.linstantcle.ch` est une installation WordPress distincte
 *     (inode différent, 119054161 ce jour-là) : hors de ce greffon, qui ne
 *     s'y déploie de toute façon pas.
 *   - Aucun sous-domaine `www.` n'existe pour `reservation.sexcaperoom.ch`
 *     ni pour `api.linstantcle.ch` (DNS ne résout pas) : non ajoutés,
 *     n'étant pas devinés.
 *   - `sexcaperoom.ch` (nu) est un site distinct, sur un serveur distinct
 *     (gvam1277) : hors de cette installation, donc hors de cette liste.
 *
 * Cette liste n'est pas une correspondance chambre/marque/expérience/
 * espace au sens de la règle absolue n°5 de CLAUDE.md : c'est un fait
 * d'infrastructure (quels hôtes partagent ce wp_mail()), pas une donnée
 * métier. Elle vit ici, en code, pour la même raison que
 * lme-brands/includes/registry.php code en dur la liste des niveaux de
 * journalisation valides : une liste de hôtes qui change aussi rarement
 * que le montage SiteGround lui-même.
 *
 * @return string[]
 */
function lme_mail_guard_default_production_hosts() {
	return array(
		'linstantcle.ch',
		'www.linstantcle.ch',
		'reservation.sexcaperoom.ch',
		'maisonnette-enchantee.ch',
		'api.linstantcle.ch',
	);
}

/**
 * Normalise la forme réelle de $args['headers'] telle que wp_mail() du
 * cœur WordPress la traite (wp-includes/pluggable.php, fonction wp_mail(),
 * lu sur le serveur le 22 septembre 2026) : une chaîne vide par défaut,
 * une chaîne à une ou plusieurs lignes séparées par "\r\n" ou "\n", ou un
 * tableau de lignes « Nom: valeur ». Jamais un tableau associatif indexé
 * par nom d'en-tête — le cœur ne l'accepte pas non plus.
 *
 * Corrige le défaut 1.c du brief : le code proposé faisait
 * `if ( ! empty( $args['headers'] ) ) { … }` puis `$args['headers'][] = …`
 * sans normaliser d'abord, ce qui plante avec une erreur fatale PHP 8
 * (« [] operator not supported for strings ») dès que headers vaut '',
 * son cas le plus courant. Ici, la normalisation est inconditionnelle et
 * précède toute lecture : elle ne suppose jamais la forme, elle la produit.
 *
 * Les lignes sans deux-points (le marqueur `boundary=` d'un message
 * multipart, cas réel traité par le cœur) sont conservées telles quelles :
 * ce n'est pas à ce greffon de les interpréter, seulement de ne pas les
 * perdre avant que le cœur ne les relise.
 *
 * @param mixed $headers Valeur brute de $args['headers'].
 * @return string[] Tableau de lignes d'en-tête, sans ligne vide.
 */
function lme_mail_guard_normalize_headers( $headers ) {
	if ( empty( $headers ) ) {
		return array();
	}

	if ( is_array( $headers ) ) {
		$lines = $headers;
	} else {
		$lines = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
	}

	$normalized = array();

	foreach ( $lines as $line ) {
		if ( ! is_string( $line ) ) {
			continue;
		}

		$trimmed = trim( $line );

		if ( '' === $trimmed ) {
			continue;
		}

		$normalized[] = $trimmed;
	}

	return $normalized;
}

/**
 * Retire les en-têtes Cc et Bcc d'un tableau déjà normalisé
 * (lme_mail_guard_normalize_headers()), pour qu'aucun destinataire réel
 * mis en copie ne reçoive le message détourné.
 *
 * Corrige le défaut 1.d du brief : le code proposé gardait une ligne dès
 * que `stripos( trim( $h ), 'cc:' ) !== 0`, ce qui teste si la sous-chaîne
 * « cc: » apparaît à la position 0. Sur "Bcc: x@example.com", elle
 * apparaît en position 1 (juste après le « B »), donc le test rend `1`,
 * différent de `0`, et la ligne est conservée : le Bcc fuit malgré le
 * commentaire d'intention. Ici, seul le **nom** de l'en-tête (la partie
 * avant le premier deux-points, comparée en entier, insensible à la
 * casse) décide, jamais une sous-chaîne trouvée n'importe où dans la
 * ligne — donc « Accept: cc:something » (un en-tête dont la valeur
 * contient littéralement « cc: ») n'est pas confondu avec un en-tête Cc.
 *
 * @param string[] $header_lines
 * @return string[]
 */
function lme_mail_guard_strip_cc_bcc( array $header_lines ) {
	return array_values(
		array_filter(
			$header_lines,
			function ( $line ) {
				if ( false === strpos( $line, ':' ) ) {
					return true;
				}

				list( $name ) = explode( ':', $line, 2 );
				$name         = strtolower( trim( $name ) );

				return ! in_array( $name, array( 'cc', 'bcc' ), true );
			}
		)
	);
}

/**
 * Forme lisible du destinataire d'origine, pour l'en-tête X-Original-To.
 * $to peut être un tableau (wp_mail() l'accepte) ou une chaîne, éventuellement
 * à plusieurs adresses séparées par des virgules — wp_mail() accepte les
 * deux formes indifféremment (@param string|string[] $to).
 *
 * @param mixed $to
 * @return string Chaîne vide si $to n'a produit aucune adresse exploitable.
 */
function lme_mail_guard_format_original_to( $to ) {
	if ( is_array( $to ) ) {
		$parts = array();

		foreach ( $to as $address ) {
			if ( is_string( $address ) && '' !== trim( $address ) ) {
				$parts[] = trim( $address );
			}
		}

		return implode( ', ', $parts );
	}

	return is_string( $to ) ? trim( $to ) : '';
}

/**
 * Compose la normalisation, le retrait Cc/Bcc et l'ajout de X-Original-To :
 * les en-têtes réellement livrées au message détourné.
 *
 * @param mixed $original_headers Valeur brute de $args['headers'] avant détournement.
 * @param mixed $original_to      Valeur brute de $args['to'] avant détournement.
 * @return string[]
 */
function lme_mail_guard_build_redirected_headers( $original_headers, $original_to ) {
	$headers = lme_mail_guard_normalize_headers( $original_headers );
	$headers = lme_mail_guard_strip_cc_bcc( $headers );

	$original_to_value = lme_mail_guard_format_original_to( $original_to );

	if ( '' !== $original_to_value ) {
		$headers[] = 'X-Original-To: ' . $original_to_value;
	}

	return $headers;
}

/**
 * Cherche la valeur d'un en-tête par son nom (la partie avant le premier
 * deux-points, comparée en entier, insensible à la casse — même règle que
 * lme_mail_guard_strip_cc_bcc()), dans un tableau déjà normalisé
 * (lme_mail_guard_normalize_headers()). Rend la première occurrence.
 *
 * Sert à la transcription d'enveloppe (chapitre 2 du brief
 * docs/briefs/brief-recette-automatisee.md) pour lire From, Sender et
 * Reply-To sans dupliquer la logique de nommage d'en-tête déjà écrite pour
 * Cc/Bcc.
 *
 * @param string[] $header_lines
 * @param string   $name
 * @return string|null null si l'en-tête n'apparaît pas, jamais une chaîne
 *                      vide qui se confondrait avec un en-tête présent mais
 *                      vide.
 */
function lme_mail_guard_find_header_value( array $header_lines, $name ) {
	$wanted = strtolower( $name );

	foreach ( $header_lines as $line ) {
		if ( ! is_string( $line ) || false === strpos( $line, ':' ) ) {
			continue;
		}

		list( $line_name, $line_value ) = explode( ':', $line, 2 );

		if ( strtolower( trim( $line_name ) ) === $wanted ) {
			return trim( $line_value );
		}
	}

	return null;
}

/**
 * Aplatit un champ de la transcription d'enveloppe sur une seule ligne : un
 * objet ou une valeur d'en-tête injectée avec un retour à la ligne ne doit
 * jamais faire déborder l'enregistrement JSON sur plusieurs lignes du
 * journal.
 *
 * @param mixed $value
 * @return string
 */
function lme_mail_guard_envelope_sanitize_field( $value ) {
	$value = str_replace( array( "\r", "\n" ), ' ', (string) $value );

	return trim( $value );
}

/**
 * Une ligne de la transcription d'enveloppe, chapitre 2 du brief
 * docs/briefs/brief-recette-automatisee.md : To d'origine, From, Sender,
 * Reply-To, objet et hôte — jamais le corps ni les pièces jointes, qui ne
 * sont même pas des paramètres de cette fonction, pour qu'aucun appelant ne
 * puisse les y glisser par erreur.
 *
 * Une ligne JSON, pas le format "PREFIX [NIVEAU] [code] message" de
 * lme_mail_guard_log() : ce journal est lu par un script (recetter-moteur.sh,
 * vérifications 5 et 7), pas par un humain qui grep un message d'erreur, et
 * JSON évite d'avoir à écrire un second analyseur pour une forme ad hoc.
 * JSON_UNESCAPED_UNICODE et JSON_UNESCAPED_SLASHES : le journal reste lisible
 * tel quel par un `cat`/`tail` en SSH, sans échapper les caractères
 * accentués.
 *
 * @param string   $timestamp        Horodatage déjà formaté par l'appelant (ex. gmdate('c')).
 * @param mixed    $original_to      Valeur brute de $args['to'] avant tout détournement.
 * @param string[] $original_headers En-têtes déjà normalisées (lme_mail_guard_normalize_headers()), avant tout détournement.
 * @param mixed    $subject          Valeur brute de $args['subject'].
 * @param string|null $host          Hôte HTTP courant, ou null hors requête HTTP.
 * @return string
 */
function lme_mail_guard_format_envelope_line( $timestamp, $original_to, array $original_headers, $subject, $host ) {
	$fields = array(
		'ts'       => lme_mail_guard_envelope_sanitize_field( $timestamp ),
		'to'       => lme_mail_guard_envelope_sanitize_field( lme_mail_guard_format_original_to( $original_to ) ),
		'from'     => lme_mail_guard_envelope_sanitize_field( lme_mail_guard_find_header_value( $original_headers, 'From' ) ),
		'sender'   => lme_mail_guard_envelope_sanitize_field( lme_mail_guard_find_header_value( $original_headers, 'Sender' ) ),
		'reply_to' => lme_mail_guard_envelope_sanitize_field( lme_mail_guard_find_header_value( $original_headers, 'Reply-To' ) ),
		'subject'  => lme_mail_guard_envelope_sanitize_field( $subject ),
		'host'     => lme_mail_guard_envelope_sanitize_field( $host ),
	);

	return json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Résout l'adresse fourre-tout depuis la constante wp-config.php
 * `LME_MAIL_GUARD_CATCHALL_EMAIL`, avec repli inerte : non définie, ou
 * définie à une chaîne vide ou blanche, rend null. C'est à l'appelant de
 * traiter null comme « ne pas envoyer plutôt qu'envoyer à une adresse par
 * défaut » (brief, chapitre « l'adresse fourre-tout est un réglage ») —
 * cette fonction ne choisit jamais elle-même une adresse de repli.
 *
 * @param bool  $defined   defined( 'LME_MAIL_GUARD_CATCHALL_EMAIL' ), lu
 *                          par l'appelant.
 * @param mixed $raw_value Valeur de la constante si $defined, n'importe
 *                          quoi sinon (ignoré).
 * @return string|null
 */
function lme_mail_guard_resolve_catchall( $defined, $raw_value ) {
	if ( ! $defined ) {
		return null;
	}

	$value = is_string( $raw_value ) ? trim( $raw_value ) : '';

	return '' === $value ? null : $value;
}

/**
 * Destinataires définitifs d'un message détourné, décidés au dernier moment
 * où WordPress laisse la main : `phpmailer_init`, dans wp_mail(), juste avant
 * `$phpmailer->send()`. Le filtre `wp_mail` de ce greffon réécrit `to` et
 * retire Cc/Bcc, mais tout greffon peut encore appeler addAddress(), addCC()
 * ou addBCC() sur l'objet PHPMailer après lui ; ici, hors production, tout est
 * vidé et seule l'adresse fourre-tout est remise.
 *
 * Aucune adresse fourre-tout (null) : aucun destinataire. PHPMailer refuse
 * alors d'envoyer (« You must provide at least one recipient »), wp_mail()
 * l'intercepte et rend false — le repli inerte du brief, jamais une adresse
 * par défaut.
 *
 * `dropped` liste les adresses présentes qui ne sont pas la fourre-tout,
 * comparées sans casse ni espaces : ce sont celles qu'un autre code a
 * ajoutées après le filtre `wp_mail`, que l'appelant doit journaliser
 * (règle absolue n°6).
 *
 * @param string[]    $present  Adresses de To, Cc et Bcc au moment de phpmailer_init.
 * @param string|null $catchall lme_mail_guard_resolve_catchall().
 * @return array{keep: string[], dropped: string[]}
 */
function lme_mail_guard_plan_final_recipients( array $present, $catchall ) {
	$catchall_norm = is_string( $catchall ) ? strtolower( trim( $catchall ) ) : '';
	$dropped       = array();

	foreach ( $present as $address ) {
		if ( ! is_string( $address ) || '' === trim( $address ) ) {
			continue;
		}

		if ( '' !== $catchall_norm && strtolower( trim( $address ) ) === $catchall_norm ) {
			continue;
		}

		$dropped[] = trim( $address );
	}

	return array(
		'keep'    => '' === $catchall_norm ? array() : array( trim( $catchall ) ),
		'dropped' => $dropped,
	);
}

/**
 * Domaines des adresses écartées, dédoublonnés et triés : ce que le journal
 * garde d'un destinataire ajouté après le filtre, pour savoir quel code l'a
 * posé sans écrire une adresse personnelle de plus dans debug.log.
 *
 * @param string[] $addresses
 * @return string[]
 */
function lme_mail_guard_address_domains( array $addresses ) {
	$domains = array();

	foreach ( $addresses as $address ) {
		$at = strrpos( (string) $address, '@' );
		$domains[] = false === $at ? '(sans domaine)' : strtolower( substr( $address, $at + 1 ) );
	}

	$domains = array_values( array_unique( $domains ) );
	sort( $domains );

	return $domains;
}
