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
	}

	if ( ! isset( $config['rooms'] ) || ! is_array( $config['rooms'] ) ) {
		$errors[]         = "Le registre ne définit aucune chambre (clé 'rooms' manquante ou invalide).";
		$config['rooms'] = array();
	}

	$room_ids = array();

	foreach ( $config['rooms'] as $room_id => $room ) {
		if ( ! is_int( $room_id ) && ! ( is_string( $room_id ) && ctype_digit( $room_id ) ) ) {
			$errors[] = 'Une chambre a une clé invalide : ' . var_export( $room_id, true ) . '.';
			continue;
		}

		$room_id    = (int) $room_id;
		$room_ids[] = $room_id;

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

	$excluded = isset( $config['excluded_room_ids'] ) ? $config['excluded_room_ids'] : array();

	if ( ! is_array( $excluded ) ) {
		$errors[]  = "'excluded_room_ids' doit être un tableau.";
		$excluded = array();
	}

	$seen_excluded = array();

	foreach ( $excluded as $excluded_id ) {
		if ( ! is_int( $excluded_id ) ) {
			$errors[] = "'excluded_room_ids' contient une valeur non entière : " . var_export( $excluded_id, true ) . '.';
			continue;
		}

		if ( isset( $seen_excluded[ $excluded_id ] ) ) {
			$errors[] = "'excluded_room_ids' contient un doublon : #{$excluded_id}.";
		}
		$seen_excluded[ $excluded_id ] = true;

		if ( in_array( $excluded_id, $room_ids, true ) ) {
			$errors[] = "La chambre #{$excluded_id} figure à la fois dans 'rooms' et dans 'excluded_room_ids'.";
		}
	}

	return $errors;
}

/**
 * Résout la marque d'une chambre à partir de son identifiant Vik.
 *
 * Trois issues possibles, jamais une quatrième devinée :
 *   - 'ok'       : la chambre est vendue, sa marque est connue.
 *   - 'excluded' : chambre de test, volontairement hors registre de vente.
 *   - 'unknown'  : chambre absente du registre. Chapitre 6 : toujours une
 *                  erreur journalisée par l'appelant, jamais un cas par défaut.
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

	$excluded = isset( $config['excluded_room_ids'] ) && is_array( $config['excluded_room_ids'] )
		? $config['excluded_room_ids']
		: array();

	if ( in_array( $room_id, $excluded, true ) ) {
		return array(
			'status'  => 'excluded',
			'room_id' => $room_id,
		);
	}

	return array(
		'status'  => 'unknown',
		'room_id' => $room_id,
	);
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
