<?php
/**
 * lme-brands — paiement par marque. Chapitre 4.5 du brief, phase 4.
 *
 * Greffé sur `payment_before_begin_transaction_vikbooking`
 * (.local/vikbooking/libraries/adapter/payment/payment.php:329,
 * JPayment::showPayment()), qui se déclenche avant beginTransaction() —
 * donc avant que VikStripe ne construise la configuration de la session
 * Stripe (constat-phase-0.md Q5).
 *
 * Correctif du 22 septembre 2026 (constat-fatal-page-paiement.md) : Vik
 * construit bien l'appel comme `do_action($hook, array(&$this))`, mais
 * `do_action()` du cœur WordPress (wp-includes/plugin.php) déballe
 * automatiquement ce motif — un tableau à un seul élément qui est un objet —
 * avant d'appeler les rappels, pour compatibilité ascendante avec le style
 * PHP4 de `array(&$this)`. **Le rappel reçoit donc l'objet de paiement
 * directement, jamais un tableau dont l'indice 0 le contiendrait.** La
 * version précédente de ce fichier affirmait l'inverse (« le rappel reçoit
 * un tableau ») : c'était la lecture littérale du seul code de Vik,
 * correcte pour `payment.php:329` pris isolément mais fausse une fois
 * WordPress ajouté, et elle a fait planter tout paiement Stripe, les deux
 * marques, les deux environnements, dès l'activation de ce mu-plugin —
 * `isset( $args[0] )` sur un objet qui n'implémente pas `ArrayAccess` lève
 * une `Error` fatale sous PHP 8. Ne pas reproduire cette prémisse ailleurs
 * dans ce plugin : `constat-phase-0.md` §Q5 porte la même correction.
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
 * @param object $payment L'objet de paiement (JPayment), livré directement
 *                         par do_action() — voir la note en tête de fichier.
 */
function lme_brands_brand_payment_transaction( $payment ) {
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

	lme_brands_set_payment_metadata( $payment, $brand_key, $resolution['room_ids'], $booking_id );
	lme_brands_correct_payment_urls( $payment, $brand_key, lme_brands_current_raw_http_host(), $booking_id );
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
 * La cible est l'hôte HTTP **réel** de la requête courante
 * (`lme_brands_current_raw_http_host()`), jamais `$config['brands'][$brand_key]['host']`,
 * l'hôte déclaré au registre. Même raisonnement et même preuve que le
 * correctif du 22 septembre 2026 à `includes/url-rewrite.php`
 * (constat-correctif-url-rewrite.md) : en production, l'hôte de la marque
 * résolue et l'hôte réel de la requête sont égaux par construction, donc
 * viser l'un ou l'autre ne change rien. Mais cette fonction s'exécute
 * pendant la même requête que le rendu de la page de paiement — la requête
 * que `url-rewrite.php` filtre déjà vers l'hôte réel — donc sous le levier
 * de préproduction B8, viser le registre y referait exactement la fuite du
 * 21 septembre : `return_url`, `error_url` et `notify_url` seraient
 * « corrigées » de l'hôte réel de la préproduction vers l'hôte de
 * *production* de la marque. Cette fonction n'a jamais pu s'exécuter avant
 * ce correctif — la fatale de `constat-fatal-page-paiement.md` l'en
 * empêchait — donc ce défaut n'a jamais été observé en pratique, mais rien
 * dans sa logique ne l'aurait empêché de se produire.
 *
 * @param object      $payment
 * @param string      $brand_key
 * @param string|null $host       Hôte HTTP réel de la requête courante, ou
 *                                 null si indisponible.
 * @param int         $booking_id
 */
function lme_brands_correct_payment_urls( $payment, $brand_key, $host, $booking_id ) {
	if ( ! is_string( $host ) || '' === $host ) {
		lme_brands_log(
			'error',
			'payment_url_host_unavailable',
			sprintf(
				"Hôte HTTP réel introuvable pour la réservation #%d : impossible de vérifier ou de corriger l'hôte de 'return_url', 'error_url' et 'notify_url'.",
				$booking_id
			),
			array(
				'booking_id' => $booking_id,
				'brand_key'  => $brand_key,
			)
		);
		return;
	}

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
