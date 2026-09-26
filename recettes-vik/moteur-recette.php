<?php
/**
 * recettes-vik/moteur-recette.php
 *
 * Moteur des recettes d'écriture dans Vik Booking. Ne s'exécute jamais seul :
 * appliquer-recette-vik.sh le transmet par SSH à `wp eval-file -`, précédé
 * d'une ligne qui définit $ICL_RECETTE_B64 (le fichier JSON de la recette).
 *
 * Arguments WP-CLI : MODE ENV_ATTENDU HOTE_ATTENDU
 *   MODE = plan | appliquer | rollback
 *
 * Périmètre, décision de Thomas du 26 septembre 2026
 * (docs/briefs/brief-f3-rappel-avant-sejour.md) : trois tables et rien
 * d'autre, #__vikbooking_condtexts, #__vikbooking_cronjobs (une clé de
 * params) et #__vikbooking_adultsdiff (la colonne value). Ce moteur ne sait
 * écrire nulle part ailleurs : une recette qui demanderait autre chose n'a
 * pas de verbe pour le dire.
 *
 * Toute comparaison se fait ici, côté serveur, sur les valeurs brutes lues
 * par $wpdb. La sortie de WP-CLI est traduite à la volée sur ce site
 * (constat-verification-7.md §1) : le rapport part donc en une seule ligne
 * « RAPPORT <hex du JSON> <sha256 du JSON> », que le script local décode et
 * contrôle avant d'en lire un seul champ. Dans le rapport, un texte n'apparaît
 * que par son SHA-256 ou en hexadécimal.
 *
 * Formats reproduits, établis dans le code de Vik 1.8.15 :
 *   - règles : json_encode() d'une liste d'objets {id, params}, les
 *     identifiants de chambre en chaînes (admin/controller.php:15140 et
 *     :15213, composeRulesParamsFromRequest dans
 *     admin/helpers/conditional_rules.php) ; une liste vide donne « [] » ;
 *   - lastupd : JDate::getInstance()->toSql(), soit l'heure UTC ;
 *   - params des tâches : json_encode() sans option du tableau des
 *     paramètres (admin/helpers/src/mvc/model.php:379, prepareSaveData) :
 *     « é » en é, « / » en \/. Le moteur exige que l'aller-retour
 *     json_encode(json_decode(params)) redonne params à l'octet près avant
 *     de réencoder quoi que ce soit.
 *
 * Traductions de Vik : translateContents() remplace msg par la traduction
 * de même reference_id et de même langue dès qu'elle est non vide
 * (site/helpers/translator.php:536 et :760). Un texte créé ne doit donc
 * hériter d'aucune ligne de #__vikbooking_translations : le moteur refuse
 * de créer tant qu'une traduction non vide pointe vers un identifiant qui
 * n'existe pas, et vérifie après création qu'aucune ne vise les nouveaux.
 */

if ( ! defined( 'WP_CLI' ) || ! isset( $ICL_RECETTE_B64 ) ) {
	exit( 1 );
}

// `wp eval-file` exécute ce code dans une méthode, pas dans la portée
// globale : sans cette ligne, les fonctions ci-dessous ne verraient ni le
// rapport ni les noms de tables.
global $wpdb, $icl_rapport, $T_CT, $T_CJ, $T_AD, $T_TR, $T_RM;

$icl_rapport = array(
	'statut'     => 'erreur',
	'mode'       => isset( $args[0] ) ? $args[0] : '',
	'operations' => array(),
	'messages'   => array(),
);

function icl_fin( $code ) {
	global $icl_rapport;
	$j = json_encode( $icl_rapport, JSON_UNESCAPED_SLASHES );
	echo 'RAPPORT ', bin2hex( $j ), ' ', hash( 'sha256', $j ), "\n";
	exit( $code );
}

function icl_msg( $m ) {
	global $icl_rapport;
	$icl_rapport['messages'][] = $m;
}

function icl_regles( $ids ) {
	$ids = array_map( 'intval', (array) $ids );
	sort( $ids, SORT_NUMERIC );
	if ( ! $ids ) {
		return '[]';
	}
	return json_encode( array( array( 'id' => 'rooms.php', 'params' => array( 'rooms' => array_map( 'strval', $ids ) ) ) ) );
}

function icl_h( $s ) {
	return hash( 'sha256', (string) $s );
}

// ---------------------------------------------------------------- arguments

if ( count( $args ) < 3 ) {
	icl_msg( 'arguments attendus : MODE ENV HOTE' );
	icl_fin( 1 );
}
list( $icl_mode, $icl_env, $icl_hote ) = $args;
if ( ! in_array( $icl_mode, array( 'plan', 'appliquer', 'rollback' ), true ) ) {
	icl_msg( 'mode inconnu' );
	icl_fin( 1 );
}

$icl_recette_json = base64_decode( $ICL_RECETTE_B64, true );
$R = json_decode( (string) $icl_recette_json, true );
if ( ! is_array( $R ) || empty( $R['nom'] ) || ! preg_match( '/^[a-z0-9-]+$/', $R['nom'] ) ) {
	icl_msg( 'recette illisible' );
	icl_fin( 1 );
}
$icl_rapport['recette']        = $R['nom'];
$icl_rapport['recette_sha256'] = icl_h( $icl_recette_json );

// ---------------------------------------------------------------- préalables

$env_reel = (string) wp_get_environment_type();
$icl_rapport['env_hex'] = bin2hex( $env_reel );
if ( $env_reel !== $icl_env ) {
	$icl_rapport['statut'] = 'refus';
	icl_msg( 'environnement du site différent de la cible annoncée' );
	icl_fin( 1 );
}

$home_host = (string) parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
$icl_rapport['home_host_hex'] = bin2hex( $home_host );
if ( preg_replace( '/^www\./', '', $home_host ) !== preg_replace( '/^www\./', '', $icl_hote ) ) {
	$icl_rapport['statut'] = 'refus';
	icl_msg( 'hôte du site (option home) différent de l\'hôte annoncé' );
	icl_fin( 1 );
}

$T_CT = $wpdb->prefix . 'vikbooking_condtexts';
$T_CJ = $wpdb->prefix . 'vikbooking_cronjobs';
$T_AD = $wpdb->prefix . 'vikbooking_adultsdiff';
$T_TR = $wpdb->prefix . 'vikbooking_translations';
$T_RM = $wpdb->prefix . 'vikbooking_rooms';
$icl_rapport['prefixe_hex'] = bin2hex( $wpdb->prefix );

// Une transaction ne couvre que des tables InnoDB : sans elle, « arrêt au
// premier écart » pourrait laisser la moitié d'une recette écrite.
foreach ( array( $T_CT, $T_CJ, $T_AD ) as $t ) {
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $t ) );
	if ( 'InnoDB' !== $engine ) {
		icl_msg( 'table non transactionnelle ou absente : ' . bin2hex( $t ) );
		icl_fin( 1 );
	}
}

// ------------------------------------------------------------ lecture d'état

function icl_ct_par_jeton( $jeton ) {
	global $wpdb, $T_CT;
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$T_CT` WHERE BINARY `token` = %s", $jeton ), ARRAY_A );
}

// Traductions non vides de msg pour un identifiant donné, ou pour des
// identifiants qui n'existent pas (orphelines) quand $id vaut null.
function icl_traductions_non_vides( $id ) {
	global $wpdb, $T_TR, $T_CT;
	if ( null === $id ) {
		$rows = $wpdb->get_results( "SELECT t.id, t.lang, t.reference_id, t.content FROM `$T_TR` t LEFT JOIN `$T_CT` c ON c.id = t.reference_id WHERE t.`table` LIKE '%vikbooking\\_condtexts' AND c.id IS NULL", ARRAY_A );
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.id, t.lang, t.reference_id, t.content FROM `$T_TR` t WHERE t.`table` LIKE '%%vikbooking\\_condtexts' AND t.reference_id = %d", $id ), ARRAY_A );
	}
	$n = 0;
	foreach ( (array) $rows as $r ) {
		$c = json_decode( (string) $r['content'], true );
		if ( ! is_array( $c ) || ( isset( $c['msg'] ) && strlen( (string) $c['msg'] ) > 0 ) ) {
			$n++;
		}
	}
	return $n;
}

/**
 * Évalue la recette contre la base, sans rien écrire. Rend la liste des
 * opérations, chacune avec son état : deja, a_ecrire ou ecart.
 */
function icl_evaluer( $R ) {
	global $wpdb, $T_CT, $T_CJ, $T_AD, $T_RM;
	$ops = array();

	// Chambres citées par la recette : toutes doivent exister.
	foreach ( (array) $R['chambres_requises'] as $id ) {
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$T_RM` WHERE id = %d", $id ) );
		if ( 1 !== $n ) {
			$ops[] = array( 'type' => 'chambre', 'cible' => (string) $id, 'etat' => 'ecart', 'detail' => 'chambre absente' );
		}
	}

	foreach ( (array) $R['regles'] as $o ) {
		$op   = array( 'type' => 'regle', 'cible_hex' => bin2hex( $o['token'] ) );
		$rows = icl_ct_par_jeton( $o['token'] );
		$av   = icl_regles( $o['avant'] );
		$ap   = icl_regles( $o['apres'] );
		$op['avant_hex'] = bin2hex( $av );
		$op['apres_hex'] = bin2hex( $ap );
		if ( 1 !== count( $rows ) ) {
			$op['etat']   = 'ecart';
			$op['detail'] = 'jeton trouvé ' . count( $rows ) . ' fois';
		} else {
			$cur               = (string) $rows[0]['rules'];
			$op['id']          = (int) $rows[0]['id'];
			$op['trouve_hex']  = bin2hex( $cur );
			$op['lastupd_hex'] = bin2hex( (string) $rows[0]['lastupd'] );
			if ( $cur === $ap ) {
				$op['etat'] = 'deja';
			} elseif ( $cur === $av ) {
				$op['etat']    = 'a_ecrire';
				$op['_ecrire'] = array( 'rules' => $ap, '_avant_rules' => $cur, '_avant_lastupd' => $rows[0]['lastupd'] );
			} else {
				$op['etat']   = 'ecart';
				$op['detail'] = 'règle trouvée ni avant ni après';
			}
		}
		$ops[] = $op;
	}

	$creations_a_faire = 0;
	foreach ( (array) $R['textes_crees'] as $o ) {
		$op   = array( 'type' => 'texte', 'cible_hex' => bin2hex( $o['token'] ) );
		$rows = icl_ct_par_jeton( $o['token'] );
		$rg   = icl_regles( $o['chambres'] );
		$op['apres_regles_hex'] = bin2hex( $rg );
		$op['apres_name_hex']   = bin2hex( $o['name'] );
		$op['apres_msg_sha256'] = $o['msg_libre'] ? 'libre' : icl_h( $o['msg'] );
		if ( count( $rows ) > 1 ) {
			$op['etat']   = 'ecart';
			$op['detail'] = 'jeton trouvé ' . count( $rows ) . ' fois';
		} elseif ( 1 === count( $rows ) ) {
			$r                    = $rows[0];
			$op['id']             = (int) $r['id'];
			$op['trouve_msg_sha256'] = $o['msg_libre'] ? 'non lu' : icl_h( $r['msg'] );
			$diff = array();
			if ( (string) $r['name'] !== $o['name'] ) {
				$diff[] = 'name';
			}
			if ( (string) $r['rules'] !== $rg ) {
				$diff[] = 'rules';
			}
			if ( '0' !== (string) $r['debug'] ) {
				$diff[] = 'debug';
			}
			if ( ! $o['msg_libre'] && (string) $r['msg'] !== $o['msg'] ) {
				$diff[] = 'msg';
			}
			if ( icl_traductions_non_vides( (int) $r['id'] ) > 0 ) {
				$diff[] = 'traduction non vide qui masquerait msg';
			}
			if ( $diff ) {
				$op['etat']   = 'ecart';
				$op['detail'] = 'existe déjà avec d\'autres valeurs : ' . implode( ', ', $diff );
			} else {
				$op['etat'] = 'deja';
			}
		} else {
			$op['etat']    = 'a_ecrire';
			$op['_ecrire'] = array( 'name' => $o['name'], 'token' => $o['token'], 'rules' => $rg, 'msg' => $o['msg'], 'debug' => 0 );
			$creations_a_faire++;
		}
		$ops[] = $op;
	}
	if ( $creations_a_faire > 0 ) {
		$orph = icl_traductions_non_vides( null );
		if ( $orph > 0 ) {
			$ops[] = array( 'type' => 'traductions', 'etat' => 'ecart', 'detail' => $orph . ' traduction(s) non vide(s) de condtexts sans texte existant : un texte créé pourrait en hériter' );
		}
		$ops[] = array( 'type' => 'traductions', 'etat' => 'deja', 'detail' => 'aucune traduction non vide de condtexts ne vise un identifiant inexistant' );
	}

	$g    = $R['gabarit'];
	$op   = array( 'type' => 'gabarit', 'cible_hex' => bin2hex( $g['class_file'] . ' / ' . $g['cron_name'] . ' / ' . $g['cle'] ) );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, params FROM `$T_CJ` WHERE BINARY class_file = %s AND BINARY cron_name = %s", $g['class_file'], $g['cron_name'] ), ARRAY_A );
	$op['avant_tpl_sha256'] = $g['sha256_avant'];
	$op['apres_tpl_sha256'] = $g['sha256_apres'];
	if ( 1 !== count( $rows ) ) {
		$op['etat']   = 'ecart';
		$op['detail'] = 'tâche trouvée ' . count( $rows ) . ' fois';
	} else {
		$raw                     = (string) $rows[0]['params'];
		$op['id']                = (int) $rows[0]['id'];
		$op['trouve_params_sha256'] = icl_h( $raw );
		$p = json_decode( $raw, true );
		if ( ! is_array( $p ) || ! array_key_exists( $g['cle'], $p ) || ! is_string( $p[ $g['cle'] ] ) ) {
			$op['etat']   = 'ecart';
			$op['detail'] = 'params illisible ou clé absente';
		} elseif ( json_encode( $p ) !== $raw ) {
			$op['etat']   = 'ecart';
			$op['detail'] = 'params n\'est pas au format json_encode de Vik : réencoder changerait autre chose que la clé';
		} else {
			$tpl = $p[ $g['cle'] ];
			$op['trouve_tpl_sha256'] = icl_h( $tpl );
			if ( icl_h( $tpl ) === $g['sha256_apres'] ) {
				$op['etat'] = 'deja';
			} elseif ( icl_h( $tpl ) === $g['sha256_avant'] ) {
				$new     = $tpl;
				$compte  = array();
				$ok      = true;
				foreach ( $g['remplacements'] as $rp ) {
					$c        = substr_count( $tpl, $rp[0] );
					$compte[] = $c;
					if ( 1 !== $c ) {
						$ok = false;
					}
				}
				foreach ( $g['remplacements'] as $rp ) {
					$new = str_replace( $rp[0], $rp[1], $new );
				}
				$op['occurrences_avant'] = $compte;
				$p2               = $p;
				$p2[ $g['cle'] ]  = $new;
				$raw2             = json_encode( $p2 );
				$autres_intactes  = true;
				$p2d              = json_decode( $raw2, true );
				foreach ( $p as $k => $v ) {
					if ( $k !== $g['cle'] && ( ! array_key_exists( $k, $p2d ) || $p2d[ $k ] !== $v ) ) {
						$autres_intactes = false;
					}
				}
				if ( ! $ok ) {
					$op['etat']   = 'ecart';
					$op['detail'] = 'une chaîne à remplacer n\'est pas présente exactement une fois';
				} elseif ( icl_h( $new ) !== $g['sha256_apres'] ) {
					$op['etat']   = 'ecart';
					$op['detail'] = 'les remplacements ne donnent pas l\'empreinte attendue : ' . icl_h( $new );
				} elseif ( ! $autres_intactes || array_keys( $p2d ) !== array_keys( $p ) ) {
					$op['etat']   = 'ecart';
					$op['detail'] = 'le réencodage toucherait une autre clé que ' . $g['cle'];
				} else {
					$op['etat']                 = 'a_ecrire';
					$op['apres_params_sha256']  = icl_h( $raw2 );
					$op['_ecrire']              = array( 'params' => $raw2, '_avant_params' => $raw );
				}
			} else {
				$op['etat']   = 'ecart';
				$op['detail'] = 'gabarit trouvé ni avant ni après';
			}
		}
	}
	$ops[] = $op;

	foreach ( (array) $R['supplements_adultes'] as $o ) {
		$op   = array( 'type' => 'supplement', 'cible' => 'idroom=' . (int) $o['idroom'] . ' adults=' . (int) $o['adults'], 'avant' => $o['avant'], 'apres' => $o['apres'] );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$T_AD` WHERE idroom = %d AND adults = %d", $o['idroom'], $o['adults'] ), ARRAY_A );
		if ( 1 !== count( $rows ) ) {
			$op['etat']   = 'ecart';
			$op['detail'] = 'ligne trouvée ' . count( $rows ) . ' fois';
		} else {
			$r            = $rows[0];
			$op['id']     = (int) $r['id'];
			$op['trouve'] = (string) $r['value'];
			if ( (string) $r['chdisc'] !== (string) $o['chdisc'] || (string) $r['valpcent'] !== (string) $o['valpcent'] || (string) $r['pernight'] !== (string) $o['pernight'] ) {
				$op['etat']   = 'ecart';
				$op['detail'] = 'chdisc, valpcent ou pernight différent';
			} elseif ( (string) $r['value'] === $o['apres'] ) {
				$op['etat'] = 'deja';
			} elseif ( (string) $r['value'] === $o['avant'] ) {
				$op['etat']    = 'a_ecrire';
				$op['_ecrire'] = array( 'value' => $o['apres'], '_avant_value' => (string) $r['value'] );
			} else {
				$op['etat']   = 'ecart';
				$op['detail'] = 'valeur trouvée ni avant ni après';
			}
		}
		$ops[] = $op;
	}

	return $ops;
}

function icl_publier( $ops ) {
	global $icl_rapport;
	$pub = array();
	$n   = array( 'deja' => 0, 'a_ecrire' => 0, 'ecart' => 0 );
	foreach ( $ops as $op ) {
		$n[ $op['etat'] ]++;
		unset( $op['_ecrire'] );
		$pub[] = $op;
	}
	$icl_rapport['operations'] = $pub;
	$icl_rapport['compte']     = $n;
	return $n;
}

function icl_dossier_sauvegardes( $hote, $nom ) {
	return rtrim( (string) getenv( 'HOME' ), '/' ) . '/.icl-dev-recette-backups/' . $hote . '/' . $nom;
}

function icl_tx_echec( $quoi ) {
	global $wpdb, $icl_rapport;
	$wpdb->query( 'ROLLBACK' );
	$icl_rapport['statut'] = 'erreur';
	icl_msg( 'écriture refusée, transaction annulée, rien n\'est écrit : ' . $quoi );
	icl_fin( 1 );
}

// ====================================================================== plan
// ============================================================== appliquer

if ( 'plan' === $icl_mode || 'appliquer' === $icl_mode ) {
	$ops = icl_evaluer( $R );
	$n   = icl_publier( $ops );

	if ( $n['ecart'] > 0 ) {
		$icl_rapport['statut'] = 'ecart';
		icl_msg( 'au moins un écart : rien n\'est écrit' );
		icl_fin( 2 );
	}
	if ( 'plan' === $icl_mode || 0 === $n['a_ecrire'] ) {
		$icl_rapport['statut'] = 'ok';
		icl_fin( 0 );
	}

	// Sauvegarde des lignes touchées, avant toute écriture. Pour un texte
	// créé, il n'y a pas de ligne avant : le retour arrière le retrouve par
	// son jeton. Le msg d'un texte existant n'est ni lu ni sauvegardé : la
	// recette ne le touche pas, et certains portent un code de porte.
	$lignes = array();
	foreach ( $ops as $op ) {
		if ( 'a_ecrire' !== $op['etat'] ) {
			continue;
		}
		$e = $op['_ecrire'];
		switch ( $op['type'] ) {
			case 'regle':
				$lignes[] = array( 'type' => 'regle', 'id' => $op['id'], 'avant' => array( 'rules' => $e['_avant_rules'], 'lastupd' => $e['_avant_lastupd'] ), 'ecrit' => array( 'rules' => $e['rules'] ) );
				break;
			case 'texte':
				$lignes[] = array( 'type' => 'texte', 'avant' => null, 'ecrit' => array( 'name' => $e['name'], 'token' => $e['token'], 'rules' => $e['rules'], 'msg' => $e['msg'], 'debug' => 0 ) );
				break;
			case 'gabarit':
				$lignes[] = array( 'type' => 'gabarit', 'id' => $op['id'], 'avant' => array( 'params' => $e['_avant_params'] ), 'ecrit' => array( 'params' => $e['params'] ) );
				break;
			case 'supplement':
				$lignes[] = array( 'type' => 'supplement', 'id' => $op['id'], 'avant' => array( 'value' => $e['_avant_value'] ), 'ecrit' => array( 'value' => $e['value'] ) );
				break;
		}
	}
	$dir = icl_dossier_sauvegardes( $icl_hote, $R['nom'] ) . '/' . gmdate( 'Ymd\THis\Z' );
	if ( ! wp_mkdir_p( $dir ) || ! chmod( $dir, 0700 ) ) {
		icl_msg( 'dossier de sauvegarde impossible à créer : rien n\'est écrit' );
		icl_fin( 1 );
	}
	$fichier = $dir . '/lignes.json';
	$contenu = json_encode( array( 'recette' => $R['nom'], 'recette_sha256' => $icl_rapport['recette_sha256'], 'hote' => $icl_hote, 'lignes' => $lignes ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	if ( false === file_put_contents( $fichier, $contenu ) || ! chmod( $fichier, 0600 ) || file_get_contents( $fichier ) !== $contenu ) {
		icl_msg( 'sauvegarde impossible à écrire ou à relire : rien n\'est écrit' );
		icl_fin( 1 );
	}
	$icl_rapport['sauvegarde']        = $fichier;
	$icl_rapport['sauvegarde_sha256'] = icl_h( $contenu );

	// Écriture, tout ou rien. Chaque UPDATE porte l'état avant dans son WHERE :
	// si la ligne a bougé depuis la lecture, il touche zéro ligne et tout
	// s'annule.
	$now = gmdate( 'Y-m-d H:i:s' );
	$icl_rapport['lastupd_ecrit'] = $now;
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		icl_tx_echec( 'START TRANSACTION' );
	}
	foreach ( $ops as $op ) {
		if ( 'a_ecrire' !== $op['etat'] ) {
			continue;
		}
		$e = $op['_ecrire'];
		switch ( $op['type'] ) {
			case 'regle':
				$r = $wpdb->query( $wpdb->prepare( "UPDATE `$T_CT` SET rules = %s, lastupd = %s WHERE id = %d AND BINARY rules = %s", $e['rules'], $now, $op['id'], $e['_avant_rules'] ) );
				if ( 1 !== $r ) {
					icl_tx_echec( 'règle ' . $op['cible_hex'] );
				}
				break;
			case 'texte':
				$r = $wpdb->insert( $T_CT, array( 'name' => $e['name'], 'token' => $e['token'], 'rules' => $e['rules'], 'msg' => $e['msg'], 'lastupd' => $now, 'debug' => 0 ), array( '%s', '%s', '%s', '%s', '%s', '%d' ) );
				if ( 1 !== $r || ! $wpdb->insert_id ) {
					icl_tx_echec( 'texte ' . $op['cible_hex'] );
				}
				break;
			case 'gabarit':
				$r = $wpdb->query( $wpdb->prepare( "UPDATE `$T_CJ` SET params = %s WHERE id = %d AND SHA2(params, 256) = %s", $e['params'], $op['id'], icl_h( $e['_avant_params'] ) ) );
				if ( 1 !== $r ) {
					icl_tx_echec( 'gabarit' );
				}
				break;
			case 'supplement':
				$r = $wpdb->query( $wpdb->prepare( "UPDATE `$T_AD` SET value = %s WHERE id = %d AND value = %s", $e['value'], $op['id'], $e['_avant_value'] ) );
				if ( 1 !== $r ) {
					icl_tx_echec( 'supplément ' . $op['cible'] );
				}
				break;
		}
	}
	if ( false === $wpdb->query( 'COMMIT' ) ) {
		icl_tx_echec( 'COMMIT' );
	}
	$icl_rapport['ecrit'] = $n['a_ecrire'];

	// Relecture : tout doit maintenant valoir « après ».
	$ops2 = icl_evaluer( $R );
	$icl_rapport['avant_ecriture'] = $icl_rapport['operations'];
	$n2   = icl_publier( $ops2 );
	if ( $n2['deja'] !== count( $ops2 ) ) {
		$icl_rapport['statut'] = 'erreur';
		icl_msg( 'relecture après écriture : au moins une opération ne vaut pas « après » — retour arrière possible par --rollback' );
		icl_fin( 1 );
	}
	$icl_rapport['statut'] = 'ok';
	icl_fin( 0 );
}

// ================================================================== rollback

$base = icl_dossier_sauvegardes( $icl_hote, $R['nom'] );
$dirs = is_dir( $base ) ? glob( $base . '/*', GLOB_ONLYDIR ) : array();
sort( $dirs );
if ( ! $dirs ) {
	$icl_rapport['statut'] = 'ok';
	icl_msg( 'aucune sauvegarde pour cette recette sur cet hôte : rien à remettre' );
	icl_fin( 0 );
}
$fichier = end( $dirs ) . '/lignes.json';
$S       = json_decode( (string) @file_get_contents( $fichier ), true );
if ( ! is_array( $S ) || ! isset( $S['lignes'] ) || $S['recette'] !== $R['nom'] || $S['hote'] !== $icl_hote ) {
	icl_msg( 'sauvegarde illisible ou d\'une autre recette : ' . bin2hex( $fichier ) );
	icl_fin( 1 );
}
$icl_rapport['sauvegarde'] = $fichier;

// Chaque ligne n'est remise que si elle vaut encore exactement ce que la
// recette a écrit. Si elle a bougé depuis (Thomas a saisi le code des
// suites, par exemple), c'est un écart : rien n'est remis, on le dit.
$plan = array();
foreach ( $S['lignes'] as $l ) {
	$op = array( 'type' => $l['type'] );
	switch ( $l['type'] ) {
		case 'regle':
			$cur = $wpdb->get_var( $wpdb->prepare( "SELECT rules FROM `$T_CT` WHERE id = %d", $l['id'] ) );
			$op['id']         = $l['id'];
			$op['trouve_hex'] = bin2hex( (string) $cur );
			$op['avant_hex']  = bin2hex( $l['avant']['rules'] );
			if ( $cur === $l['avant']['rules'] ) {
				$op['etat'] = 'deja';
			} elseif ( $cur === $l['ecrit']['rules'] ) {
				$op['etat'] = 'a_ecrire';
			} else {
				$op['etat'] = 'ecart';
				$op['detail'] = 'règle modifiée depuis la recette';
			}
			break;
		case 'texte':
			$rows = icl_ct_par_jeton( $l['ecrit']['token'] );
			$op['cible_hex'] = bin2hex( $l['ecrit']['token'] );
			if ( 0 === count( $rows ) ) {
				$op['etat'] = 'deja';
			} elseif ( 1 === count( $rows ) && (string) $rows[0]['name'] === $l['ecrit']['name'] && (string) $rows[0]['rules'] === $l['ecrit']['rules'] && (string) $rows[0]['msg'] === $l['ecrit']['msg'] && '0' === (string) $rows[0]['debug'] ) {
				$op['etat'] = 'a_ecrire';
				$op['id']   = (int) $rows[0]['id'];
			} else {
				$op['etat']   = 'ecart';
				$op['detail'] = 'texte modifié depuis sa création (pour le code des suites : le vider dans Vik avant le retour arrière)';
			}
			break;
		case 'gabarit':
			$cur = (string) $wpdb->get_var( $wpdb->prepare( "SELECT params FROM `$T_CJ` WHERE id = %d", $l['id'] ) );
			$op['id']                   = $l['id'];
			$op['trouve_params_sha256'] = icl_h( $cur );
			$op['avant_params_sha256']  = icl_h( $l['avant']['params'] );
			if ( $cur === $l['avant']['params'] ) {
				$op['etat'] = 'deja';
			} elseif ( $cur === $l['ecrit']['params'] ) {
				$op['etat'] = 'a_ecrire';
			} else {
				$op['etat']   = 'ecart';
				$op['detail'] = 'params modifié depuis la recette';
			}
			break;
		case 'supplement':
			$cur = (string) $wpdb->get_var( $wpdb->prepare( "SELECT value FROM `$T_AD` WHERE id = %d", $l['id'] ) );
			$op['id']     = $l['id'];
			$op['trouve'] = $cur;
			$op['avant']  = $l['avant']['value'];
			if ( $cur === $l['avant']['value'] ) {
				$op['etat'] = 'deja';
			} elseif ( $cur === $l['ecrit']['value'] ) {
				$op['etat'] = 'a_ecrire';
			} else {
				$op['etat']   = 'ecart';
				$op['detail'] = 'valeur modifiée depuis la recette';
			}
			break;
		default:
			$op['etat']   = 'ecart';
			$op['detail'] = 'type inconnu dans la sauvegarde';
	}
	$op['_l'] = $l;
	$plan[]   = $op;
}
$pub = array();
$n   = array( 'deja' => 0, 'a_ecrire' => 0, 'ecart' => 0 );
foreach ( $plan as $op ) {
	$n[ $op['etat'] ]++;
	$q = $op;
	unset( $q['_l'] );
	$pub[] = $q;
}
$icl_rapport['operations'] = $pub;
$icl_rapport['compte']     = $n;
if ( $n['ecart'] > 0 ) {
	$icl_rapport['statut'] = 'ecart';
	icl_msg( 'au moins un écart : rien n\'est remis' );
	icl_fin( 2 );
}
if ( 0 === $n['a_ecrire'] ) {
	$icl_rapport['statut'] = 'ok';
	icl_msg( 'tout vaut déjà l\'état d\'avant la recette' );
	icl_fin( 0 );
}
if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
	icl_tx_echec( 'START TRANSACTION' );
}
foreach ( $plan as $op ) {
	if ( 'a_ecrire' !== $op['etat'] ) {
		continue;
	}
	$l = $op['_l'];
	switch ( $l['type'] ) {
		case 'regle':
			$r = $wpdb->query( $wpdb->prepare( "UPDATE `$T_CT` SET rules = %s, lastupd = %s WHERE id = %d AND BINARY rules = %s", $l['avant']['rules'], $l['avant']['lastupd'], $l['id'], $l['ecrit']['rules'] ) );
			break;
		case 'texte':
			$r = $wpdb->query( $wpdb->prepare( "DELETE FROM `$T_CT` WHERE id = %d AND BINARY token = %s", $op['id'], $l['ecrit']['token'] ) );
			break;
		case 'gabarit':
			$r = $wpdb->query( $wpdb->prepare( "UPDATE `$T_CJ` SET params = %s WHERE id = %d AND SHA2(params, 256) = %s", $l['avant']['params'], $l['id'], icl_h( $l['ecrit']['params'] ) ) );
			break;
		case 'supplement':
			$r = $wpdb->query( $wpdb->prepare( "UPDATE `$T_AD` SET value = %s WHERE id = %d AND value = %s", $l['avant']['value'], $l['id'], $l['ecrit']['value'] ) );
			break;
	}
	if ( 1 !== $r ) {
		icl_tx_echec( 'retour arrière, ligne ' . $l['type'] );
	}
}
if ( false === $wpdb->query( 'COMMIT' ) ) {
	icl_tx_echec( 'COMMIT' );
}
$icl_rapport['ecrit'] = $n['a_ecrire'];

// Relecture : chaque ligne remise doit valoir exactement son état d'avant.
$restes = 0;
foreach ( $plan as $op ) {
	if ( 'a_ecrire' !== $op['etat'] ) {
		continue;
	}
	$l = $op['_l'];
	switch ( $l['type'] ) {
		case 'regle':
			$ok = $wpdb->get_var( $wpdb->prepare( "SELECT rules FROM `$T_CT` WHERE id = %d", $l['id'] ) ) === $l['avant']['rules'];
			break;
		case 'texte':
			$ok = 0 === count( icl_ct_par_jeton( $l['ecrit']['token'] ) );
			break;
		case 'gabarit':
			$ok = $wpdb->get_var( $wpdb->prepare( "SELECT params FROM `$T_CJ` WHERE id = %d", $l['id'] ) ) === $l['avant']['params'];
			break;
		case 'supplement':
			$ok = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM `$T_AD` WHERE id = %d", $l['id'] ) ) === $l['avant']['value'];
			break;
		default:
			$ok = false;
	}
	if ( ! $ok ) {
		$restes++;
	}
}
if ( $restes > 0 ) {
	$icl_rapport['statut'] = 'erreur';
	icl_msg( 'relecture après retour arrière : ' . $restes . ' ligne(s) ne valent pas leur état d\'avant' );
	icl_fin( 1 );
}
$icl_rapport['statut'] = 'ok';
icl_fin( 0 );
