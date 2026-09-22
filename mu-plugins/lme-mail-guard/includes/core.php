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
 * chapitre 2.a : deux conditions, jamais une seule.
 *
 * Ne pas se fier à `option_home` (ou à toute valeur qu'un autre greffon
 * réécrit, comme lme-brands le fait pour cette option) : `$host` doit
 * venir de `$_SERVER['HTTP_HOST']`, normalisé, jamais d'une option ou
 * d'une URL calculée.
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
		return false;
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
