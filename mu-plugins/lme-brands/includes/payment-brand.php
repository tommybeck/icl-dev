<?php
/**
 * lme-brands — paiement par marque. Chapitre 4.5 du brief, phase 4.
 *
 * Greffé sur `payment_before_begin_transaction_vikbooking`
 * (.local/vikbooking/libraries/adapter/payment/payment.php:329,
 * JPayment::showPayment()), qui se déclenche avant beginTransaction() —
 * donc avant que VikStripe ne construise la configuration de la session
 * Stripe (constat-phase-0.md Q5). L'appel est `do_action($hook, array(&$this))` :
 * le rappel reçoit un tableau dont l'indice 0 est l'objet de paiement, pas
 * l'objet directement — piège relevé dans ce même constat.
 *
 * Restreint à `isDriver('stripe')` : c'est la seule passerelle publiée
 * aujourd'hui (constat-reserve-paiement.md §2, sir_vikbooking_gpayments
 * id 3) et la seule dont la lecture de `tn_metadata` et de
 * `return_url`/`error_url`/`notify_url` a été vérifiée. Publier une autre
 * passerelle demandera de vérifier d'abord qu'elle se comporte pareil —
 * on ne suppose jamais qu'un greffon non lu lit les mêmes clés qu'un autre.
 *
 * --- Ce que ce fichier obtient, et ce qu'il n'obtient pas ------------------
 *
 * Établi le 17 septembre 2026 par une lecture ciblée de
 * `.local/wp-vikstripe/stripe.php` (grep sur 'metadata', 'descriptor' et
 * 'statement' — pas une relecture complète du greffon, déjà faite trois fois
 * et non reprise ici) :
 *
 * - `tn_metadata`, lu par `createSession()` (stripe.php:454) et fusionné
 *   dans `$config['metadata']`. C'est la métadonnée de la **session Stripe
 *   Checkout**, pas celle du PaymentIntent qu'elle crée : Stripe ne copie
 *   jamais automatiquement les métadonnées d'une session sur son
 *   PaymentIntent. `payment_intent_data.metadata` n'est renseigné que si le
 *   champ d'administration `transaction_metadata` est rempli
 *   (stripe.php:457-466) — un réglage unique pour toute l'installation,
 *   partagé par les deux marques dans la même ligne `sir_vikbooking_gpayments`,
 *   donc structurellement impropre à porter une métadonnée par marque.
 * - `statement_descriptor_suffix` n'est lu **nulle part** dans le greffon,
 *   ni dans la configuration de session ni dans les appels PaymentIntent
 *   directs des paiements différés (grep exhaustif, aucune occurrence).
 *   Impossible à poser depuis ce mu-plugin sans modifier le greffon, ce que
 *   la règle absolue n°1 interdit.
 *
 * Conséquence pour le critère de recette n°8 du brief (« le PaymentIntent
 * porte la métadonnée de marque et le libellé attendu ») : ce fichier pose
 * la métadonnée sur la session, pas sur le PaymentIntent, et ne pose aucun
 * libellé de relevé bancaire — il n'existe pas de levier pour le faire sans
 * toucher au greffon. Décision à prendre par Thomas, documentée dans
 * docs/briefs/constat-phase-4-paiement.md : accepter la métadonnée de
 * session comme suffisante, ou traiter le libellé de relevé comme hors
 * d'atteinte tant que le greffon n'est pas mis à jour ou remplacé.
 *
 * --- L'URL de retour --------------------------------------------------------
 *
 * `return_url`, `error_url` et `notify_url` sont construites par le cœur de
 * Vik sur `JUri::root()` → `home_url()`, donc déjà filtrées par
 * includes/url-rewrite.php pendant la même requête qui affiche la page de
 * paiement (constat-phase-0.md Q5, constat-reserve-paiement.md §0 écart 1) :
 * elles devraient déjà porter le bon hôte quand ce fichier s'exécute. La
 * fonction ci-dessous est un filet, pas le mécanisme : si elle doit
 * réellement corriger quelque chose, c'est que le filtrage d'hôte a un trou
 * ailleurs, d'où une alerte plutôt qu'une correction silencieuse.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'payment_before_begin_transaction_vikbooking', 'lme_brands_brand_payment_transaction', 10, 1 );

/**
 * @param array $args Indice 0 : l'objet de paiement (JPayment), par référence.
 */
function lme_brands_brand_payment_transaction( $args ) {
	$payment = isset( $args[0] ) ? $args[0] : null;

	if ( ! is_object( $payment ) || ! method_exists( $payment, 'isDriver' ) || ! $payment->isDriver( 'stripe' ) ) {
		return;
	}

	if ( ! method_exists( $payment, 'get' ) || ! method_exists( $payment, 'set' ) ) {
		lme_brands_log(
			'error',
			'payment_object_unexpected',
			"L'objet passé à payment_before_begin_transaction_vikbooking n'expose pas get()/set() : métadonnées et URL de paiement laissées telles quelles.",
			array( 'type' => get_class( $payment ) )
		);
		return;
	}

	$booking_id = (int) $payment->get( 'oid' );

	if ( $booking_id <= 0 ) {
		lme_brands_log(
			'error',
			'payment_booking_id_unreadable',
			"Identifiant de réservation illisible sur l'objet de paiement ('oid') : métadonnées et URL de paiement laissées telles quelles.",
			array()
		);
		return;
	}

	$config     = lme_brands_get_config();
	$room_ids   = lme_brands_booking_room_ids( $booking_id );
	$resolution = lme_brands_resolve_brand_for_rooms( $config, $room_ids );

	if ( 'ok' !== $resolution['status'] ) {
		// Même principe que mail-brand.php : jamais une marque devinée sur
		// la première chambre venue. Ici, cela veut dire ne rien poser : ni
		// métadonnée, ni correction d'URL, plutôt qu'une valeur arbitraire.
		lme_brands_log(
			'error',
			'payment_brand_undetermined',
			sprintf(
				"Marque indéterminée (%s) pour le paiement de la réservation #%d : métadonnées et URL de paiement laissées telles quelles, jamais devinées.",
				$resolution['reason'],
				$booking_id
			),
			array(
				'booking_id'    => $booking_id,
				'reason'        => $resolution['reason'],
				'room_ids'      => $resolution['room_ids'],
				'brand_keys'    => $resolution['brand_keys'],
				'unknown_rooms' => $resolution['unknown_rooms'],
			)
		);
		return;
	}

	$brand_key = $resolution['brand_key'];
	$brand     = $config['brands'][ $brand_key ];

	lme_brands_set_payment_metadata( $payment, $brand_key, $resolution['room_ids'], $booking_id );
	lme_brands_correct_payment_urls( $payment, $brand_key, $brand['host'], $booking_id );
}

/**
 * Pose la métadonnée de marque sur l'objet de paiement, sous la clé que
 * VikStripe fusionne dans la métadonnée de sa session Stripe Checkout
 * (stripe.php:454, `$this->get('tn_metadata', [])`). Voir la note en tête de
 * fichier : c'est la métadonnée de la session, pas celle du PaymentIntent.
 *
 * Fusionne plutôt qu'écrase, au cas où un chemin futur ou un autre greffon
 * aurait déjà posé une valeur sur cette même clé avant ce hook.
 *
 * @param object $payment
 * @param string $brand_key
 * @param int[]  $room_ids
 * @param int    $booking_id
 */
function lme_brands_set_payment_metadata( $payment, $brand_key, array $room_ids, $booking_id ) {
	$existing = $payment->get( 'tn_metadata', array() );
	$existing = is_array( $existing ) ? $existing : array();

	$payment->set(
		'tn_metadata',
		array_merge(
			$existing,
			array(
				'lme_brand'      => $brand_key,
				'lme_room_ids'   => implode( ',', $room_ids ),
				'lme_booking_id' => (string) $booking_id,
			)
		)
	);
}

/**
 * Vérifie l'hôte de `return_url`, `error_url` et `notify_url`, et le corrige
 * si besoin avant que VikStripe ne les lise pour construire `success_url` et
 * `cancel_url` (constat-reserve-paiement.md §0, écart 1). Une correction
 * réelle ici est un symptôme, pas une réparation anodine : elle veut dire
 * qu'une URL a atteint ce point sans porter le bon hôte alors qu'elle
 * devrait déjà le porter (voir la note en tête de fichier) — d'où une
 * alerte, jamais un silence.
 *
 * @param object $payment
 * @param string $brand_key
 * @param string $host
 * @param int    $booking_id
 */
function lme_brands_correct_payment_urls( $payment, $brand_key, $host, $booking_id ) {
	foreach ( array( 'return_url', 'error_url', 'notify_url' ) as $key ) {
		$original = $payment->get( $key );

		if ( ! is_string( $original ) || '' === $original ) {
			continue;
		}

		$corrected = lme_brands_swap_url_host( $original, $host );

		if ( $corrected === $original ) {
			continue;
		}

		$payment->set( $key, $corrected );

		lme_brands_log(
			'error',
			'payment_url_host_corrected',
			sprintf(
				"'%s' du paiement de la réservation #%d portait un hôte étranger à la marque '%s' et a été corrigée avant l'appel à Stripe : le filtrage d'hôte de la requête a un trou à investiguer, cette correction n'aurait jamais dû être nécessaire.",
				$key,
				$booking_id,
				$brand_key
			),
			array(
				'booking_id' => $booking_id,
				'brand_key'  => $brand_key,
				'field'      => $key,
				'original'   => $original,
				'corrected'  => $corrected,
			)
		);
	}
}
