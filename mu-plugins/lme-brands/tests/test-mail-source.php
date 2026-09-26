<?php
/**
 * Tests du point d'accroche générique du chantier B5,
 * lme_brands_brand_mail_source(), dans includes/mail-brand.php.
 *
 * Contrairement à test-core.php, ce fichier charge un fichier qui appelle
 * WordPress et Vik. Il les remplace par des doublures minimales, déclarées
 * ci-dessous et nulle part ailleurs : `add_action`, le journal, la lecture
 * des chambres d'une réservation, le magasin partagé de Vik et le wrapper
 * d'e-mail. Le registre, lui, est le vrai : config/brands.php.
 *
 * La doublure du wrapper reproduit le comportement de `VBOMailWrapper`
 * (Vik Booking 1.8.14, `admin/helpers/src/mail/wrapper.php:166-276`) pour les
 * seules méthodes que le hook emploie : `setSender()` efface le nom quand il
 * vaut l'adresse, `getSenderMail()` se replie sur `senderemail`, `setReply()`
 * remplace l'adresse de réponse — il n'y en a qu'une.
 *
 * Exécution :
 *     php mu-plugins/lme-brands/tests/test-mail-source.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['lme_test_count']    = 0;
$GLOBALS['lme_test_failures'] = 0;
$GLOBALS['lme_test_log']      = array();
$GLOBALS['lme_test_rooms']    = array();
$GLOBALS['lme_test_store']    = array();
$GLOBALS['lme_test_config']   = require __DIR__ . '/../config/brands.php';

const LME_TEST_VIK_SENDER = 'info@maisonnette-enchantee.ch';

// --- Doublures ------------------------------------------------------------------

function add_action() {}

function lme_brands_log( $level, $code, $message, array $context = array() ) {
	$GLOBALS['lme_test_log'][] = array(
		'level'   => $level,
		'code'    => $code,
		'message' => $message,
		'context' => $context,
	);
}

function lme_brands_get_config() {
	return $GLOBALS['lme_test_config'];
}

function lme_brands_booking_room_ids( $idorder ) {
	return isset( $GLOBALS['lme_test_rooms'][ $idorder ] ) ? $GLOBALS['lme_test_rooms'][ $idorder ] : array();
}

function lme_brands_resolve_room_or_log( $room_id ) {
	return lme_brands_resolve_room( lme_brands_get_config(), $room_id );
}

class LmeTestRules {
	public function get( $key, $def = null ) {
		return array_key_exists( $key, $GLOBALS['lme_test_store'] ) ? $GLOBALS['lme_test_store'][ $key ] : $def;
	}
}

class VikBooking {
	public static function getConditionalRulesInstance() {
		return new LmeTestRules();
	}
}

class LmeTestMail {
	private $sender;
	private $recipient = array();
	private $reply;

	public function __construct( $recipient, $reply ) {
		// Ce que `sendMail()` pose pour un rappel (`email_reminder.php:660`) :
		// l'adresse de Vik en expéditeur, en nom et en adresse de réponse.
		$this->setSender( LME_TEST_VIK_SENDER, LME_TEST_VIK_SENDER );
		$this->recipient = (array) $recipient;
		$this->reply     = $reply;
	}

	public function setSender( $address, $name = null ) {
		$this->sender = array(
			'address' => $address,
			'name'    => $address != $name ? $name : null,
		);
		return $this;
	}

	public function getSenderMail() {
		return ! empty( $this->sender['address'] ) ? $this->sender['address'] : LME_TEST_VIK_SENDER;
	}

	public function getSenderName() {
		return ! empty( $this->sender['name'] ) ? $this->sender['name'] : "L'Instant Clé";
	}

	public function getRecipient() {
		return $this->recipient;
	}

	public function setReply( $address ) {
		$this->reply = $address;
		return $this;
	}

	public function getReply() {
		return $this->reply;
	}
}

/** Un wrapper d'une version de Vik qui n'aurait plus `setReply()`. */
class LmeTestMailWithoutReply {
	public function setSender( $address, $name = null ) {}
	public function getSenderMail() { return LME_TEST_VIK_SENDER; }
	public function getRecipient() { return array( 'client@example.ch' ); }
}

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/mail-brand.php';

// --- Outils ---------------------------------------------------------------------

function lme_test_assert( $condition, $label ) {
	++$GLOBALS['lme_test_count'];

	if ( $condition ) {
		echo "  ok  - {$label}\n";
		return;
	}

	echo "FAIL  - {$label}\n";
	++$GLOBALS['lme_test_failures'];
}

/**
 * Dépose une réservation comme le fait l'émetteur de Vik, et prépare le
 * message qu'il enverrait à son client.
 */
function lme_test_reminder( $booking_id, array $room_ids, $custmail = 'client@example.ch', $channel = null ) {
	$GLOBALS['lme_test_rooms'][ $booking_id ] = $room_ids;
	$GLOBALS['lme_test_store']['booking']     = array(
		'id'       => $booking_id,
		'custmail' => $custmail,
		'channel'  => $channel,
		'lang'     => 'fr-FR',
	);

	return new LmeTestMail( $custmail, LME_TEST_VIK_SENDER );
}

function lme_test_log_codes() {
	$codes = array();
	foreach ( $GLOBALS['lme_test_log'] as $entry ) {
		$codes[] = $entry['code'];
	}
	return $codes;
}

// --- Marque résolue : l'adresse de réponse suit l'expéditeur -----------------------

echo "\nlme_brands_brand_mail_source(), marque résolue\n";

$GLOBALS['lme_test_log'] = array();
$mail = lme_test_reminder( 1839, array( 4 ) );
lme_brands_brand_mail_source( $mail );

lme_test_assert(
	'reservations@sexcaperoom.ch' === $mail->getSenderMail() && 'Sexcape Room' === $mail->getSenderName(),
	'rappel du Boudoir du Désir : expéditeur Sexcape Room, comme avant'
);
lme_test_assert(
	'reservations@sexcaperoom.ch' === $mail->getReply(),
	"rappel du Boudoir du Désir : l'adresse de réponse devient celle de Sexcape Room"
);
lme_test_assert( array() === $GLOBALS['lme_test_log'], 'marque résolue : rien au journal' );

$mail = lme_test_reminder( 1841, array( 8 ) );
lme_brands_brand_mail_source( $mail );

lme_test_assert(
	'reservations@linstantcle.ch' === $mail->getSenderMail() && 'reservations@linstantcle.ch' === $mail->getReply(),
	"rappel de La Parenthèse : expéditeur et adresse de réponse de L'Instant Clé"
);

// Contre-épreuve n°5 de la recette, étendue à l'adresse de réponse : dans une
// même exécution, deux réservations de marques différentes produisent deux
// adresses de réponse différentes.
$first  = lme_test_reminder( 2001, array( 9 ) );
lme_brands_brand_mail_source( $first );
$second = lme_test_reminder( 2002, array( 2 ), 'autre@example.ch' );
lme_brands_brand_mail_source( $second );

lme_test_assert(
	'reservations@sexcaperoom.ch' === $first->getReply() && 'reservations@linstantcle.ch' === $second->getReply(),
	'même exécution, deux marques : chaque message prend l\'adresse de réponse de sa propre réservation'
);

// --- Marque mêlée ou absente : comportement d'avant, et journal -------------------

echo "\nlme_brands_brand_mail_source(), marque indéterminée\n";

foreach ( array(
	'mixed_brands' => array( 8, 9 ),
	'unknown_room' => array( 999 ),
	'no_rooms'     => array(),
) as $reason => $rooms ) {
	$GLOBALS['lme_test_log'] = array();
	$mail = lme_test_reminder( 3000 + count( $rooms ), $rooms );
	lme_brands_brand_mail_source( $mail );

	$entry = null;
	foreach ( $GLOBALS['lme_test_log'] as $candidate ) {
		if ( 'mail_brand_undetermined' === $candidate['code'] ) {
			$entry = $candidate;
		}
	}

	lme_test_assert(
		LME_TEST_VIK_SENDER === $mail->getReply(),
		"{$reason} : l'adresse de réponse posée par Vik est gardée"
	);
	lme_test_assert(
		LME_TEST_VIK_SENDER === $mail->getSenderMail()
			&& false === stripos( $mail->getSenderName(), 'instant' )
			&& false === stripos( $mail->getSenderName(), 'sexcape' ),
		"{$reason} : adresse d'expéditeur de Vik, nom neutre, comme avant"
	);
	lme_test_assert(
		null !== $entry
			&& 'error' === $entry['level']
			&& $reason === $entry['context']['reason']
			&& 'vikbooking_before_send_mail' === $entry['context']['chemin']
			&& LME_TEST_VIK_SENDER === $entry['context']['reply_to_kept']
			&& false !== strpos( $entry['message'], LME_TEST_VIK_SENDER ),
		"{$reason} : journalisé en erreur, avec l'adresse de réponse gardée"
	);
}

// --- Hors périmètre : rien ne change ----------------------------------------------

echo "\nlme_brands_brand_mail_source(), hors périmètre\n";

$GLOBALS['lme_test_log'] = array();

$stale = lme_test_reminder( 4001, array( 4 ) );
$GLOBALS['lme_test_store']['booking']['custmail'] = 'client-precedent@example.ch';
lme_brands_brand_mail_source( $stale );

lme_test_assert(
	LME_TEST_VIK_SENDER === $stale->getReply() && LME_TEST_VIK_SENDER === $stale->getSenderMail(),
	'réservation périmée dans le magasin partagé : ni expéditeur ni adresse de réponse touchés'
);

$ota = lme_test_reminder( 4002, array( 4 ), 'client@example.ch', 'airbnbapi' );
lme_brands_brand_mail_source( $ota );

lme_test_assert(
	LME_TEST_VIK_SENDER === $ota->getReply() && LME_TEST_VIK_SENDER === $ota->getSenderMail(),
	'réservation OTA : ni expéditeur ni adresse de réponse touchés'
);

$decided = lme_test_reminder( 4003, array( 4 ) );
lme_brands_mail_wrapper_decided( $decided, true );
lme_brands_brand_mail_source( $decided );

lme_test_assert(
	LME_TEST_VIK_SENDER === $decided->getReply(),
	'message déjà tranché par le hook métier : adresse de réponse non touchée'
);

unset( $GLOBALS['lme_test_store']['booking'] );
$orphan = new LmeTestMail( 'admin@example.ch', LME_TEST_VIK_SENDER );
lme_brands_brand_mail_source( $orphan );

lme_test_assert(
	LME_TEST_VIK_SENDER === $orphan->getReply(),
	'aucune réservation déposée : adresse de réponse non touchée'
);
lme_test_assert( array() === $GLOBALS['lme_test_log'], 'hors périmètre : rien au journal, comme avant' );

// --- Anomalies ----------------------------------------------------------------------

echo "\nlme_brands_brand_mail_source(), anomalies\n";

$GLOBALS['lme_test_log'] = array();
lme_brands_brand_mail_source( new LmeTestMailWithoutReply() );

lme_test_assert(
	array( 'mail_wrapper_unexpected' ) === lme_test_log_codes(),
	"un wrapper sans setReply() est une anomalie journalisée, pas un envoi à moitié marqué"
);

// Expéditeur refusé par le gardien de setSender() : l'adresse de réponse ne
// part pas seule vers la marque.
$saved = $GLOBALS['lme_test_config'];
$GLOBALS['lme_test_config']['brands']['sexcaperoom']['sender_name'] = 'reservations@sexcaperoom.ch';
$GLOBALS['lme_test_log'] = array();

$refused = lme_test_reminder( 5001, array( 4 ) );
lme_brands_brand_mail_source( $refused );

lme_test_assert(
	LME_TEST_VIK_SENDER === $refused->getSenderMail() && LME_TEST_VIK_SENDER === $refused->getReply(),
	"expéditeur refusé : l'adresse de réponse de Vik est gardée, elle suit le From"
);
lme_test_assert(
	in_array( 'mail_sender_name_unusable', lme_test_log_codes(), true ),
	'expéditeur refusé : journalisé'
);

$GLOBALS['lme_test_config'] = $saved;

// --- Résultat -------------------------------------------------------------------

$count    = $GLOBALS['lme_test_count'];
$failures = $GLOBALS['lme_test_failures'];

echo "\n{$count} tests, {$failures} échec(s).\n";

exit( $failures > 0 ? 1 : 0 );
