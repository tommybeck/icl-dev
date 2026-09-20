<?php
/**
 * Tests unitaires purs pour includes/core.php.
 *
 * Ne nécessitent ni WordPress ni PHPUnit, volontairement : `core.php` ne
 * touche à rien d'externe, donc `php tests/test-core.php` suffit à le
 * vérifier de bout en bout, y compris depuis un poste sans site WordPress
 * installé.
 *
 * Exécution :
 *     php mu-plugins/lme-brands/tests/test-core.php
 *
 * Sortie : une ligne par test, un total, et un code de sortie non nul si un
 * test échoue (exploitable dans une intégration continue).
 */

require_once __DIR__ . '/../includes/core.php';

$GLOBALS['lme_brands_test_count']    = 0;
$GLOBALS['lme_brands_test_failures'] = 0;

/**
 * @param bool   $condition
 * @param string $label
 */
function lme_brands_test_assert( $condition, $label ) {
	++$GLOBALS['lme_brands_test_count'];

	if ( $condition ) {
		echo "  ok  - {$label}\n";
		return;
	}

	echo "FAIL  - {$label}\n";
	++$GLOBALS['lme_brands_test_failures'];
}

/**
 * @return array Un registre minimal, valide, structurellement fidèle à la
 *               carte de vérité du brief (chapitre 2), sans dépendre du
 *               contenu réel de config/brands.php.
 */
function lme_brands_test_sample_config() {
	return array(
		'neutral' => array(
			'label'        => 'Réservations',
			'sender_name'  => 'Réservations',
			'sender_email' => null,
			'reply_to'     => null,
			'subject'      => array(
				'fr' => 'Votre réservation',
				'en' => 'Your reservation',
			),
		),
		'brands' => array(
			'linstantcle' => array(
				'label'                => "L'Instant Clé",
				'host'                 => 'linstantcle.ch',
				'sender_email'         => 'reservations@linstantcle.ch',
				'sender_name'          => "L'Instant Clé",
				'reply_to'             => 'reservations@linstantcle.ch',
				'signature'            => "L'équipe L'Instant Clé",
				'confirmation_page_id' => 12,
				'languages'            => array( 'en', 'fr' ),
				'mail'                 => array(
					'subject'        => array(),
					'replacements'   => array(),
					'signature_html' => null,
				),
			),
			'sexcaperoom' => array(
				'label'                => 'Sexcape Room',
				'host'                 => 'reservation.sexcaperoom.ch',
				'sender_email'         => 'reservations@sexcaperoom.ch',
				'sender_name'          => 'Sexcape Room',
				'reply_to'             => 'reservations@sexcaperoom.ch',
				'signature'            => "L'équipe Sexcape Room",
				'confirmation_page_id' => 34,
				'languages'            => array( 'fr' ),
				'mail'                 => array(
					'subject'        => array(
						'fr' => 'Votre réservation — Sexcape Room',
					),
					'replacements'   => array(),
					'signature_html' => null,
				),
			),
		),
		'rooms' => array(
			1  => array(
				'brand'              => 'linstantcle',
				'name'               => "L'Entracte",
				'experience'         => "L'Entracte",
				'forfait'            => null,
				'availability_group' => 'villa-entracte',
			),
			7  => array(
				'brand'              => 'linstantcle',
				'name'               => "L'Entracte all inclusive",
				'experience'         => "L'Entracte",
				'forfait'            => 'all inclusive',
				'availability_group' => 'villa-entracte',
			),
			10 => array(
				'brand'              => 'sexcaperoom',
				'name'               => 'À Huis Clos',
				'experience'         => 'À Huis Clos',
				'forfait'            => null,
				'availability_group' => 'villa-entracte',
			),
			2  => array(
				'brand'              => 'linstantcle',
				'name'               => "L'Aparté",
				'experience'         => "L'Aparté",
				'forfait'            => null,
				'availability_group' => 'villa-aparte',
			),
			4  => array(
				'brand'              => 'sexcaperoom',
				'name'               => 'Le Boudoir du Désir',
				'experience'         => 'Le Boudoir du Désir',
				'forfait'            => null,
				'availability_group' => 'villa-aparte',
			),
			8  => array(
				'brand'              => 'linstantcle',
				'name'               => 'La Parenthèse',
				'experience'         => 'La Parenthèse',
				'forfait'            => null,
				'availability_group' => null,
			),
			9  => array(
				'brand'              => 'sexcaperoom',
				'name'               => "L'Indécent",
				'experience'         => "L'Indécent",
				'forfait'            => null,
				'availability_group' => null,
			),
			5  => array(
				'brand'              => 'linstantcle',
				'name'               => 'Chambre de test (clone Cinéma)',
				'experience'         => 'Chambre de test',
				'forfait'            => null,
				'availability_group' => null,
			),
			6  => array(
				'brand'              => 'sexcaperoom',
				'name'               => 'Chambre de test (clone Maisonnette)',
				'experience'         => 'Chambre de test',
				'forfait'            => null,
				'availability_group' => null,
			),
		),
	);
}

// --- lme_brands_validate_config --------------------------------------------

$config = lme_brands_test_sample_config();
lme_brands_test_assert( array() === lme_brands_validate_config( $config ), 'validate_config : le registre de test est valide' );

$broken = $config;
unset( $broken['brands'] );
lme_brands_test_assert( count( lme_brands_validate_config( $broken ) ) > 0, 'validate_config : signale un registre sans marques' );

$broken                = $config;
$broken['rooms'][99]   = array(
	'brand'      => 'marque-inexistante',
	'name'       => 'X',
	'experience' => 'X',
);
lme_brands_test_assert( count( lme_brands_validate_config( $broken ) ) > 0, 'validate_config : signale une chambre pointant vers une marque inconnue' );

$broken                                         = $config;
$broken['brands']['sexcaperoom']['host']        = 'linstantcle.ch'; // doublon d'hôte.
lme_brands_test_assert( count( lme_brands_validate_config( $broken ) ) > 0, 'validate_config : signale deux marques sur le même hôte' );

$broken = $config;
unset( $broken['rooms'][7]['name'] );
lme_brands_test_assert( count( lme_brands_validate_config( $broken ) ) > 0, 'validate_config : signale une chambre sans nom' );

// --- lme_brands_resolve_room -------------------------------------------------

$resolved = lme_brands_resolve_room( $config, 10 );
lme_brands_test_assert(
	'ok' === $resolved['status'] && 'sexcaperoom' === $resolved['brand_key'],
	'resolve_room : la chambre 10 résout vers sexcaperoom'
);

$resolved = lme_brands_resolve_room( $config, 7 );
lme_brands_test_assert(
	'ok' === $resolved['status']
		&& "L'Entracte" === $resolved['experience']
		&& 'all inclusive' === $resolved['forfait']
		&& "L'Entracte all inclusive" === $resolved['name'],
	"resolve_room : la chambre 7 partage l'expérience de la chambre 1 avec le forfait all inclusive"
);

$resolved = lme_brands_resolve_room( $config, 1 );
lme_brands_test_assert( null === $resolved['forfait'], "resolve_room : la chambre 1 n'a pas de forfait" );

$resolved = lme_brands_resolve_room( $config, 5 );
lme_brands_test_assert(
	'ok' === $resolved['status'] && 'linstantcle' === $resolved['brand_key'],
	"resolve_room : la chambre de test 5 résout vers linstantcle, comme n'importe quelle chambre déclarée"
);

$resolved = lme_brands_resolve_room( $config, 6 );
lme_brands_test_assert(
	'ok' === $resolved['status'] && 'sexcaperoom' === $resolved['brand_key'],
	"resolve_room : la chambre de test 6 résout vers sexcaperoom, comme n'importe quelle chambre déclarée"
);

$resolved = lme_brands_resolve_room( $config, 3 );
lme_brands_test_assert( 'unknown' === $resolved['status'], 'resolve_room : une chambre absente du registre est inconnue' );

$resolved = lme_brands_resolve_room( $config, '10' );
lme_brands_test_assert( 'ok' === $resolved['status'], "resolve_room : accepte un identifiant de chambre passé en chaîne" );

$broken_brand_ref               = $config;
$broken_brand_ref['rooms'][1]['brand'] = 'marque-inexistante';
$resolved                       = lme_brands_resolve_room( $broken_brand_ref, 1 );
lme_brands_test_assert(
	'unknown' === $resolved['status'],
	'resolve_room : une chambre qui pointe vers une marque absente du registre est traitée comme inconnue, jamais devinée'
);

// --- lme_brands_resolve_brand_by_host ----------------------------------------

lme_brands_test_assert(
	'sexcaperoom' === lme_brands_resolve_brand_by_host( $config, 'reservation.sexcaperoom.ch' ),
	"resolve_brand_by_host : reconnaît l'hôte Sexcape Room"
);
lme_brands_test_assert(
	'sexcaperoom' === lme_brands_resolve_brand_by_host( $config, 'RESERVATION.SEXCAPEROOM.CH' ),
	'resolve_brand_by_host : insensible à la casse'
);
lme_brands_test_assert(
	null === lme_brands_resolve_brand_by_host( $config, 'staging10.linstantcle.ch' ),
	"resolve_brand_by_host : un hôte inconnu ne résout vers aucune marque"
);
lme_brands_test_assert(
	null === lme_brands_resolve_brand_by_host( $config, '' ),
	'resolve_brand_by_host : une chaîne vide ne résout vers aucune marque'
);

// --- lme_brands_swap_url_host -------------------------------------------------

lme_brands_test_assert(
	'https://reservation.sexcaperoom.ch/wp-content/uploads/x.jpg'
		=== lme_brands_swap_url_host( 'https://linstantcle.ch/wp-content/uploads/x.jpg', 'reservation.sexcaperoom.ch' ),
	"swap_url_host : remplace l'hôte en conservant schéma et chemin"
);
lme_brands_test_assert(
	'https://reservation.sexcaperoom.ch/x?a=1#frag'
		=== lme_brands_swap_url_host( 'https://linstantcle.ch/x?a=1#frag', 'reservation.sexcaperoom.ch' ),
	'swap_url_host : conserve la requête et le fragment'
);
lme_brands_test_assert(
	'https://reservation.sexcaperoom.ch/x'
		=== lme_brands_swap_url_host( 'https://reservation.sexcaperoom.ch/x', 'reservation.sexcaperoom.ch' ),
	"swap_url_host : ne change rien si l'hôte est déjà le bon"
);
lme_brands_test_assert(
	'' === lme_brands_swap_url_host( '', 'reservation.sexcaperoom.ch' ),
	'swap_url_host : une URL vide reste vide'
);
lme_brands_test_assert(
	'not a url' === lme_brands_swap_url_host( 'not a url', 'reservation.sexcaperoom.ch' ),
	"swap_url_host : une chaîne sans hôte n'est pas modifiée"
);
lme_brands_test_assert(
	'https://reservation.sexcaperoom.ch:8443/x'
		=== lme_brands_swap_url_host( 'https://linstantcle.ch:8443/x', 'reservation.sexcaperoom.ch' ),
	'swap_url_host : conserve un port explicite'
);

// --- Chantier B8 : levier de préproduction -------------------------------------

echo "\nlme_brands_resolve_effective_http_host()\n";

lme_brands_test_assert(
	array( 'host' => 'reservation.sexcaperoom.ch', 'override_used' => true )
		=== lme_brands_resolve_effective_http_host( 'staging10.linstantcle.ch', 'staging', 'reservation.sexcaperoom.ch' ),
	"resolve_effective_http_host : en préproduction, la constante définie force l'hôte"
);

lme_brands_test_assert(
	array( 'host' => 'staging10.linstantcle.ch', 'override_used' => false )
		=== lme_brands_resolve_effective_http_host( 'staging10.linstantcle.ch', 'staging', null ),
	"resolve_effective_http_host : en préproduction sans constante définie, l'hôte de la requête est gardé"
);

lme_brands_test_assert(
	array( 'host' => 'staging10.linstantcle.ch', 'override_used' => false )
		=== lme_brands_resolve_effective_http_host( 'staging10.linstantcle.ch', 'staging', '' ),
	"resolve_effective_http_host : une constante vide est traitée comme absente, jamais comme un hôte vide"
);

foreach ( array( 'production', 'local', 'development', '' ) as $other_environment ) {
	lme_brands_test_assert(
		array( 'host' => 'linstantcle.ch', 'override_used' => false )
			=== lme_brands_resolve_effective_http_host( 'linstantcle.ch', $other_environment, 'reservation.sexcaperoom.ch' ),
		"resolve_effective_http_host : la constante n'a aucun effet hors de l'environnement 'staging' (ici '{$other_environment}')"
	);
}

lme_brands_test_assert(
	array( 'host' => 'reservation.sexcaperoom.ch', 'override_used' => true )
		=== lme_brands_resolve_effective_http_host( null, 'staging', '  RESERVATION.SEXCAPEROOM.CH:8443  ' ),
	'resolve_effective_http_host : la constante est ramenée en minuscules, sans port ni espaces de bord, même sans hôte de requête'
);

lme_brands_test_assert(
	array( 'host' => null, 'override_used' => false )
		=== lme_brands_resolve_effective_http_host( null, 'staging', null ),
	"resolve_effective_http_host : sans hôte de requête ni constante, il n'y a toujours aucun hôte"
);

// --- Non-régression 3 : le levier ne dispense jamais de connaître le registre --
//
// Le levier ne fait que choisir l'hôte transmis à
// lme_brands_resolve_brand_by_host() ; il ne doit rien changer à cette
// résolution elle-même. Un hôte de substitution qui n'est celui d'aucune
// marque continue de ne résoudre aucune marque, exactement comme un hôte de
// requête ordinaire.

$override_outcome = lme_brands_resolve_effective_http_host( 'staging10.linstantcle.ch', 'staging', 'hote-inconnu.example.ch' );
lme_brands_test_assert(
	true === $override_outcome['override_used']
		&& null === lme_brands_resolve_brand_by_host( $config, $override_outcome['host'] ),
	"non-régression : le levier de préproduction n'invente jamais une marque pour un hôte de substitution inconnu du registre"
);

// --- lme_brands_rate_limit_gate ------------------------------------------------

$state = array();
$gate1 = lme_brands_rate_limit_gate( $state, 'unknown_room', 1000, 900 );
lme_brands_test_assert( true === $gate1['should_alert'], 'rate_limit_gate : premier événement toujours alerté' );

$gate2 = lme_brands_rate_limit_gate( $gate1['state'], 'unknown_room', 1500, 900 );
lme_brands_test_assert( false === $gate2['should_alert'], 'rate_limit_gate : un second événement dans la fenêtre est absorbé' );

$gate3 = lme_brands_rate_limit_gate( $gate1['state'], 'unknown_room', 2000, 900 );
lme_brands_test_assert( true === $gate3['should_alert'], 'rate_limit_gate : un événement après la fenêtre alerte à nouveau' );

$gate4 = lme_brands_rate_limit_gate( $gate1['state'], 'autre_code', 1100, 900 );
lme_brands_test_assert( true === $gate4['should_alert'], "rate_limit_gate : un autre code n'est pas affecté par la limitation" );

// --- lme_brands_parse_id_list ------------------------------------------------

lme_brands_test_assert(
	array( 1, 7, 10 ) === lme_brands_parse_id_list( '1,7,10' ),
	'parse_id_list : découpe une chaîne délimitée par des virgules'
);
lme_brands_test_assert(
	array( 1, 7, 10 ) === lme_brands_parse_id_list( '1;7;10' ),
	'parse_id_list : découpe une chaîne délimitée par des points-virgules'
);
lme_brands_test_assert(
	array( 2, 4 ) === lme_brands_parse_id_list( array( '2', '4' ) ),
	'parse_id_list : accepte un tableau (soumission room_ids[])'
);
lme_brands_test_assert(
	array() === lme_brands_parse_id_list( '' ),
	'parse_id_list : une chaîne vide donne une liste vide'
);
lme_brands_test_assert(
	array( 4 ) === lme_brands_parse_id_list( '4,abc,-1' ),
	'parse_id_list : ignore silencieusement les jetons non entiers'
);

// --- lme_brands_extract_room_ids ----------------------------------------------

lme_brands_test_assert(
	array( 10 ) === lme_brands_extract_room_ids( array( array( 'id' => 10, 'name' => 'x' ) ) ),
	'extract_room_ids : lit la clé id de chaque chambre réservée'
);
lme_brands_test_assert(
	array( 1, 2 ) === lme_brands_extract_room_ids( array( array( 'id' => 1 ), array( 'id' => 2 ), array( 'id' => 1 ) ) ),
	'extract_room_ids : dédoublonne'
);
lme_brands_test_assert(
	array() === lme_brands_extract_room_ids( array() ),
	'extract_room_ids : un tableau vide donne une liste vide'
);
lme_brands_test_assert(
	array() === lme_brands_extract_room_ids( null ),
	"extract_room_ids : une valeur qui n'est pas un tableau donne une liste vide, jamais une erreur"
);
$room_object       = new stdClass();
$room_object->id   = 4;
lme_brands_test_assert(
	array( 4 ) === lme_brands_extract_room_ids( array( $room_object ) ),
	'extract_room_ids : accepte aussi un objet avec une propriété id'
);

// --- lme_brands_room_ids_matching_category -------------------------------------
//
// Carte alignée sur la production, relevée le 14 septembre 2026
// (brief-correctif-phase-2-filtrage.md, chapitre 8) : la catégorie 1 contient
// aussi la chambre 5, celle qui déclenchait le défaut du chapitre 2 — un
// jeu d'essai qui ne la contenait pas ne pouvait pas l'attraper.

$category_tokens = array(
	1 => array( '1' ),  // L'Entracte
	2 => array( '1' ),  // L'Aparté
	5 => array( '1' ),  // chambre de test, clone Cinéma
	7 => array( '1' ),  // L'Entracte all inclusive
	4 => array( '3' ),  // Le Boudoir du Désir
	9 => array( '3' ),  // L'Indécent
	8 => array( '4' ),  // La Parenthèse
	// 6 et 10 : aucune catégorie, absentes de la carte (idcat vide dans Vik).
);
lme_brands_test_assert(
	array( 1, 2, 5, 7 ) === lme_brands_room_ids_matching_category( $category_tokens, 1 ),
	'room_ids_matching_category : la catégorie 1 retrouve les chambres 1, 2, 5 et 7, y compris la chambre de test'
);
lme_brands_test_assert(
	array( 4, 9 ) === lme_brands_room_ids_matching_category( $category_tokens, 3 ),
	'room_ids_matching_category : la catégorie 3 retrouve les chambres 4 et 9'
);
lme_brands_test_assert(
	array() === lme_brands_room_ids_matching_category( $category_tokens, 99 ),
	"room_ids_matching_category : une catégorie sans chambre connue donne une liste vide"
);
lme_brands_test_assert(
	array() === lme_brands_room_ids_matching_category( array(), 1 ),
	'room_ids_matching_category : une carte vide donne une liste vide'
);

// --- lme_brands_evaluate_booking_room -------------------------------------------

$ok_linstantcle = lme_brands_resolve_room( $config, 1 ); // brand_key 'linstantcle'.

lme_brands_test_assert(
	'allow' === lme_brands_evaluate_booking_room( $ok_linstantcle, true, 'linstantcle' ),
	'evaluate_booking_room : chambre active, marque de l\'hôte : autorisée'
);
lme_brands_test_assert(
	'refuse_unavailable' === lme_brands_evaluate_booking_room( $ok_linstantcle, false, 'linstantcle' ),
	"evaluate_booking_room : une chambre à avail = 0 est refusée même quand sa marque correspond à l'hôte"
);
lme_brands_test_assert(
	'refuse_foreign_brand' === lme_brands_evaluate_booking_room( $ok_linstantcle, true, 'sexcaperoom' ),
	"evaluate_booking_room : chambre active, mais d'une autre marque que l'hôte : refusée"
);
lme_brands_test_assert(
	'refuse_unknown_room' === lme_brands_evaluate_booking_room( lme_brands_resolve_room( $config, 3 ), true, 'linstantcle' ),
	'evaluate_booking_room : une chambre absente du registre est refusée'
);
lme_brands_test_assert(
	'refuse_unavailable' === lme_brands_evaluate_booking_room( lme_brands_resolve_room( $config, 3 ), false, 'linstantcle' ),
	"evaluate_booking_room : l'indisponibilité est vérifiée avant même la résolution de la chambre"
);

// --- Le registre réel du dépôt est valide --------------------------------------

$real_config = require __DIR__ . '/../config/brands.php';
$real_errors = lme_brands_validate_config( $real_config );

if ( array() !== $real_errors ) {
	foreach ( $real_errors as $error ) {
		echo "      > {$error}\n";
	}
}
lme_brands_test_assert( array() === $real_errors, 'validate_config : le registre réel config/brands.php est valide' );

$expected_registry_rooms = array( 1, 2, 4, 5, 6, 7, 8, 9, 10 );
$actual_registry_rooms   = array_map( 'intval', array_keys( $real_config['rooms'] ) );
lme_brands_test_assert(
	array() === array_diff( $expected_registry_rooms, $actual_registry_rooms )
		&& array() === array_diff( $actual_registry_rooms, $expected_registry_rooms ),
	'config réel : les sept chambres vendues et les deux chambres de test sont exactement celles du registre (chapitre 2 : plus de statut exclu)'
);
lme_brands_test_assert(
	'linstantcle' === $real_config['rooms'][1]['brand'] && 'sexcaperoom' === $real_config['rooms'][10]['brand'],
	'config réel : la chambre 1 est L\'Instant Clé, la chambre 10 est Sexcape Room'
);
lme_brands_test_assert(
	'linstantcle' === $real_config['rooms'][5]['brand'] && 'sexcaperoom' === $real_config['rooms'][6]['brand'],
	'config réel : la chambre de test 5 est L\'Instant Clé, la chambre de test 6 est Sexcape Room (affectation délibérée, chapitre 4)'
);

// --- Non-régression 1 : la catégorie 1 n'est plus retirée sur l'hôte L'Instant Clé --
//
// Carte de catégories réelle (sir_vikbooking_rooms.idcat, relevée le
// 14 septembre 2026). C'est le défaut du chapitre 2 : avant que la chambre 5
// n'entre dans le registre, elle résolvait en 'excluded', jamais 'ok', ce qui
// faisait retirer le paramètre category_id=1 même sur l'hôte L'Instant Clé,
// alors que la catégorie 1 lui appartient entièrement.

$real_category1_tokens = array(
	1 => array( '1' ),
	2 => array( '1' ),
	5 => array( '1' ),
	7 => array( '1' ),
);
$category1_room_ids   = lme_brands_room_ids_matching_category( $real_category1_tokens, 1 );
$category1_all_ok_lic = true;

foreach ( $category1_room_ids as $room_id ) {
	$resolved = lme_brands_resolve_room( $real_config, $room_id );
	if ( 'ok' !== $resolved['status'] || 'linstantcle' !== $resolved['brand_key'] ) {
		$category1_all_ok_lic = false;
		break;
	}
}

lme_brands_test_assert(
	array( 1, 2, 5, 7 ) === $category1_room_ids && $category1_all_ok_lic,
	"non-régression : sur l'hôte L'Instant Clé, aucune chambre de la catégorie 1 (dont la 5) n'est étrangère à la marque — category_id n'est donc pas retiré"
);

// --- Non-régression 2 : une chambre à avail = 0 est refusée par la garde ------------

$real_test_room_5 = lme_brands_resolve_room( $real_config, 5 );

lme_brands_test_assert(
	'refuse_unavailable' === lme_brands_evaluate_booking_room( $real_test_room_5, false, 'linstantcle' ),
	'non-régression : la chambre de test 5, à avail = 0, est refusée par la garde même sur son propre hôte de marque'
);

// --- Phase 3 : destinataire visé par une itération d'envoi -----------------------

echo "\nlme_brands_mail_audience()\n";

lme_brands_test_assert(
	'guest' === lme_brands_mail_audience( 'guest' ),
	"'guest' vise le client — le seul cas que les quinze appels de sendBookingEmail() produisent"
);

lme_brands_test_assert(
	'guest' === lme_brands_mail_audience( 'customer' ),
	"'customer' vise le client aussi : Vik teste guest OU customer (lib.vikbooking.php:6414)"
);

lme_brands_test_assert(
	'admin' === lme_brands_mail_audience( 'admin' ),
	"'admin' vise l'administrateur, hors périmètre de la réécriture"
);

lme_brands_test_assert(
	'custom' === lme_brands_mail_audience( 'guest@example.com' ),
	"une adresse littérale contenant « guest » est un destinataire personnalisé : l'ordre des tests de Vik est respecté"
);

lme_brands_test_assert(
	'none' === lme_brands_mail_audience( '' ) && 'none' === lme_brands_mail_audience( null ),
	'une valeur vide ou non textuelle ne vise personne'
);

// --- Phase 3 : résolution de marque depuis les chambres d'une réservation --------

echo "\nlme_brands_resolve_brand_for_rooms()\n";

$config = lme_brands_test_sample_config();

$one_room = lme_brands_resolve_brand_for_rooms( $config, array( 4 ) );
lme_brands_test_assert(
	'ok' === $one_room['status'] && 'sexcaperoom' === $one_room['brand_key'],
	'une réservation de la seule chambre 4 résout vers Sexcape Room'
);

$same_brand = lme_brands_resolve_brand_for_rooms( $config, array( 1, 7 ) );
lme_brands_test_assert(
	'ok' === $same_brand['status'] && 'linstantcle' === $same_brand['brand_key'],
	'deux chambres de la même marque résolvent vers cette marque'
);

$duplicated = lme_brands_resolve_brand_for_rooms( $config, array( 4, 4, '4' ) );
lme_brands_test_assert(
	'ok' === $duplicated['status'] && array( 4 ) === $duplicated['room_ids'],
	'les identifiants répétés, entiers ou textuels, sont dédoublonnés'
);

$mixed = lme_brands_resolve_brand_for_rooms( $config, array( 1, 4 ) );
lme_brands_test_assert(
	'undetermined' === $mixed['status'] && 'mixed_brands' === $mixed['reason'],
	'une réservation qui mêle deux marques est indéterminée, jamais rattachée à la première chambre venue'
);

$unknown = lme_brands_resolve_brand_for_rooms( $config, array( 3 ) );
lme_brands_test_assert(
	'undetermined' === $unknown['status'] && 'unknown_room' === $unknown['reason'] && array( 3 ) === $unknown['unknown_rooms'],
	'une chambre absente du registre rend la réservation indéterminée'
);

$unknown_and_mixed = lme_brands_resolve_brand_for_rooms( $config, array( 1, 3 ) );
lme_brands_test_assert(
	'undetermined' === $unknown_and_mixed['status'] && 'unknown_room' === $unknown_and_mixed['reason'],
	"une chambre inconnue l'emporte sur toute autre raison : c'est le cas que le chapitre 6 exige d'alerter"
);

$no_room = lme_brands_resolve_brand_for_rooms( $config, array() );
lme_brands_test_assert(
	'undetermined' === $no_room['status'] && 'no_rooms' === $no_room['reason'],
	"une réservation sans chambre lisible est indéterminée, jamais un envoi sous une marque par défaut"
);

// --- Phase 3 : langues ------------------------------------------------------------

echo "\nlme_brands_normalize_language_tag() et lme_brands_pick_localized()\n";

lme_brands_test_assert(
	'fr' === lme_brands_normalize_language_tag( 'fr-FR' )
		&& 'de' === lme_brands_normalize_language_tag( 'de_CH' )
		&& 'en' === lme_brands_normalize_language_tag( 'en' )
		&& 'fr' === lme_brands_normalize_language_tag( '  FR-fr ' ),
	"les étiquettes réelles de sir_vikbooking_orders.lang se ramènent à leur code primaire"
);

lme_brands_test_assert(
	null === lme_brands_normalize_language_tag( '' )
		&& null === lme_brands_normalize_language_tag( null )
		&& null === lme_brands_normalize_language_tag( array() ),
	"une langue absente reste absente, jamais devinée"
);

$subjects = array( 'fr' => 'Objet FR', 'en' => 'Objet EN' );

lme_brands_test_assert(
	'Objet EN' === lme_brands_pick_localized( $subjects, array( 'en', 'fr' ) ),
	'la première langue préférée disponible gagne'
);

lme_brands_test_assert(
	'Objet FR' === lme_brands_pick_localized( $subjects, array( 'de' ) ),
	"une langue absente de la table se replie sur la première entrée : l'objet d'une autre langue de la bonne marque vaut mieux que celui de l'autre marque"
);

lme_brands_test_assert(
	null === lme_brands_pick_localized( array(), array( 'fr' ) ),
	"une table vide ne produit pas d'objet : l'appelant garde celui de Vik"
);

// --- Phase 3 : substitutions et injection -----------------------------------------

echo "\nlme_brands_apply_text_replacements() et lme_brands_inject_html_before_body_end()\n";

lme_brands_test_assert(
	'bonjour monde' === lme_brands_apply_text_replacements( 'bonjour terre', array( 'terre' => 'monde' ) ),
	'une substitution littérale simple est appliquée'
);

lme_brands_test_assert(
	'a c' === lme_brands_apply_text_replacements( 'a b c', array( 'b ' => '' ) ),
	'un remplacement vide supprime le terme cherché'
);

lme_brands_test_assert(
	'x' === lme_brands_apply_text_replacements( 'x', array( '' => 'y', 'x' => null ) ),
	'une substitution mal formée est ignorée, pas appliquée de travers'
);

lme_brands_test_assert(
	'<body>a<b>SIG</body>' === lme_brands_inject_html_before_body_end( '<body>a<b></body>', 'SIG' ),
	"le fragment entre avant </body>, pas après </html>"
);

lme_brands_test_assert(
	'<body>1</body><body>2SIG</body>' === lme_brands_inject_html_before_body_end( '<body>1</body><body>2</body>', 'SIG' ),
	"c'est la dernière </body> qui compte, pas la première"
);

lme_brands_test_assert(
	'texte nuSIG' === lme_brands_inject_html_before_body_end( 'texte nu', 'SIG' )
		&& 'texte nu' === lme_brands_inject_html_before_body_end( 'texte nu', '' ),
	"sans </body> le fragment est ajouté à la fin ; un fragment vide ne change rien"
);

// --- Phase 3 : contrôle de fuite d'une marque dans le message d'une autre ---------

echo "\nlme_brands_foreign_brand_tokens() et lme_brands_find_foreign_tokens()\n";

$foreign = lme_brands_foreign_brand_tokens( $config, 'sexcaperoom' );

lme_brands_test_assert(
	in_array( "L'Instant Clé", $foreign, true )
		&& in_array( 'linstantcle.ch', $foreign, true )
		&& in_array( 'reservations@linstantcle.ch', $foreign, true )
		&& ! in_array( 'Sexcape Room', $foreign, true ),
	"les termes étrangers d'un message Sexcape Room sont ceux de L'Instant Clé, et pas les siens"
);

lme_brands_test_assert(
	array() === lme_brands_foreign_brand_tokens( array( 'brands' => array() ), 'sexcaperoom' ),
	"un registre sans autre marque n'a rien d'étranger à signaler"
);

lme_brands_test_assert(
	array( "L'Instant Clé" ) === lme_brands_find_foreign_tokens( '<p>Merci de votre séjour à L&#039;Instant Clé.</p>', array( "L'Instant Clé" ) ),
	"une apostrophe encodée en entité HTML ne fait pas échouer le contrôle"
);

lme_brands_test_assert(
	array( "L'Instant Clé" ) === lme_brands_find_foreign_tokens( "<p>L\xE2\x80\x99Instant Clé</p>", array( "L'Instant Clé" ) ),
	"une apostrophe typographique non plus"
);

lme_brands_test_assert(
	array( 'linstantcle.ch' ) === lme_brands_find_foreign_tokens( '<img src="https://LINSTANTCLE.CH/x.jpg">', array( 'linstantcle.ch' ) ),
	"la recherche d'un hôte est insensible à la casse"
);

lme_brands_test_assert(
	array() === lme_brands_find_foreign_tokens( '<p>Sexcape Room</p>', array( "L'Instant Clé", 'linstantcle.ch' ) ),
	'un message propre ne déclenche rien'
);

// --- Phase 3 : identité posée sur le message --------------------------------------

echo "\nlme_brands_mail_identity()\n";

$sexcape = lme_brands_mail_identity( $config, lme_brands_resolve_brand_for_rooms( $config, array( 10 ) ), 'fr-FR' );

lme_brands_test_assert(
	'sexcaperoom' === $sexcape['brand_key']
		&& 'reservations@sexcaperoom.ch' === $sexcape['sender_email']
		&& 'Sexcape Room' === $sexcape['sender_name']
		&& 'reservations@sexcaperoom.ch' === $sexcape['reply_to']
		&& 'Votre réservation — Sexcape Room' === $sexcape['subject'],
	"une réservation de la chambre 10 en français prend l'identité Sexcape Room de bout en bout"
);

lme_brands_test_assert(
	$sexcape['sender_email'] !== $sexcape['sender_name'],
	"adresse et nom d'expéditeur diffèrent : sans quoi VBOMailWrapper efface le nom et se replie sur le titre global du site"
);

$sexcape_de = lme_brands_mail_identity( $config, lme_brands_resolve_brand_for_rooms( $config, array( 10 ) ), 'de-DE' );

lme_brands_test_assert(
	'Votre réservation — Sexcape Room' === $sexcape_de['subject'],
	"une réservation allemande d'une marque qui ne déclare que le français prend l'objet français de cette marque, jamais l'objet natif de Vik"
);

$lic = lme_brands_mail_identity( $config, lme_brands_resolve_brand_for_rooms( $config, array( 1 ) ), 'en-US' );

lme_brands_test_assert(
	'linstantcle' === $lic['brand_key'] && null === $lic['subject'],
	"une marque sans table d'objets laisse l'objet natif de Vik intact"
);

$undetermined = lme_brands_mail_identity( $config, lme_brands_resolve_brand_for_rooms( $config, array( 1, 4 ) ), 'fr-FR' );

lme_brands_test_assert(
	null === $undetermined['brand_key']
		&& 'Réservations' === $undetermined['sender_name']
		&& null === $undetermined['sender_email']
		&& null === $undetermined['reply_to']
		&& 'Votre réservation' === $undetermined['subject']
		&& array() === $undetermined['replacements']
		&& null === $undetermined['signature_html'],
	"une marque indéterminée prend l'identité neutre : aucun nom de marque, et l'adresse de Vik conservée plutôt qu'une adresse inventée"
);

lme_brands_test_assert(
	array() === $undetermined['foreign_tokens'],
	"marque inconnue, donc rien à déclarer étranger : on ne contrôle pas ce qu'on ne sait pas"
);

$undetermined_en = lme_brands_mail_identity( $config, lme_brands_resolve_brand_for_rooms( $config, array( 3 ) ), 'en-US' );

lme_brands_test_assert(
	'Your reservation' === $undetermined_en['subject'],
	"l'objet neutre suit la langue de la réservation"
);

// --- Phase 3 : le registre refuse les formes qui produiraient la mauvaise marque ---

echo "\nvalidate_config(), contrôles ajoutés par la phase 3\n";

$no_neutral = lme_brands_test_sample_config();
unset( $no_neutral['neutral'] );

lme_brands_test_assert(
	1 === count( lme_brands_validate_config( $no_neutral ) ),
	"un registre sans identité neutre est invalide : sans elle, une marque indéterminée partirait sous l'expéditeur global"
);

$same_sender = lme_brands_test_sample_config();
$same_sender['brands']['sexcaperoom']['sender_name'] = $same_sender['brands']['sexcaperoom']['sender_email'];

lme_brands_test_assert(
	1 === count( lme_brands_validate_config( $same_sender ) ),
	'un nom d\'expéditeur identique à l\'adresse est refusé — piège de VBOMailWrapper::setSender()'
);

$bad_subject = lme_brands_test_sample_config();
$bad_subject['brands']['sexcaperoom']['mail']['subject'] = array( 'fr' => '' );

lme_brands_test_assert(
	1 === count( lme_brands_validate_config( $bad_subject ) ),
	'un objet vide dans mail.subject est refusé plutôt que silencieusement ignoré'
);

$bad_replacements = lme_brands_test_sample_config();
$bad_replacements['brands']['sexcaperoom']['mail']['replacements'] = array( 'de' => array( 'x' ) );

lme_brands_test_assert(
	1 === count( lme_brands_validate_config( $bad_replacements ) ),
	'une substitution dont le remplacement n\'est pas une chaîne est refusée'
);

$bad_neutral = lme_brands_test_sample_config();
$bad_neutral['neutral']['sender_email'] = '';

lme_brands_test_assert(
	1 === count( lme_brands_validate_config( $bad_neutral ) ),
	"une adresse neutre vide est refusée : null veut dire « garder celle de Vik », la chaîne vide ne veut rien dire"
);

// --- Chantier B5 : recoupement réservation / destinataires ------------------------

echo "\nlme_brands_normalize_email() et lme_brands_mail_booking_matches_recipients()\n";

lme_brands_test_assert(
	'jean@example.ch' === lme_brands_normalize_email( '  Jean@Example.CH  ' ),
	'une adresse est ramenée en minuscules, sans espaces de bord'
);

lme_brands_test_assert(
	'' === lme_brands_normalize_email( null ) && '' === lme_brands_normalize_email( array( 'a@b.ch' ) ),
	'ce qui n\'est pas une chaîne ne devient jamais une adresse'
);

$b5_booking = array(
	'id'       => 1234,
	'custmail' => 'client@example.ch',
	'channel'  => null,
	'lang'     => 'fr-FR',
);

lme_brands_test_assert(
	true === lme_brands_mail_booking_matches_recipients( $b5_booking, array( 'client@example.ch' ) ),
	'le client de la réservation déposée est le destinataire : on peut y croire'
);

lme_brands_test_assert(
	true === lme_brands_mail_booking_matches_recipients( $b5_booking, 'client@example.ch' ),
	'un destinataire unique passé en chaîne est accepté comme une liste d\'un élément'
);

lme_brands_test_assert(
	true === lme_brands_mail_booking_matches_recipients( $b5_booking, array( 'CLIENT@Example.ch ' ) ),
	'la casse et les espaces ne font pas échouer un recoupement légitime'
);

lme_brands_test_assert(
	true === lme_brands_mail_booking_matches_recipients( $b5_booking, array( 'info@maisonnette-enchantee.ch', 'client@example.ch' ) ),
	'le client compte même quand une règle conditionnelle a ajouté un destinataire administrateur'
);

lme_brands_test_assert(
	false === lme_brands_mail_booking_matches_recipients( $b5_booking, array( 'info@maisonnette-enchantee.ch' ) ),
	"le message de l'administrateur n'est pas rattaché à la réservation du client"
);

lme_brands_test_assert(
	false === lme_brands_mail_booking_matches_recipients( $b5_booking, array( 'quelquun-dautre@example.ch' ) ),
	'une réservation périmée dans le magasin partagé de Vik ne contamine pas le message suivant'
);

lme_brands_test_assert(
	false === lme_brands_mail_booking_matches_recipients( null, array( 'client@example.ch' ) )
		&& false === lme_brands_mail_booking_matches_recipients( array(), array( 'client@example.ch' ) ),
	'un magasin vide ne rattache rien : un message sans réservation reste intouché'
);

lme_brands_test_assert(
	false === lme_brands_mail_booking_matches_recipients( array( 'id' => 12, 'custmail' => '' ), array( 'client@example.ch' ) )
		&& false === lme_brands_mail_booking_matches_recipients( array( 'custmail' => 'client@example.ch' ), array( 'client@example.ch' ) ),
	'une réservation sans identifiant ou sans adresse client ne rattache rien'
);

lme_brands_test_assert(
	false === lme_brands_mail_booking_matches_recipients( $b5_booking, array( 'Client <client@example.ch>' ) ),
	"une adresse de la forme « Nom <adresse> » n'est pas décortiquée : le message est laissé tel quel, ce qui est la bonne façon de se tromper"
);

lme_brands_test_assert(
	false === lme_brands_mail_booking_matches_recipients( $b5_booking, array() )
		&& false === lme_brands_mail_booking_matches_recipients( $b5_booking, null ),
	'un message sans destinataire ne rattache rien'
);

// L'identité posée sur un rappel est exactement celle du message client : même
// registre, même résolution, même fonction pure. Ce que le chantier B5 change,
// c'est ce qui en est lu — l'expéditeur et le nom affiché, rien d'autre.
$b5_identity = lme_brands_mail_identity( $config, lme_brands_resolve_brand_for_rooms( $config, array( 9 ) ), 'fr-FR' );

lme_brands_test_assert(
	'reservations@sexcaperoom.ch' === $b5_identity['sender_email']
		&& 'Sexcape Room' === $b5_identity['sender_name']
		&& $b5_identity['sender_name'] !== $b5_identity['sender_email'],
	'un rappel de chambre Sexcape Room prend expéditeur et nom Sexcape Room, et le nom ne vaut pas l\'adresse'
);

$b5_neutral = lme_brands_mail_identity( $config, lme_brands_resolve_brand_for_rooms( $config, array( 8, 9 ) ), 'fr-FR' );

lme_brands_test_assert(
	null === $b5_neutral['brand_key']
		&& null === $b5_neutral['sender_email']
		&& false === stripos( $b5_neutral['sender_name'], 'instant' )
		&& false === stripos( $b5_neutral['sender_name'], 'sexcape' ),
	"marque indéterminée sur un rappel : nom neutre, et l'adresse de Vik gardée plutôt qu'une adresse inventée"
);

// --- Phase 3 sur le registre réel --------------------------------------------------

echo "\nPhase 3, registre réel config/brands.php\n";

$real_sexcape = lme_brands_mail_identity( $real_config, lme_brands_resolve_brand_for_rooms( $real_config, array( 9 ) ), 'fr-FR' );

lme_brands_test_assert(
	'sexcaperoom' === $real_sexcape['brand_key']
		&& is_string( $real_sexcape['subject'] ) && '' !== $real_sexcape['subject']
		&& false === stripos( $real_sexcape['subject'], 'instant' ),
	"registre réel : une réservation de L'Indécent prend un objet Sexcape Room, qui ne nomme pas L'Instant Clé"
);

lme_brands_test_assert(
	in_array( 'linstantcle.ch', $real_sexcape['foreign_tokens'], true ),
	"registre réel : le contrôle de fuite d'un message Sexcape Room surveille bien l'hôte linstantcle.ch"
);

$real_neutral = lme_brands_mail_identity( $real_config, lme_brands_resolve_brand_for_rooms( $real_config, array( 999 ) ), null );

lme_brands_test_assert(
	null === $real_neutral['brand_key']
		&& false === stripos( $real_neutral['sender_name'], 'instant' )
		&& false === stripos( $real_neutral['sender_name'], 'sexcape' )
		&& false === stripos( (string) $real_neutral['subject'], 'instant' )
		&& false === stripos( (string) $real_neutral['subject'], 'sexcape' ),
	'registre réel : une chambre inconnue produit une identité qui ne nomme aucune des deux marques'
);

// --- Résultat -------------------------------------------------------------------

$count    = $GLOBALS['lme_brands_test_count'];
$failures = $GLOBALS['lme_brands_test_failures'];

echo "\n{$count} tests, {$failures} échec(s).\n";

exit( $failures > 0 ? 1 : 0 );
