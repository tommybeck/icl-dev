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
		$available = lme_brands_room_is_available( $room_id );
		$resolved  = lme_brands_resolve_room_or_log( $room_id );
		$outcome   = lme_brands_evaluate_booking_room( $resolved, $available, $expected_brand );

		if ( 'allow' === $outcome ) {
			continue;
		}

		if ( 'refuse_unavailable' === $outcome ) {
			// Chapitre 5 du brief-correctif-phase-2-filtrage.md : une chambre
			// désactivée dans Vik (avail = 0) est refusée sans condition,
			// quelle que soit sa marque.
			lme_brands_log(
				'error',
				'unavailable_room_booking_attempt',
				sprintf(
					"Tentative de réservation de la chambre #%d, désactivée dans Vik (avail = 0), refusée avant insertion.",
					$room_id
				),
				array( 'room_id' => $room_id )
			);
		} elseif ( 'refuse_foreign_brand' === $outcome ) {
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

		// 'refuse_unknown_room' : déjà journalisé en erreur et alerté par
		// lme_brands_resolve_room_or_log() ci-dessus, code 'unknown_room'
		// (premier cas du chapitre 6, « chambre absente du registre »).
		lme_brands_reject_booking_attempt();
	}
}

/**
 * Interrupteur `avail` de Vik pour une chambre (sir_vikbooking_rooms.avail),
 * chapitre 3 et chapitre 5 du brief-correctif-phase-2-filtrage.md : ce fait
 * vit dans Vik, jamais dans le registre lme-brands.
 *
 * Lecture seule, en cache pour la durée de la requête : une seule requête
 * SQL même si plusieurs chambres sont réservées dans la même commande.
 *
 * @param int $room_id
 * @return bool|null true si active (avail = 1), false si désactivée
 *                    (avail = 0), null si la chambre est absente de Vik ou si
 *                    la table est inaccessible — jamais interprété comme un
 *                    refus : une chambre inconnue de Vik est déjà traitée
 *                    comme 'unknown' par lme_brands_resolve_room_or_log().
 */
function lme_brands_room_is_available( $room_id ) {
	static $avail_by_room = null;

	if ( null === $avail_by_room ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'vikbooking_rooms';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

		$avail_by_room = array();

		if ( $exists ) {
			$rows = $wpdb->get_results( "SELECT id, avail FROM {$table}" ); // Nom de table issu de $wpdb->prefix, aucune entrée utilisateur.

			foreach ( $rows as $row ) {
				$avail_by_room[ (int) $row->id ] = ( 1 === (int) $row->avail );
			}
		}
	}

	return isset( $avail_by_room[ (int) $room_id ] ) ? $avail_by_room[ (int) $room_id ] : null;
}

/**
 * Arrête la requête avant l'insertion. N'est jamais un retour normal de
 * fonction : `wp_die()` ne rend pas la main à l'appelant.
 *
 * Le visiteur lit le message dans la langue de l'hôte qui l'a refusé, pas
 * dans la langue du serveur (règle 7 du brief-correctif-phase-2-filtrage.md,
 * chapitre 7) : aucun domaine de traduction n'est chargé pour ce mu-plugin
 * (`load_muplugin_textdomain()` n'existe nulle part dans lme-brands), donc
 * `esc_html__( ..., 'lme-brands' )` sortirait toujours la chaîne source, en
 * français, y compris sur linstantcle.ch dont la langue native est
 * l'anglais. On résout donc la langue par la marque de l'hôte courant,
 * plutôt que de charger un domaine de traduction pour deux chaînes fixes :
 * le registre porte déjà `languages` par marque, c'est la même source de
 * vérité que partout ailleurs dans ce greffon (règle absolue n°5 de
 * CLAUDE.md, rien de configurable en dur).
 */
function lme_brands_reject_booking_attempt() {
	$strings = lme_brands_rejection_strings();

	wp_die(
		esc_html( $strings['message'] ),
		esc_html( $strings['title'] ),
		array( 'response' => 403 )
	);
}

/**
 * Message et titre de refus, dans la langue de la marque de l'hôte courant.
 * Le français est le repli si l'hôte ne résout vers aucune marque (ne
 * devrait jamais arriver ici : lme_brands_guard_booking_record() retourne
 * avant d'appeler ceci si l'hôte n'a pas de marque) ou si la première langue
 * déclarée de la marque n'est ni l'anglais ni le français.
 *
 * @return array{title: string, message: string}
 */
function lme_brands_rejection_strings() {
	$translations = array(
		'en' => array(
			'title'   => 'Booking refused',
			'message' => 'This booking cannot be created from this address.',
		),
		'fr' => array(
			'title'   => 'Réservation refusée',
			'message' => 'Cette réservation ne peut pas être créée depuis cette adresse.',
		),
	);

	$brand_key = lme_brands_current_request_brand_key();
	$config    = lme_brands_get_config();
	$languages = ( null !== $brand_key && isset( $config['brands'][ $brand_key ]['languages'] ) )
		? $config['brands'][ $brand_key ]['languages']
		: array();

	foreach ( $languages as $language ) {
		if ( isset( $translations[ $language ] ) ) {
			return $translations[ $language ];
		}
	}

	return $translations['fr'];
}
