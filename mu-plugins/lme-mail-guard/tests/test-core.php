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
	false,
	lme_mail_guard_is_production_context( 'production', null, $prod_hosts ),
	'hôte absent (CLI, cron sans requête HTTP) = pas la production'
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

echo "\n{$GLOBALS['lme_mail_guard_test_count']} tests, {$GLOBALS['lme_mail_guard_test_failures']} échec(s).\n";

exit( $GLOBALS['lme_mail_guard_test_failures'] > 0 ? 1 : 0 );
