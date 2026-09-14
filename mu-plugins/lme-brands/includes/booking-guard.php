<?php
/**
 * lme-brands — garde sur la création de réservation. Chapitre 4.3, couche 2.
 *
 * Le seul mécanisme réellement opposable (constat-phase-0.md Q4 : « ce
 * n'est pas une ceinture de sécurité : c'est le seul mécanisme réellement
 * opposable »). La présentation (includes/room-filter.php) cède devant un
 * paramètre GET fabriqué à la main ; cette garde, non — elle ne dépend
 * d'aucun attribut de shortcode.
 *
 * Se greffe sur `vikbooking_before_create_booking_record`, qui se déclenche
 * juste avant l'insertion de la commande, sur les deux branches de
 * `saveorder()` — réservation confirmée immédiatement et réservation en
 * attente de paiement (Vik Booking 1.8.14, site/controller.php:1114 et
 * :1512, mêmes arguments sur les deux branches).
 *
 * Piège à connaître : ce hook est un `do_action_ref_array`
 * (constat-phase-0.md Q2 : la même mécanique que
 * `vikbooking_before_send_booking_mail`), donc un `do_action` WordPress
 * ordinaire — il ne peut pas annuler l'insertion par une valeur de retour.
 * Refuser signifie donc arrêter la requête ici même, avant que le
 * contrôleur n'atteigne `$dbo->insertObject()` quelques lignes plus bas.
 * Même technique que VikStripe pour son propre hook de redirection
 * (.local/wp-vikstripe/vikbooking/stripe.php:223-242 : `redirect()` puis
 * `exit` à l'intérieur du rappel).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'vikbooking_before_create_booking_record', 'lme_brands_guard_booking_record', 10, 5 );

/**
 * @param object $booking_record Propriétés de la commande, pas encore insérée.
 * @param array  $rooms          Chambres réservées ; chaque élément porte
 *                                au moins la clé `id`.
 * @param array  $tars
 * @param array  $selopt
 * @param array  $arrpeople
 */
function lme_brands_guard_booking_record( $booking_record, $rooms, $tars, $selopt, $arrpeople ) {
	$expected_brand = lme_brands_current_request_brand_key();

	if ( null === $expected_brand ) {
		// Hôte qui n'est celui d'aucune marque du registre (staging, accès
		// direct par IP...) : aucun contexte de marque à faire respecter.
		// On ne devine jamais une marque, y compris ici.
		return;
	}

	foreach ( lme_brands_extract_room_ids( $rooms ) as $room_id ) {
		$resolved = lme_brands_resolve_room_or_log( $room_id );

		if ( 'excluded' === $resolved['status'] ) {
			// Chambres de test 5 et 6 : la réservation factice de bout en
			// bout (plan-de-marche.md, chantier E, point E5) les réserve
			// délibérément sur les deux hôtes, en paiement hors ligne. Déjà
			// journalisée en avertissement par lme_brands_resolve_room_or_log().
			continue;
		}

		if ( 'ok' === $resolved['status'] && $resolved['brand_key'] === $expected_brand ) {
			continue;
		}

		if ( 'ok' === $resolved['status'] ) {
			// Chambre connue, mais d'une autre marque que l'hôte de la
			// requête : c'est exactement le troisième cas que le chapitre 6
			// du brief exige d'alerter sans exception, « tentative de
			// réservation d'une chambre étrangère à l'hôte ».
			lme_brands_log(
				'error',
				'foreign_room_booking_attempt',
				sprintf(
					"Tentative de réservation de la chambre #%d (marque '%s') sur l'hôte de la marque '%s', refusée avant insertion.",
					$room_id,
					$resolved['brand_key'],
					$expected_brand
				),
				array(
					'room_id'        => $room_id,
					'resolved_brand' => $resolved['brand_key'],
					'expected_brand' => $expected_brand,
				)
			);
		}

		// Statut 'unknown' : déjà journalisé en erreur et alerté par
		// lme_brands_resolve_room_or_log() ci-dessus, code 'unknown_room'
		// (premier cas du chapitre 6, « chambre absente du registre »).
		// Statut 'ok' mais marque étrangère : journalisé juste au-dessus.
		// Dans les deux cas, on refuse.
		lme_brands_reject_booking_attempt();
	}
}

/**
 * Arrête la requête avant l'insertion. N'est jamais un retour normal de
 * fonction : `wp_die()` ne rend pas la main à l'appelant.
 */
function lme_brands_reject_booking_attempt() {
	wp_die(
		esc_html__( 'Cette réservation ne peut pas être créée depuis cette adresse.', 'lme-brands' ),
		esc_html__( 'Réservation refusée', 'lme-brands' ),
		array( 'response' => 403 )
	);
}
