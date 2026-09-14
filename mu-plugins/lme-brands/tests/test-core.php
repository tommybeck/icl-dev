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
		),
		'excluded_room_ids' => array( 5, 6 ),
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

$broken                       = $config;
$broken['excluded_room_ids'][] = 1; // chevauche une chambre déjà vendue.
lme_brands_test_assert( count( lme_brands_validate_config( $broken ) ) > 0, 'validate_config : signale un chevauchement entre rooms et excluded_room_ids' );

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
lme_brands_test_assert( 'excluded' === $resolved['status'], 'resolve_room : la chambre de test 5 est exclue, pas inconnue' );

$resolved = lme_brands_resolve_room( $config, 6 );
lme_brands_test_assert( 'excluded' === $resolved['status'], 'resolve_room : la chambre de test 6 est exclue, pas inconnue' );

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

$category_tokens = array(
	1  => array( '1', '2' ),
	7  => array( '1' ),
	10 => array( '3' ),
	2  => array( '2' ),
);
lme_brands_test_assert(
	array( 1, 7 ) === lme_brands_room_ids_matching_category( $category_tokens, 1 ),
	'room_ids_matching_category : retrouve toutes les chambres portant le jeton'
);
lme_brands_test_assert(
	array() === lme_brands_room_ids_matching_category( $category_tokens, 99 ),
	"room_ids_matching_category : une catégorie sans chambre connue donne une liste vide"
);
lme_brands_test_assert(
	array() === lme_brands_room_ids_matching_category( array(), 1 ),
	'room_ids_matching_category : une carte vide donne une liste vide'
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

$expected_sellable_rooms = array( 1, 2, 4, 7, 8, 9, 10 );
$actual_sellable_rooms   = array_map( 'intval', array_keys( $real_config['rooms'] ) );
lme_brands_test_assert(
	array() === array_diff( $expected_sellable_rooms, $actual_sellable_rooms )
		&& array() === array_diff( $actual_sellable_rooms, $expected_sellable_rooms ),
	'config réel : les sept chambres vendues de la carte de vérité sont exactement celles du registre'
);
lme_brands_test_assert(
	array() === array_diff( array( 5, 6 ), $real_config['excluded_room_ids'] ),
	'config réel : les chambres de test 5 et 6 sont bien exclues'
);
lme_brands_test_assert(
	'linstantcle' === $real_config['rooms'][1]['brand'] && 'sexcaperoom' === $real_config['rooms'][10]['brand'],
	'config réel : la chambre 1 est L\'Instant Clé, la chambre 10 est Sexcape Room'
);

// --- Résultat -------------------------------------------------------------------

$count    = $GLOBALS['lme_brands_test_count'];
$failures = $GLOBALS['lme_brands_test_failures'];

echo "\n{$count} tests, {$failures} échec(s).\n";

exit( $failures > 0 ? 1 : 0 );
