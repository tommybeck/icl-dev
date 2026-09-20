<?php
/**
 * lme-brands — logique pure.
 *
 * Aucune fonction de ce fichier n'appelle une fonction WordPress. C'est
 * volontaire : c'est ce qui permet de le tester avec `php`, sans site
 * WordPress, sans base de données, sans PHPUnit. Voir tests/test-core.php.
 *
 * Aucun identifiant de chambre, aucun nom de marque, aucun hôte n'est écrit
 * en dur ici : tout vient du tableau $config passé en paramètre, lui-même
 * chargé depuis config/brands.php par includes/registry.php.
 *
 * Ce fichier n'a pas de garde ABSPATH, contrairement aux autres : il ne
 * définit que des fonctions, sans effet de bord, et doit rester chargeable
 * directement par le harnais de tests en ligne de commande.
 */

/**
 * Valide la forme du registre des marques. Ne devine jamais : une anomalie
 * devient une entrée dans le tableau retourné, jamais une correction
 * silencieuse.
 *
 * @param mixed $config
 * @return string[] Liste d'erreurs lisibles. Vide si le registre est valide.
 */
function lme_brands_validate_config( $config ) {
	$errors = array();

	if ( ! is_array( $config ) ) {
		return array( "Le registre n'est pas un tableau." );
	}

	if ( empty( $config['brands'] ) || ! is_array( $config['brands'] ) ) {
		$errors[]          = "Le registre ne définit aucune marque (clé 'brands' manquante ou vide).";
		$config['brands'] = array();
	}

	$required_string_fields = array( 'label', 'host', 'sender_email', 'sender_name', 'reply_to', 'signature' );
	$seen_hosts             = array();

	foreach ( $config['brands'] as $brand_key => $brand ) {
		if ( ! is_string( $brand_key ) || '' === $brand_key ) {
			$errors[] = 'Une marque a une clé invalide : ' . var_export( $brand_key, true ) . '.';
			continue;
		}

		if ( ! is_array( $brand ) ) {
			$errors[] = "La marque '{$brand_key}' n'est pas un tableau.";
			continue;
		}

		foreach ( $required_string_fields as $field ) {
			if ( empty( $brand[ $field ] ) || ! is_string( $brand[ $field ] ) ) {
				$errors[] = "La marque '{$brand_key}' n'a pas de champ '{$field}' valide.";
			}
		}

		if ( ! array_key_exists( 'confirmation_page_id', $brand ) || ! is_int( $brand['confirmation_page_id'] ) || $brand['confirmation_page_id'] < 0 ) {
			$errors[] = "La marque '{$brand_key}' n'a pas de 'confirmation_page_id' entier valide.";
		}

		if ( empty( $brand['languages'] ) || ! is_array( $brand['languages'] ) ) {
			$errors[] = "La marque '{$brand_key}' n'a pas de 'languages' valide.";
		} else {
			foreach ( $brand['languages'] as $lang ) {
				if ( ! is_string( $lang ) || '' === $lang ) {
					$errors[] = "La marque '{$brand_key}' a une langue invalide dans 'languages'.";
					break;
				}
			}
		}

		if ( ! empty( $brand['host'] ) && is_string( $brand['host'] ) ) {
			$host_key = strtolower( $brand['host'] );
			if ( isset( $seen_hosts[ $host_key ] ) ) {
				$errors[] = "L'hôte '{$brand['host']}' est utilisé par plusieurs marques ('{$seen_hosts[ $host_key ]}' et '{$brand_key}').";
			} else {
				$seen_hosts[ $host_key ] = $brand_key;
			}
		}

		// Piège relevé dans VBOMailWrapper::setSender() (wrapper.php:182,
		// constat-phase-0.md Q2) : `'name' => $address != $name ? $name : null`.
		// Passer la même chaîne comme adresse et comme nom fait disparaître le
		// nom, et VBOMailWrapper::getSenderName() se replie alors sur
		// VikBooking::getFrontTitle(), c'est-à-dire le titre global de
		// l'installation — donc le nom de l'autre marque. Une égalité ici ne
		// produirait pas un nom manquant, elle produirait la mauvaise marque.
		if ( ! empty( $brand['sender_email'] ) && is_string( $brand['sender_email'] )
			&& ! empty( $brand['sender_name'] ) && is_string( $brand['sender_name'] )
			&& $brand['sender_email'] === $brand['sender_name'] ) {
			$errors[] = "La marque '{$brand_key}' a le même 'sender_email' et 'sender_name' : VBOMailWrapper effacerait le nom et se replierait sur le titre global du site.";
		}

		if ( array_key_exists( 'mail', $brand ) ) {
			foreach ( lme_brands_validate_brand_mail( $brand_key, $brand['mail'] ) as $mail_error ) {
				$errors[] = $mail_error;
			}
		}
	}

	if ( ! isset( $config['rooms'] ) || ! is_array( $config['rooms'] ) ) {
		$errors[]         = "Le registre ne définit aucune chambre (clé 'rooms' manquante ou invalide).";
		$config['rooms'] = array();
	}

	foreach ( $config['rooms'] as $room_id => $room ) {
		if ( ! is_int( $room_id ) && ! ( is_string( $room_id ) && ctype_digit( $room_id ) ) ) {
			$errors[] = 'Une chambre a une clé invalide : ' . var_export( $room_id, true ) . '.';
			continue;
		}

		$room_id = (int) $room_id;

		if ( ! is_array( $room ) ) {
			$errors[] = "La chambre #{$room_id} n'est pas un tableau.";
			continue;
		}

		if ( empty( $room['brand'] ) || ! is_string( $room['brand'] ) || ! isset( $config['brands'][ $room['brand'] ] ) ) {
			$errors[] = "La chambre #{$room_id} pointe vers une marque inconnue.";
		}

		foreach ( array( 'name', 'experience' ) as $field ) {
			if ( empty( $room[ $field ] ) || ! is_string( $room[ $field ] ) ) {
				$errors[] = "La chambre #{$room_id} n'a pas de champ '{$field}' valide.";
			}
		}

		if ( array_key_exists( 'forfait', $room ) && null !== $room['forfait'] && ( ! is_string( $room['forfait'] ) || '' === $room['forfait'] ) ) {
			$errors[] = "La chambre #{$room_id} a un 'forfait' invalide (doit être une chaîne non vide ou null).";
		}

		if ( array_key_exists( 'availability_group', $room ) && null !== $room['availability_group'] && ( ! is_string( $room['availability_group'] ) || '' === $room['availability_group'] ) ) {
			$errors[] = "La chambre #{$room_id} a un 'availability_group' invalide (doit être une chaîne non vide ou null).";
		}
	}

	foreach ( lme_brands_validate_neutral( isset( $config['neutral'] ) ? $config['neutral'] : null ) as $neutral_error ) {
		$errors[] = $neutral_error;
	}

	return $errors;
}

/**
 * Valide le bloc `mail` optionnel d'une marque. Chapitre 4.4 du brief.
 *
 * @param string $brand_key
 * @param mixed  $mail
 * @return string[]
 */
function lme_brands_validate_brand_mail( $brand_key, $mail ) {
	$errors = array();

	if ( ! is_array( $mail ) ) {
		return array( "La marque '{$brand_key}' a un bloc 'mail' qui n'est pas un tableau." );
	}

	if ( array_key_exists( 'subject', $mail ) ) {
		if ( ! is_array( $mail['subject'] ) ) {
			$errors[] = "La marque '{$brand_key}' a un 'mail.subject' qui n'est pas une table langue => objet.";
		} else {
			foreach ( $mail['subject'] as $lang => $subject ) {
				if ( ! is_string( $lang ) || '' === $lang || ! is_string( $subject ) || '' === $subject ) {
					$errors[] = "La marque '{$brand_key}' a une entrée invalide dans 'mail.subject'.";
					break;
				}
			}
		}
	}

	if ( array_key_exists( 'replacements', $mail ) ) {
		if ( ! is_array( $mail['replacements'] ) ) {
			$errors[] = "La marque '{$brand_key}' a un 'mail.replacements' qui n'est pas un tableau.";
		} else {
			foreach ( $mail['replacements'] as $from => $to ) {
				if ( ! is_string( $from ) || '' === $from || ! is_string( $to ) ) {
					$errors[] = "La marque '{$brand_key}' a une substitution invalide dans 'mail.replacements' (clé et valeur doivent être des chaînes, la clé non vide).";
					break;
				}
			}
		}
	}

	if ( array_key_exists( 'signature_html', $mail ) && null !== $mail['signature_html']
		&& ( ! is_string( $mail['signature_html'] ) || '' === $mail['signature_html'] ) ) {
		$errors[] = "La marque '{$brand_key}' a un 'mail.signature_html' invalide (chaîne non vide ou null).";
	}

	return $errors;
}

/**
 * Valide l'identité neutre, obligatoire.
 *
 * Elle l'est parce que sans elle, une marque indéterminée n'aurait pas
 * d'expéditeur à porter et le message partirait sous l'expéditeur global de
 * l'installation, c'est-à-dire potentiellement sous la mauvaise marque — ce
 * que le §4.4 du brief interdit sans exception.
 *
 * @param mixed $neutral
 * @return string[]
 */
function lme_brands_validate_neutral( $neutral ) {
	$errors = array();

	if ( ! is_array( $neutral ) ) {
		return array( "Le registre ne définit aucune identité neutre (clé 'neutral' manquante ou invalide)." );
	}

	foreach ( array( 'label', 'sender_name' ) as $field ) {
		if ( empty( $neutral[ $field ] ) || ! is_string( $neutral[ $field ] ) ) {
			$errors[] = "L'identité neutre n'a pas de champ '{$field}' valide.";
		}
	}

	foreach ( array( 'sender_email', 'reply_to' ) as $field ) {
		if ( ! array_key_exists( $field, $neutral ) ) {
			$errors[] = "L'identité neutre n'a pas de champ '{$field}' (null accepté, absent non).";
			continue;
		}

		if ( null !== $neutral[ $field ] && ( ! is_string( $neutral[ $field ] ) || '' === $neutral[ $field ] ) ) {
			$errors[] = "L'identité neutre a un '{$field}' invalide (chaîne non vide ou null).";
		}
	}

	if ( ! isset( $neutral['subject'] ) || ! is_array( $neutral['subject'] ) || empty( $neutral['subject'] ) ) {
		$errors[] = "L'identité neutre n'a pas de 'subject' valide (table langue => objet, non vide).";
	} else {
		foreach ( $neutral['subject'] as $lang => $subject ) {
			if ( ! is_string( $lang ) || '' === $lang || ! is_string( $subject ) || '' === $subject ) {
				$errors[] = "L'identité neutre a une entrée invalide dans 'subject'.";
				break;
			}
		}
	}

	return $errors;
}

/**
 * Résout la marque d'une chambre à partir de son identifiant Vik.
 *
 * Deux issues possibles, jamais une troisième devinée (décision de Thomas,
 * 14 septembre 2026, docs/briefs/brief-correctif-phase-2-filtrage.md
 * chapitre 2) :
 *   - 'ok'      : la chambre est déclarée dans le registre, sa marque est
 *                 connue. Toute chambre active dans Vik (avail = 1) doit
 *                 avoir cette issue ; une chambre désactivée (avail = 0)
 *                 porte aussi une marque si elle est déclarée (chambres de
 *                 test 5 et 6), mais n'est de toute façon jamais servie —
 *                 voir includes/booking-guard.php pour le refus sur avail.
 *   - 'unknown' : chambre absente du registre. Chapitre 6 : toujours une
 *                 erreur journalisée par l'appelant, jamais un cas par défaut.
 *
 * @param array      $config
 * @param int|string $room_id
 * @return array
 */
function lme_brands_resolve_room( array $config, $room_id ) {
	$room_id = (int) $room_id;

	if ( isset( $config['rooms'][ $room_id ] ) && is_array( $config['rooms'][ $room_id ] ) ) {
		$room      = $config['rooms'][ $room_id ];
		$brand_key = isset( $room['brand'] ) ? $room['brand'] : null;
		$brand     = ( is_string( $brand_key ) && isset( $config['brands'][ $brand_key ] ) )
			? $config['brands'][ $brand_key ]
			: null;

		if ( null !== $brand ) {
			return array(
				'status'              => 'ok',
				'room_id'             => $room_id,
				'brand_key'           => $brand_key,
				'brand'               => $brand,
				'name'                => isset( $room['name'] ) ? $room['name'] : null,
				'experience'          => isset( $room['experience'] ) ? $room['experience'] : null,
				'forfait'             => isset( $room['forfait'] ) ? $room['forfait'] : null,
				'availability_group'  => isset( $room['availability_group'] ) ? $room['availability_group'] : null,
			);
		}

		// La chambre est déclarée, mais pointe vers une marque qui n'existe
		// pas : le registre est incohérent. Ne jamais deviner, traiter comme
		// une chambre absente.
	}

	return array(
		'status'  => 'unknown',
		'room_id' => $room_id,
	);
}

/**
 * Décide de l'issue d'une tentative de réservation pour une chambre, sans
 * aucun effet de bord — la lecture de `avail` (Vik) et la résolution du
 * registre sont faites par l'appelant (includes/booking-guard.php), qui n'a
 * pas d'équivalent testable ici. Chapitre 5 et chapitre 6 du brief
 * docs/briefs/brief-correctif-phase-2-filtrage.md.
 *
 * Le refus sur indisponibilité est vérifié en premier et sans condition :
 * une chambre à `avail = 0` est refusée quelle que soit sa marque, y
 * compris quand elle correspond à celle de l'hôte courant.
 *
 * @param array      $resolved       Voir lme_brands_resolve_room().
 * @param bool|null  $available      true/false lu depuis
 *                                    sir_vikbooking_rooms.avail ; null si la
 *                                    chambre est absente de Vik ou la table
 *                                    inaccessible — jamais interprété comme
 *                                    une indisponibilité.
 * @param string     $expected_brand Clé de marque de l'hôte de la requête.
 * @return string 'allow', 'refuse_unavailable', 'refuse_foreign_brand', ou
 *                'refuse_unknown_room'.
 */
function lme_brands_evaluate_booking_room( array $resolved, $available, $expected_brand ) {
	if ( false === $available ) {
		return 'refuse_unavailable';
	}

	if ( 'unknown' === $resolved['status'] ) {
		return 'refuse_unknown_room';
	}

	if ( 'ok' === $resolved['status'] && $resolved['brand_key'] === $expected_brand ) {
		return 'allow';
	}

	return 'refuse_foreign_brand';
}

/**
 * Résout la clé de marque à partir d'un hôte de requête. Comparaison exacte,
 * insensible à la casse : pas de correspondance partielle, pas de deviner.
 *
 * @param array  $config
 * @param string $host
 * @return string|null
 */
function lme_brands_resolve_brand_by_host( array $config, $host ) {
	if ( ! is_string( $host ) || '' === $host ) {
		return null;
	}

	$host   = strtolower( $host );
	$brands = isset( $config['brands'] ) && is_array( $config['brands'] ) ? $config['brands'] : array();

	foreach ( $brands as $brand_key => $brand ) {
		if ( isset( $brand['host'] ) && is_string( $brand['host'] ) && strtolower( $brand['host'] ) === $host ) {
			return $brand_key;
		}
	}

	return null;
}

/**
 * Remplace l'hôte d'une URL absolue, en conservant schéma, port, chemin,
 * requête et fragment. Ne touche pas aux URL déjà sur le bon hôte, ni aux
 * chaînes qui ne sont pas des URL absolues.
 *
 * @param mixed  $url
 * @param string $new_host
 * @return mixed La valeur d'origine si elle n'est pas exploitable.
 */
function lme_brands_swap_url_host( $url, $new_host ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return $url;
	}

	if ( ! is_string( $new_host ) || '' === $new_host ) {
		return $url;
	}

	$parts = parse_url( $url );

	if ( false === $parts || empty( $parts['host'] ) ) {
		return $url;
	}

	if ( 0 === strcasecmp( $parts['host'], $new_host ) ) {
		return $url;
	}

	$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//';

	$userinfo = '';
	if ( isset( $parts['user'] ) ) {
		$userinfo = $parts['user'];
		if ( isset( $parts['pass'] ) ) {
			$userinfo .= ':' . $parts['pass'];
		}
		$userinfo .= '@';
	}

	$port     = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
	$path     = isset( $parts['path'] ) ? $parts['path'] : '';
	$query    = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
	$fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

	return $scheme . $userinfo . $new_host . $port . $path . $query . $fragment;
}

/**
 * Décide de l'hôte à utiliser pour la résolution de marque, en tenant compte
 * du levier de préproduction B8. Fonction pure : ne lit ni `$_SERVER`, ni une
 * constante, ni `wp_get_environment_type()` — tout est passé en paramètre par
 * l'appelant (`includes/registry.php`), seul habilité à ces lectures et à la
 * journalisation de l'emploi du levier. C'est ce qui rend vérifiable, sans
 * site WordPress, la règle la plus sensible de ce chantier : que le levier ne
 * puisse jamais s'activer ailleurs qu'en préproduction.
 *
 * Trois garanties, dans l'ordre où le brief les demande
 * (docs/briefs/plan-de-marche.md §B8) :
 *   - le levier n'a aucun effet si `$environment_type` n'est pas exactement
 *     `'staging'` — jamais en production, jamais en local, jamais en
 *     développement, quelle que soit la valeur de la constante ;
 *   - le levier ne vient jamais d'une requête : `$override` est la valeur
 *     déjà lue depuis la constante PHP `LME_BRANDS_HOST_OVERRIDE` par
 *     l'appelant, jamais depuis `$_GET`, `$_POST` ou un en-tête ;
 *   - une constante vide ou absente (`null`) est traitée comme absente : elle
 *     ne force jamais l'hôte vers la chaîne vide, ce qui ferait échouer toute
 *     résolution de marque plutôt que de la laisser inchangée.
 *
 * Le registre ne change pas : cette fonction ne fait que décider quel hôte
 * `lme_brands_resolve_brand_by_host()` recevra ensuite. Un hôte de
 * substitution qui n'est celui d'aucune marque continue de ne résoudre aucune
 * marque, exactement comme un hôte de requête inconnu.
 *
 * @param string|null $request_host     Hôte lu depuis `$_SERVER['HTTP_HOST']`,
 *                                       déjà normalisé (minuscule, sans port),
 *                                       ou `null` si absent.
 * @param string      $environment_type Valeur de `wp_get_environment_type()`.
 * @param mixed       $override         Valeur de la constante
 *                                       `LME_BRANDS_HOST_OVERRIDE` si elle est
 *                                       définie, `null` sinon.
 * @return array{host: string|null, override_used: bool}
 */
function lme_brands_resolve_effective_http_host( $request_host, $environment_type, $override ) {
	if ( 'staging' === $environment_type && is_string( $override ) && '' !== trim( $override ) ) {
		$host = strtolower( trim( $override ) );
		$host = preg_replace( '/:\d+$/', '', $host );

		return array(
			'host'          => $host,
			'override_used' => true,
		);
	}

	return array(
		'host'          => $request_host,
		'override_used' => false,
	);
}

/**
 * Découpe une liste d'identifiants reçue en paramètre de requête : tableau
 * (soumission `nom[]=...`) ou chaîne délimitée par virgule ou point-virgule
 * (format des jetons idrooms/idcat de Vik, constat-phase-0.md Q6). Toute
 * valeur non entière est silencieusement ignorée : un identifiant de
 * chambre est toujours un entier positif.
 *
 * @param mixed $value
 * @return int[]
 */
function lme_brands_parse_id_list( $value ) {
	$parts = is_array( $value ) ? $value : preg_split( '/[,;]+/', (string) $value );
	$ids   = array();

	foreach ( $parts as $part ) {
		$part = trim( (string) $part );
		if ( '' !== $part && ctype_digit( $part ) ) {
			$ids[] = (int) $part;
		}
	}

	return $ids;
}

/**
 * Identifiants de chambre réservée, extraits de la structure `$rooms` telle
 * que Vik la construit juste avant l'insertion (site/controller.php de Vik
 * Booking, méthode saveorder() : chaque élément porte au moins la clé
 * `id`). Défensif sur la forme exacte (tableau ou objet) : une seule
 * fonction pure, testable sans dépendre de la structure réelle de Vik au
 * moment du test.
 *
 * @param mixed $rooms
 * @return int[]
 */
function lme_brands_extract_room_ids( $rooms ) {
	$ids = array();

	if ( ! is_array( $rooms ) ) {
		return $ids;
	}

	foreach ( $rooms as $room_booked ) {
		if ( is_array( $room_booked ) && isset( $room_booked['id'] ) ) {
			$ids[] = (int) $room_booked['id'];
		} elseif ( is_object( $room_booked ) && isset( $room_booked->id ) ) {
			$ids[] = (int) $room_booked->id;
		}
	}

	return array_values( array_unique( $ids ) );
}

/**
 * Identifiants de chambre dont la carte de jetons de catégorie (telle que
 * lue depuis sir_vikbooking_rooms.idcat, chaîne de jetons séparés par `;`,
 * constat-phase-0.md Q4) contient le jeton de cette catégorie. Fonction
 * pure : la lecture de la table est faite par l'appelant
 * (includes/room-filter.php), qui n'a pas d'équivalent testable ici.
 *
 * @param array $room_category_tokens room_id (int) => jetons (string[]).
 * @param int   $category_id
 * @return int[]
 */
function lme_brands_room_ids_matching_category( array $room_category_tokens, $category_id ) {
	$token = (string) (int) $category_id;
	$ids   = array();

	foreach ( $room_category_tokens as $room_id => $tokens ) {
		if ( is_array( $tokens ) && in_array( $token, $tokens, true ) ) {
			$ids[] = (int) $room_id;
		}
	}

	return $ids;
}

/**
 * Limitation de débit pure : décide si une alerte doit partir pour ce code,
 * étant donné un état (map code => dernier horodatage d'alerte) et
 * l'horodatage courant. Ne touche à aucun stockage : includes/logger.php
 * fait le pont avec une option WordPress.
 *
 * @param array  $state
 * @param string $code
 * @param int    $now
 * @param int    $window_seconds
 * @return array{should_alert: bool, state: array}
 */
function lme_brands_rate_limit_gate( array $state, $code, $now, $window_seconds ) {
	$last = isset( $state[ $code ] ) ? (int) $state[ $code ] : null;

	if ( null !== $last && ( $now - $last ) < $window_seconds ) {
		return array(
			'should_alert' => false,
			'state'        => $state,
		);
	}

	$state[ $code ] = $now;

	return array(
		'should_alert' => true,
		'state'        => $state,
	);
}

/**
 * Destinataire visé par une itération de l'envoi de Vik, déduit de `$who`.
 *
 * Le hook `vikbooking_before_send_booking_mail` reçoit `$who` tel quel,
 * c'est-à-dire un élément du tableau `$for` passé à
 * `VikBooking::sendBookingEmail()`. Les quinze appels de cette méthode dans
 * Vik Booking 1.8.14 et VikChannelManager ne passent jamais que `'guest'` ou
 * `'admin'` (relevé exhaustif en §2 de docs/briefs/constat-phase-3-emails.md),
 * donc « `$who` égal à `guest` » du brief décrit exactement le périmètre voulu.
 *
 * Cette fonction reproduit néanmoins la cascade de Vik lui-même
 * (`lib.vikbooking.php:6411-6425`) plutôt que de comparer `$who` à `'guest'` :
 * Vik reconnaît son destinataire par `strpos($who, '@')`, puis par
 * `stripos($who, 'guest')` **ou** `stripos($who, 'customer')`. Un appelant
 * futur qui passerait `'customer'` enverrait donc au client un message que
 * l'égalité stricte laisserait filer — et un message non réécrit part sous
 * l'expéditeur global de l'installation, c'est-à-dire sous une marque qui
 * n'est peut-être pas la bonne. Suivre la cascade de Vik élargit le
 * périmètre exactement là où Vik l'élargit, et nulle part ailleurs.
 *
 * L'ordre des tests est celui de Vik, et il compte : une adresse e-mail
 * littérale contenant le mot « guest » est un destinataire personnalisé,
 * pas le client de la réservation.
 *
 * @param mixed $who
 * @return string 'custom', 'guest', 'admin' ou 'none'.
 */
function lme_brands_mail_audience( $who ) {
	$who = is_string( $who ) ? $who : '';

	if ( false !== strpos( $who, '@' ) ) {
		return 'custom';
	}

	if ( false !== stripos( $who, 'guest' ) || false !== stripos( $who, 'customer' ) ) {
		return 'guest';
	}

	if ( false !== stripos( $who, 'admin' ) ) {
		return 'admin';
	}

	return 'none';
}

/**
 * Ramène une adresse e-mail à une forme comparable : sans espaces de bord,
 * en minuscules.
 *
 * Le domaine d'une adresse est insensible à la casse, la partie locale ne
 * l'est pas formellement — mais aucun service de messagerie courant ne
 * distingue `Jean@…` de `jean@…`, et Vik lui-même ne normalise rien. La
 * comparaison stricte ferait donc échouer un recoupement légitime sur une
 * simple majuscule saisie par un client.
 *
 * @param mixed $address
 * @return string Chaîne vide si l'argument n'est pas une chaîne.
 */
function lme_brands_normalize_email( $address ) {
	if ( ! is_string( $address ) ) {
		return '';
	}

	return strtolower( trim( $address ) );
}

/**
 * La réservation trouvée dans le magasin partagé de Vik est-elle bien celle
 * du message en partance ?
 *
 * C'est la parade du chantier B5, et elle est le cœur du dispositif.
 * `vikbooking_before_send_mail` ne transporte aucun contexte métier : la
 * réservation se retrouve dans `VikBookingHelperConditionalRules`, où
 * l'émetteur la dépose avant de composer son message
 * (`email_reminder.php:729-731`, `lib.vikbooking.php:5734`, et six autres
 * émetteurs). Mais ce magasin est un `protected static` que Vik **ne vide
 * jamais** entre deux envois : le lire sans vérification, c'est risquer
 * d'attribuer à un message la marque du message précédent, exactement la
 * « mauvaise marque » que le §4.4 du brief interdit.
 *
 * On n'y croit donc que si le client de cette réservation est parmi les
 * destinataires du message. Sinon, on ne touche à rien — un message laissé
 * tel quel part sous l'expéditeur global de l'installation, c'est-à-dire
 * l'état d'avant ce plugin, jamais une marque devinée.
 *
 * La comparaison est normalisée mais pas approximative : une adresse de la
 * forme `Nom <a@b.ch>` ne correspondra à rien, et le message sera laissé
 * tel quel. Vik ne pose que des adresses nues à cet endroit
 * (`jv_helper.php:68-80`, `lib.vikbooking.php:6440-6452`) ; le jour où il en
 * poserait d'autres, ne rien faire est la bonne façon de se tromper.
 *
 * @param mixed $booking    Ce que le magasin partagé contient sous 'booking'.
 * @param mixed $recipients `VBOMailWrapper::getRecipient()`, liste ou chaîne.
 * @return bool
 */
function lme_brands_mail_booking_matches_recipients( $booking, $recipients ) {
	if ( ! is_array( $booking ) || empty( $booking['id'] ) || empty( $booking['custmail'] ) ) {
		return false;
	}

	$custmail = lme_brands_normalize_email( $booking['custmail'] );

	if ( '' === $custmail ) {
		return false;
	}

	foreach ( (array) $recipients as $recipient ) {
		if ( lme_brands_normalize_email( $recipient ) === $custmail ) {
			return true;
		}
	}

	return false;
}

/**
 * Résout la marque d'une réservation à partir des chambres qu'elle porte.
 *
 * Le hook d'envoi ne transporte pas les identifiants de chambre
 * (constat-phase-0.md Q2) : l'appelant les relit dans
 * `sir_vikbooking_ordersrooms` par `idorder`, puis passe la liste ici.
 *
 * Une seule issue vaut pour une marque : toutes les chambres de la
 * réservation résolvent, et elles résolvent vers la même marque. Tout le
 * reste est indéterminé, jamais une marque devinée sur la première chambre
 * venue — §4.4 du brief, « jamais sous la mauvaise marque ».
 *
 * Le cas `mixed_brands` n'est pas théorique : la garde de réservation
 * (includes/booking-guard.php) interdit qu'une réservation du tunnel mêle
 * deux marques, mais rien n'interdit à Thomas de composer une telle
 * réservation dans l'administration de Vik.
 *
 * @param array $config
 * @param array $room_ids Identifiants de chambre Vik.
 * @return array{status: string, reason: string, brand_key: string|null, room_ids: int[], brand_keys: string[], unknown_rooms: int[]}
 */
function lme_brands_resolve_brand_for_rooms( array $config, array $room_ids ) {
	$ids = array();
	foreach ( $room_ids as $room_id ) {
		$ids[] = (int) $room_id;
	}
	$ids = array_values( array_unique( $ids ) );

	$result = array(
		'status'        => 'undetermined',
		'reason'        => 'no_rooms',
		'brand_key'     => null,
		'room_ids'      => $ids,
		'brand_keys'    => array(),
		'unknown_rooms' => array(),
	);

	if ( empty( $ids ) ) {
		return $result;
	}

	$brand_keys = array();

	foreach ( $ids as $room_id ) {
		$resolved = lme_brands_resolve_room( $config, $room_id );

		if ( 'ok' !== $resolved['status'] ) {
			$result['unknown_rooms'][] = $room_id;
			continue;
		}

		$brand_keys[ $resolved['brand_key'] ] = true;
	}

	$result['brand_keys'] = array_keys( $brand_keys );

	if ( ! empty( $result['unknown_rooms'] ) ) {
		$result['reason'] = 'unknown_room';
		return $result;
	}

	if ( count( $result['brand_keys'] ) > 1 ) {
		$result['reason'] = 'mixed_brands';
		return $result;
	}

	$result['status']    = 'ok';
	$result['reason']    = '';
	$result['brand_key'] = $result['brand_keys'][0];

	return $result;
}

/**
 * Ramène une étiquette de langue à son code primaire en minuscules.
 *
 * `sir_vikbooking_orders.lang` porte des étiquettes complètes : relevé du
 * 15 septembre 2026 sur les 1758 réservations de la base, `fr-FR`, `de-CH`,
 * `en-US`, `de-DE`, et NULL sur 157 d'entre elles. Le registre, lui,
 * déclare des codes primaires (`fr`, `en`). C'est ici que les deux se
 * rejoignent, et nulle part ailleurs.
 *
 * @param mixed $tag
 * @return string|null
 */
function lme_brands_normalize_language_tag( $tag ) {
	if ( ! is_string( $tag ) ) {
		return null;
	}

	$tag = strtolower( trim( $tag ) );

	if ( '' === $tag ) {
		return null;
	}

	// 'fr-FR' et 'fr_FR' donnent 'fr' ; 'fr' reste 'fr'.
	$tag = preg_replace( '/[^a-z].*$/', '', $tag );

	return '' === $tag ? null : $tag;
}

/**
 * Choisit une chaîne dans une table indexée par langue.
 *
 * Repli assumé sur la première entrée de la table quand aucune langue
 * préférée n'y figure : mieux vaut l'objet d'une autre langue de la **bonne**
 * marque que l'objet natif de Vik, qui porte le titre global de
 * l'installation, donc le nom de l'autre marque.
 *
 * @param mixed    $map       Table langue => chaîne.
 * @param string[] $preferred Langues par ordre de préférence.
 * @return string|null
 */
function lme_brands_pick_localized( $map, array $preferred ) {
	if ( ! is_array( $map ) || empty( $map ) ) {
		return null;
	}

	foreach ( $preferred as $lang ) {
		if ( is_string( $lang ) && '' !== $lang && isset( $map[ $lang ] ) && is_string( $map[ $lang ] ) && '' !== $map[ $lang ] ) {
			return $map[ $lang ];
		}
	}

	foreach ( $map as $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
	}

	return null;
}

/**
 * Applique une table de substitutions littérales, dans l'ordre de
 * déclaration. Une valeur de remplacement vide supprime le terme cherché.
 *
 * @param mixed $text
 * @param mixed $replacements Table « terme cherché » => « remplacement ».
 * @return string
 */
function lme_brands_apply_text_replacements( $text, $replacements ) {
	$text = (string) $text;

	if ( ! is_array( $replacements ) ) {
		return $text;
	}

	foreach ( $replacements as $from => $to ) {
		if ( ! is_string( $from ) || '' === $from || ! is_string( $to ) ) {
			continue;
		}

		$text = str_replace( $from, $to, $text );
	}

	return $text;
}

/**
 * Termes qui trahiraient une autre marque dans un message écrit pour
 * celle-ci. Dérivés du registre, jamais tenus dans une seconde liste :
 * ajouter une marque suffit à étendre le contrôle.
 *
 * @param array       $config
 * @param string|null $brand_key Marque du message ; null n'exclut rien.
 * @return string[]
 */
function lme_brands_foreign_brand_tokens( array $config, $brand_key ) {
	$tokens = array();
	$brands = isset( $config['brands'] ) && is_array( $config['brands'] ) ? $config['brands'] : array();

	foreach ( $brands as $key => $brand ) {
		if ( $key === $brand_key || ! is_array( $brand ) ) {
			continue;
		}

		foreach ( array( 'label', 'host', 'sender_email' ) as $field ) {
			if ( ! empty( $brand[ $field ] ) && is_string( $brand[ $field ] ) ) {
				$tokens[] = $brand[ $field ];
			}
		}
	}

	return array_values( array_unique( $tokens ) );
}

/**
 * Normalise un texte avant recherche : entités HTML décodées, apostrophes
 * typographiques ramenées à l'apostrophe droite.
 *
 * Sans cela, chercher « L'Instant Clé » dans un corps HTML échoue : le nom
 * y apparaît le plus souvent en `L&#039;Instant Clé` ou en `L’Instant Clé`,
 * et le contrôle de fuite déclarerait le message propre alors qu'il ne l'est
 * pas. Le silence d'un contrôle raté est pire que l'absence de contrôle.
 *
 * @param mixed $text
 * @return string
 */
function lme_brands_normalize_for_search( $text ) {
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	return str_replace(
		array( "\xE2\x80\x99", "\xE2\x80\x98", "\xC2\xB4", '`' ),
		"'",
		$text
	);
}

/**
 * Termes d'une autre marque effectivement présents dans un texte.
 *
 * @param mixed    $haystack
 * @param string[] $tokens
 * @return string[] Les termes trouvés, dans leur forme d'origine.
 */
function lme_brands_find_foreign_tokens( $haystack, array $tokens ) {
	$haystack = lme_brands_normalize_for_search( $haystack );
	$found    = array();

	foreach ( $tokens as $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			continue;
		}

		if ( false !== stripos( $haystack, lme_brands_normalize_for_search( $token ) ) ) {
			$found[] = $token;
		}
	}

	return $found;
}

/**
 * Insère un fragment juste avant la dernière balise `</body>`, ou à la fin
 * du document s'il n'y en a pas. Vik enveloppe le corps parsé dans un
 * document HTML complet (`lib.vikbooking.php:6388`) : ajouter après
 * `</html>` produirait un fragment que certains clients de messagerie
 * n'affichent pas.
 *
 * @param mixed $html
 * @param mixed $fragment
 * @return string
 */
function lme_brands_inject_html_before_body_end( $html, $fragment ) {
	$html = (string) $html;

	if ( ! is_string( $fragment ) || '' === $fragment ) {
		return $html;
	}

	$pos = strripos( $html, '</body>' );

	if ( false === $pos ) {
		return $html . $fragment;
	}

	return substr( $html, 0, $pos ) . $fragment . substr( $html, $pos );
}

/**
 * Identité à poser sur un message client, à partir de la marque résolue.
 *
 * Fonction pure : elle décide, elle n'écrit rien. C'est
 * includes/mail-brand.php qui applique le résultat aux mutateurs de
 * `VBOMailWrapper`.
 *
 * Marque indéterminée : l'identité neutre du registre, jamais celle d'une
 * marque. `sender_email` et `reply_to` valant null signifient « garder ce
 * que Vik a posé » — l'adresse configurée pour toute l'installation —, parce
 * qu'inventer une adresse qui n'existe pas ferait rebondir la réponse du
 * client. Seul le nom affiché, l'objet et le contenu cessent alors de
 * nommer une marque.
 *
 * @param array       $config
 * @param array       $resolution   Sortie de lme_brands_resolve_brand_for_rooms().
 * @param mixed       $booking_lang `sir_vikbooking_orders.lang`, brut.
 * @return array{brand_key: string|null, label: string, sender_email: string|null, sender_name: string, reply_to: string|null, subject: string|null, replacements: array, signature_html: string|null, foreign_tokens: string[]}
 */
function lme_brands_mail_identity( array $config, array $resolution, $booking_lang ) {
	$lang      = lme_brands_normalize_language_tag( $booking_lang );
	$preferred = null === $lang ? array() : array( $lang );

	if ( ! isset( $resolution['status'] ) || 'ok' !== $resolution['status'] ) {
		$neutral = isset( $config['neutral'] ) && is_array( $config['neutral'] ) ? $config['neutral'] : array();

		return array(
			'brand_key'      => null,
			'label'          => isset( $neutral['label'] ) && is_string( $neutral['label'] ) ? $neutral['label'] : '',
			'sender_email'   => ( isset( $neutral['sender_email'] ) && is_string( $neutral['sender_email'] ) && '' !== $neutral['sender_email'] ) ? $neutral['sender_email'] : null,
			'sender_name'    => isset( $neutral['sender_name'] ) && is_string( $neutral['sender_name'] ) ? $neutral['sender_name'] : '',
			'reply_to'       => ( isset( $neutral['reply_to'] ) && is_string( $neutral['reply_to'] ) && '' !== $neutral['reply_to'] ) ? $neutral['reply_to'] : null,
			'subject'        => lme_brands_pick_localized( isset( $neutral['subject'] ) ? $neutral['subject'] : array(), $preferred ),
			'replacements'   => array(),
			'signature_html' => null,
			// Marque inconnue : on ne sait pas ce qui serait « étranger ».
			'foreign_tokens' => array(),
		);
	}

	$brand_key = $resolution['brand_key'];
	$brand     = $config['brands'][ $brand_key ];
	$mail_cfg  = isset( $brand['mail'] ) && is_array( $brand['mail'] ) ? $brand['mail'] : array();

	if ( isset( $brand['languages'] ) && is_array( $brand['languages'] ) ) {
		foreach ( $brand['languages'] as $declared ) {
			$declared = lme_brands_normalize_language_tag( $declared );
			if ( null !== $declared && ! in_array( $declared, $preferred, true ) ) {
				$preferred[] = $declared;
			}
		}
	}

	return array(
		'brand_key'      => $brand_key,
		'label'          => $brand['label'],
		'sender_email'   => $brand['sender_email'],
		'sender_name'    => $brand['sender_name'],
		'reply_to'       => $brand['reply_to'],
		'subject'        => lme_brands_pick_localized( isset( $mail_cfg['subject'] ) ? $mail_cfg['subject'] : array(), $preferred ),
		'replacements'   => ( isset( $mail_cfg['replacements'] ) && is_array( $mail_cfg['replacements'] ) ) ? $mail_cfg['replacements'] : array(),
		'signature_html' => ( isset( $mail_cfg['signature_html'] ) && is_string( $mail_cfg['signature_html'] ) && '' !== $mail_cfg['signature_html'] ) ? $mail_cfg['signature_html'] : null,
		'foreign_tokens' => lme_brands_foreign_brand_tokens( $config, $brand_key ),
	);
}
