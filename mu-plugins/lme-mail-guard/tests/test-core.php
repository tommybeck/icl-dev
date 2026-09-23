<?php
/**
 * Tests unitaires purs pour includes/core.php.
 *
 * Ne nécessitent ni WordPress ni PHPUnit, volontairement : `core.php` ne
 * touche à rien d'externe, donc `php tests/test-core.php` suffit à le
 * vérifier de bout en bout, y compris depuis un poste sans site WordPress
 * installé. Même convention que mu-plugins/lme-brands/tests/test-core.php.
 *
 * Exécution :
 *     php mu-plugins/lme-mail-guard/tests/test-core.php
 *
 * Sortie : une ligne par test, un total, et un code de sortie non nul si un
 * test échoue (exploitable dans une intégration continue).
 */

require_once __DIR__ . '/../includes/core.php';

$GLOBALS['lme_mail_guard_test_count']    = 0;
$GLOBALS['lme_mail_guard_test_failures'] = 0;

/**
 * @param bool   $condition
 * @param string $label
 */
function lme_mail_guard_test_assert( $condition, $label ) {
	++$GLOBALS['lme_mail_guard_test_count'];

	if ( $condition ) {
		echo "  ok  - {$label}\n";
		return;
	}

	echo "FAIL  - {$label}\n";
	++$GLOBALS['lme_mail_guard_test_failures'];
}

/**
 * @param mixed  $expected
 * @param mixed  $actual
 * @param string $label
 */
function lme_mail_guard_test_assert_same( $expected, $actual, $label ) {
	lme_mail_guard_test_assert(
		$expected === $actual,
		$label . ' (attendu ' . var_export( $expected, true ) . ', obtenu ' . var_export( $actual, true ) . ')'
	);
}

echo "== lme_mail_guard_normalize_host() ==\n";

lme_mail_guard_test_assert_same( null, lme_mail_guard_normalize_host( null ), 'null reste null' );
lme_mail_guard_test_assert_same( null, lme_mail_guard_normalize_host( '' ), "chaîne vide devient null" );
lme_mail_guard_test_assert_same( null, lme_mail_guard_normalize_host( 42 ), 'valeur non-chaîne devient null' );
lme_mail_guard_test_assert_same( 'linstantcle.ch', lme_mail_guard_normalize_host( 'linstantcle.ch' ), 'hôte déjà propre, inchangé' );
lme_mail_guard_test_assert_same( 'linstantcle.ch', lme_mail_guard_normalize_host( 'LinstantCLE.ch' ), 'mis en minuscules' );
lme_mail_guard_test_assert_same( 'linstantcle.ch', lme_mail_guard_normalize_host( 'linstantcle.ch:443' ), 'port retiré' );
lme_mail_guard_test_assert_same( 'staging13.linstantcle.ch', lme_mail_guard_normalize_host( 'STAGING13.linstantcle.ch:8080' ), 'sous-domaine minuscule, port retiré' );

echo "== lme_mail_guard_is_production_context() : les deux conditions, jamais une seule ==\n";

$prod_hosts = lme_mail_guard_default_production_hosts();

lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_is_production_context( 'production', 'linstantcle.ch', $prod_hosts ),
	'production + hôte de production listé = production'
);
lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_is_production_context( 'production', 'reservation.sexcaperoom.ch', $prod_hosts ),
	"production + reservation.sexcaperoom.ch (singulier, défaut 1.a corrigé) = production"
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_is_production_context( 'production', 'reservations.sexcaperoom.ch', $prod_hosts ),
	"la faute de frappe 'reservations.sexcaperoom.ch' (pluriel) ne matche toujours pas — régression du défaut 1.a"
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_is_production_context( 'staging', 'linstantcle.ch', $prod_hosts ),
	'hôte de production mais environnement staging = pas la production'
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_is_production_context( 'production', 'staging13.linstantcle.ch', $prod_hosts ),
	'environnement production mais hôte hors liste = pas la production'
);
lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_is_production_context( 'production', null, $prod_hosts ),
	"hôte absent (CLI, cron sans requête HTTP) mais environnement 'production' = production quand même — réserve de plan-de-marche.md §B9e levée, wp_get_environment_type() seul décide faute d'hôte à vérifier"
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_is_production_context( 'staging', null, $prod_hosts ),
	"hôte absent ET environnement 'staging' = pas la production — l'absence d'hôte ne fait jamais basculer une préproduction en production"
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_is_production_context( '', null, $prod_hosts ),
	"hôte absent ET environnement vide/inconnu = pas la production"
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_is_production_context( 'development', 'linstantcle.ch', $prod_hosts ),
	"environnement 'development' = pas la production"
);
lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_is_production_context( 'production', 'linstantcle.ch', array( 'LinstantCLE.ch' ) ),
	"un hôte déjà normalisé matche même si une entrée de la liste ne l'est pas (robustesse défensive de la liste, pas du \$host — celui-ci reste la responsabilité de l'appelant)"
);

echo "== lme_mail_guard_should_redirect() : composition avec FORCE_EMAIL_REDIRECT ==\n";

lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_should_redirect( 'production', 'linstantcle.ch', $prod_hosts, false ),
	'production réelle, pas de forçage = ne pas détourner'
);
lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_should_redirect( 'staging', 'staging13.linstantcle.ch', $prod_hosts, false ),
	'préproduction, pas de forçage = détourner'
);
lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_should_redirect( 'production', 'linstantcle.ch', $prod_hosts, true ),
	"FORCE_EMAIL_REDIRECT=true force le détournement même si les deux conditions de production sont réunies"
);
lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_should_redirect( 'staging', null, $prod_hosts, false ),
	'ni environnement ni hôte de production = détourner'
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_should_redirect( 'production', null, $prod_hosts, false ),
	"production réelle, hôte absent (WP-CLI, cron en ligne de commande), pas de forçage = ne pas détourner — c'est le rappel de production de plan-de-marche.md §B9e qui ne doit plus être avalé"
);

echo "== lme_mail_guard_normalize_headers() : forme réelle de wp_mail(), défaut 1.c ==\n";

lme_mail_guard_test_assert_same(
	array(),
	lme_mail_guard_normalize_headers( '' ),
	"chaîne vide (valeur par défaut la plus fréquente de \$args['headers']) devient un tableau vide, sans erreur fatale"
);
lme_mail_guard_test_assert_same(
	array(),
	lme_mail_guard_normalize_headers( null ),
	'null devient un tableau vide'
);
lme_mail_guard_test_assert_same(
	array( 'From: Réservations <reservations@linstantcle.ch>' ),
	lme_mail_guard_normalize_headers( 'From: Réservations <reservations@linstantcle.ch>' ),
	'chaîne à une seule ligne, sans terminateur, devient un tableau à un élément'
);
lme_mail_guard_test_assert_same(
	array( 'From: a@example.ch', 'Reply-To: b@example.ch' ),
	lme_mail_guard_normalize_headers( "From: a@example.ch\r\nReply-To: b@example.ch" ),
	'chaîne à deux lignes séparées par "\r\n" (forme réelle produite par PHPMailer/Vik)'
);
lme_mail_guard_test_assert_same(
	array( 'From: a@example.ch', 'Reply-To: b@example.ch' ),
	lme_mail_guard_normalize_headers( "From: a@example.ch\nReply-To: b@example.ch\n" ),
	'chaîne séparée par "\n", ligne finale vide ignorée'
);
lme_mail_guard_test_assert_same(
	array( 'From: a@example.ch', 'Cc: c@example.ch' ),
	lme_mail_guard_normalize_headers( array( 'From: a@example.ch', '  ', 'Cc: c@example.ch' ) ),
	'tableau déjà fourni : conservé tel quel, lignes blanches ignorées'
);
lme_mail_guard_test_assert_same(
	array( 'Content-Type: multipart/mixed; boundary="XYZ"', 'boundary=XYZ' ),
	lme_mail_guard_normalize_headers( "Content-Type: multipart/mixed; boundary=\"XYZ\"\nboundary=XYZ" ),
	"ligne sans deux-points (marqueur 'boundary=' d'un message multipart) conservée, jamais perdue"
);

echo "== lme_mail_guard_strip_cc_bcc() : défaut 1.d, le plus dangereux car silencieux ==\n";

lme_mail_guard_test_assert_same(
	array( 'From: a@example.ch' ),
	lme_mail_guard_strip_cc_bcc( array( 'From: a@example.ch', 'Bcc: espion@example.ch' ) ),
	"Bcc retiré — régression du défaut 1.d : stripos(trim(\$h),'cc:') !== 0 gardait ce Bcc, car 'cc:' n'est pas trouvé en position 0 dans 'Bcc: …'"
);
lme_mail_guard_test_assert_same(
	array( 'From: a@example.ch' ),
	lme_mail_guard_strip_cc_bcc( array( 'From: a@example.ch', 'Cc: copie@example.ch' ) ),
	'Cc retiré'
);
lme_mail_guard_test_assert_same(
	array( 'From: a@example.ch' ),
	lme_mail_guard_strip_cc_bcc( array( 'From: a@example.ch', 'BCC: espion@example.ch', 'cc: copie@example.ch' ) ),
	'Bcc et Cc retirés quelle que soit la casse du nom'
);
lme_mail_guard_test_assert_same(
	array( 'From: a@example.ch', 'Reply-To: b@example.ch' ),
	lme_mail_guard_strip_cc_bcc( array( 'From: a@example.ch', 'Reply-To: b@example.ch' ) ),
	'aucun Cc/Bcc : rien de retiré'
);
lme_mail_guard_test_assert_same(
	array( 'X-Debug: cc: ceci ne commence pas par cc' ),
	lme_mail_guard_strip_cc_bcc( array( 'X-Debug: cc: ceci ne commence pas par cc' ) ),
	"un en-tête dont la VALEUR contient 'cc:' n'est pas confondu avec un en-tête Cc : seul le nom (avant le premier deux-points) compte"
);
lme_mail_guard_test_assert_same(
	array( 'boundary=XYZ' ),
	lme_mail_guard_strip_cc_bcc( array( 'boundary=XYZ' ) ),
	'ligne sans deux-points conservée (non interprétable comme Cc/Bcc)'
);

echo "== lme_mail_guard_format_original_to() ==\n";

lme_mail_guard_test_assert_same(
	'client@example.ch',
	lme_mail_guard_format_original_to( 'client@example.ch' ),
	'chaîne simple, inchangée'
);
lme_mail_guard_test_assert_same(
	'a@example.ch, b@example.ch',
	lme_mail_guard_format_original_to( array( 'a@example.ch', 'b@example.ch' ) ),
	'tableau joint par virgule'
);
lme_mail_guard_test_assert_same(
	'',
	lme_mail_guard_format_original_to( array() ),
	'tableau vide = chaîne vide'
);
lme_mail_guard_test_assert_same(
	'',
	lme_mail_guard_format_original_to( null ),
	'null = chaîne vide'
);

echo "== lme_mail_guard_build_redirected_headers() : composition de bout en bout ==\n";

lme_mail_guard_test_assert_same(
	array( 'X-Original-To: client@example.ch' ),
	lme_mail_guard_build_redirected_headers( '', 'client@example.ch' ),
	"headers='' (cas le plus fréquent, défaut 1.c) + un destinataire : ne plante pas, produit juste X-Original-To"
);
lme_mail_guard_test_assert_same(
	array( 'From: a@example.ch', 'X-Original-To: client@example.ch' ),
	lme_mail_guard_build_redirected_headers( "From: a@example.ch\r\nBcc: espion@example.ch", 'client@example.ch' ),
	'From conservé (jamais touché), Bcc retiré, X-Original-To ajouté'
);
lme_mail_guard_test_assert_same(
	array( 'X-Original-To: a@example.ch, b@example.ch' ),
	lme_mail_guard_build_redirected_headers( array(), array( 'a@example.ch', 'b@example.ch' ) ),
	'plusieurs destinataires joints dans X-Original-To'
);

echo "== lme_mail_guard_resolve_catchall() : repli inerte ==\n";

lme_mail_guard_test_assert_same(
	null,
	lme_mail_guard_resolve_catchall( false, null ),
	'constante non définie = null, jamais une adresse par défaut'
);
lme_mail_guard_test_assert_same(
	null,
	lme_mail_guard_resolve_catchall( true, '' ),
	'constante définie mais vide = null'
);
lme_mail_guard_test_assert_same(
	null,
	lme_mail_guard_resolve_catchall( true, '   ' ),
	'constante définie mais blanche = null'
);
lme_mail_guard_test_assert_same(
	'recette@linstantcle.ch',
	lme_mail_guard_resolve_catchall( true, 'recette@linstantcle.ch' ),
	'constante définie et non vide = son contenu, trimé'
);
lme_mail_guard_test_assert_same(
	'recette@linstantcle.ch',
	lme_mail_guard_resolve_catchall( true, '  recette@linstantcle.ch  ' ),
	'espaces superflus retirés'
);

echo "== lme_mail_guard_find_header_value() ==\n";

lme_mail_guard_test_assert_same(
	'Réservations <reservations@linstantcle.ch>',
	lme_mail_guard_find_header_value( array( 'From: Réservations <reservations@linstantcle.ch>', 'Reply-To: a@example.ch' ), 'From' ),
	'trouve From, insensible à la casse du nom recherché implicitement (ici déjà exact)'
);
lme_mail_guard_test_assert_same(
	'a@example.ch',
	lme_mail_guard_find_header_value( array( 'reply-to: a@example.ch' ), 'Reply-To' ),
	'insensible à la casse du nom dans la ligne'
);
lme_mail_guard_test_assert_same(
	null,
	lme_mail_guard_find_header_value( array( 'From: a@example.ch' ), 'Sender' ),
	"en-tête absent = null, jamais une chaîne vide qui se confondrait avec un en-tête présent mais vide"
);
lme_mail_guard_test_assert_same(
	null,
	lme_mail_guard_find_header_value( array( 'boundary=XYZ' ), 'From' ),
	'ligne sans deux-points ignorée, jamais interprétée comme un en-tête'
);
lme_mail_guard_test_assert_same(
	'premier@example.ch',
	lme_mail_guard_find_header_value( array( 'From: premier@example.ch', 'From: second@example.ch' ), 'From' ),
	'première occurrence retenue en cas de doublon'
);
lme_mail_guard_test_assert_same(
	'a@example.ch',
	lme_mail_guard_find_header_value( array( 'X-Debug: from: ceci n’est pas un en-tête From', 'From: a@example.ch' ), 'From' ),
	"une valeur d'en-tête qui contient littéralement 'from:' n'est pas confondue avec le nom de l'en-tête — même garde que lme_mail_guard_strip_cc_bcc()"
);

echo "== lme_mail_guard_format_envelope_line() : transcription d'enveloppe, chapitre 2 du brief ==\n";

$envelope_headers = lme_mail_guard_normalize_headers(
	"From: L'Instant Clé <reservations@linstantcle.ch>\r\nSender: reservations@linstantcle.ch\r\nReply-To: reservations@linstantcle.ch\r\nBcc: espion@example.ch"
);

$line = lme_mail_guard_format_envelope_line(
	'2026-09-23T10:00:00+00:00',
	'client@example.ch',
	$envelope_headers,
	'Votre réservation',
	'staging13.linstantcle.ch'
);
$decoded = json_decode( $line, true );

lme_mail_guard_test_assert( is_string( $line ) && '' !== $line, 'produit une chaîne non vide' );
lme_mail_guard_test_assert( is_array( $decoded ), 'la ligne est un JSON valide (une ligne, un enregistrement)' );
lme_mail_guard_test_assert_same( '2026-09-23T10:00:00+00:00', $decoded['ts'], 'horodatage transmis tel quel' );
lme_mail_guard_test_assert_same( 'client@example.ch', $decoded['to'], "To d'origine (avant tout détournement)" );
lme_mail_guard_test_assert_same( "L'Instant Clé <reservations@linstantcle.ch>", $decoded['from'], 'From lu dans les en-têtes' );
lme_mail_guard_test_assert_same( 'reservations@linstantcle.ch', $decoded['sender'], 'Sender lu dans les en-têtes' );
lme_mail_guard_test_assert_same( 'reservations@linstantcle.ch', $decoded['reply_to'], 'Reply-To lu dans les en-têtes' );
lme_mail_guard_test_assert_same( 'Votre réservation', $decoded['subject'], 'objet transmis tel quel' );
lme_mail_guard_test_assert_same( 'staging13.linstantcle.ch', $decoded['host'], 'hôte transmis tel quel' );
lme_mail_guard_test_assert(
	false === strpos( $line, 'espion@example.ch' ),
	"le Bcc d'origine n'apparaît nulle part : cette fonction ne reçoit même pas de quoi le transcrire"
);

$line_no_from = lme_mail_guard_format_envelope_line( '2026-09-23T10:00:00+00:00', 'client@example.ch', array(), '', null );
$decoded_no_from = json_decode( $line_no_from, true );
lme_mail_guard_test_assert_same( '', $decoded_no_from['from'], "From absent = chaîne vide dans la transcription, pas d'erreur" );
lme_mail_guard_test_assert_same( '', $decoded_no_from['host'], 'hôte absent (CLI, cron) = chaîne vide, pas une erreur' );

$line_injection = lme_mail_guard_format_envelope_line(
	'2026-09-23T10:00:00+00:00',
	'client@example.ch',
	array(),
	"Sujet\nCc: injection@example.ch",
	'staging13.linstantcle.ch'
);
lme_mail_guard_test_assert(
	0 === substr_count( $line_injection, "\n" ),
	"un retour à la ligne injecté dans l'objet ne fait jamais déborder la ligne du journal sur deux lignes"
);

echo "== la transcription d'enveloppe s'active exactement quand le détournement s'active — pas un second test ==\n";
echo "   (lme_mail_guard_maybe_transcribe_envelope() de includes/guard.php reçoit la même valeur\n";
echo "   \$should_redirect que le détournement, calculée une seule fois par lme_mail_guard_filter_wp_mail() ;\n";
echo "   les cas ci-dessous sont donc déjà couverts par la suite lme_mail_guard_should_redirect() plus haut,\n";
echo "   répétés ici nommément pour qu'ils survivent même si cette suite est un jour réorganisée.)\n";

lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_should_redirect( 'staging', 'staging13.linstantcle.ch', $prod_hosts, false ),
	"préproduction : le détournement s'active, donc la transcription aussi"
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_should_redirect( 'production', 'linstantcle.ch', $prod_hosts, false ),
	"production stricte : ni détournement ni transcription"
);
lme_mail_guard_test_assert_same(
	false,
	lme_mail_guard_should_redirect( 'production', null, $prod_hosts, false ),
	"production, hôte absent (WP-CLI, cron en ligne de commande) : ni détournement ni transcription, cohérent avec le rappel de production qui ne doit pas être avalé (plan-de-marche.md §B9e)"
);
lme_mail_guard_test_assert_same(
	true,
	lme_mail_guard_should_redirect( 'production', 'linstantcle.ch', $prod_hosts, true ),
	'FORCE_EMAIL_REDIRECT=true : détournement et transcription actifs même sur un hôte de production (cas du clone qui garde le domaine)'
);

echo "\n{$GLOBALS['lme_mail_guard_test_count']} tests, {$GLOBALS['lme_mail_guard_test_failures']} échec(s).\n";

exit( $GLOBALS['lme_mail_guard_test_failures'] > 0 ? 1 : 0 );
