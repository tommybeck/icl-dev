<?php
/**
 * lme-brands — e-mails par marque. Chapitre 4.4 du brief, phase 3.
 *
 * Un seul chemin d'envoi, jamais deux. Vik n'offre aucun réglage pour
 * désactiver l'e-mail client d'une réservation confirmée, et son hook
 * d'envoi est un `do_action` qui ne peut rien annuler (constat-phase-0.md
 * Q2). On ne coupe donc rien et on n'émet rien de second : on **réécrit le
 * message en vol**, sur `vikbooking_before_send_booking_mail`, par les
 * mutateurs de `VBOMailWrapper`. Les pièces jointes iCal de Vik survivent,
 * et aucun doublon n'est possible.
 *
 * La marque ne se déduit jamais de l'hôte de la requête : un envoi peut
 * partir de l'administration, d'un cron ou d'une reprise de paiement. Elle
 * se résout depuis la réservation, donc depuis ses chambres — que le hook ne
 * transporte pas (constat-phase-0.md Q2 : `$rooms` existe dans la portée de
 * `sendBookingEmail()` mais ne figure pas dans les arguments de `trigger`).
 * D'où la relecture de `sir_vikbooking_ordersrooms` par `idorder`.
 *
 * Périmètre strict, §4.4 : le destinataire est le client, et la réservation
 * est directe (`channel` nul). Les messages liés aux canaux OTA ont leurs
 * propres règles et ne sont pas touchés.
 *
 * Ce que ce fichier ne fait pas : les rappels avant séjour. Ils sont produits
 * par la tâche planifiée `email_reminder` de Vik, qui **n'emprunte pas** ce
 * chemin d'envoi — établi, preuves à l'appui, en §4 de
 * docs/briefs/constat-phase-3-emails.md, avec le point d'accroche proposé.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Le message client -------------------------------------------------------

add_action( 'vikbooking_before_send_booking_mail', 'lme_brands_rewrite_booking_mail', 10, 3 );

/**
 * @param mixed $who     Élément du tableau `$for` de `sendBookingEmail()`.
 * @param mixed $booking Ligne de `sir_vikbooking_orders`, en tableau.
 * @param mixed $mail    L'instance `VBOMailWrapper` du message en partance.
 */
function lme_brands_rewrite_booking_mail( $who, $booking, $mail ) {
	if ( 'guest' !== lme_brands_mail_audience( $who ) ) {
		return;
	}

	if ( ! is_object( $mail ) || ! method_exists( $mail, 'setSender' ) || ! method_exists( $mail, 'getContent' ) ) {
		lme_brands_log(
			'error',
			'mail_wrapper_unexpected',
			"L'objet passé à vikbooking_before_send_booking_mail n'expose pas les mutateurs de VBOMailWrapper : message client laissé tel quel, donc potentiellement sous la mauvaise marque.",
			array( 'type' => is_object( $mail ) ? get_class( $mail ) : gettype( $mail ) )
		);
		return;
	}

	if ( ! is_array( $booking ) || empty( $booking['id'] ) ) {
		lme_brands_log(
			'error',
			'mail_booking_unreadable',
			"Réservation illisible dans vikbooking_before_send_booking_mail : message client laissé tel quel, donc potentiellement sous la mauvaise marque.",
			array( 'type' => gettype( $booking ) )
		);
		return;
	}

	if ( ! empty( $booking['channel'] ) ) {
		// Réservation OTA. §4.4 : « Ne pas toucher aux messages liés aux
		// canaux OTA, qui ont leurs propres règles. » Relevé du 15 septembre
		// 2026 : `channel` est NULL sur les 1079 réservations directes de la
		// base et renseigné sur les 679 réservations OTA, aucune ligne ne
		// portant la chaîne vide — `empty()` couvre donc les deux formes que la
		// colonne pourrait prendre, et correspond aux données réelles.
		return;
	}

	$config     = lme_brands_get_config();
	$booking_id = (int) $booking['id'];
	$room_ids   = lme_brands_booking_room_ids( $booking_id );

	// Chapitre 6 : une chambre absente du registre est une erreur journalisée
	// et alertée, à chaque résolution réelle. C'est ce passage-ci qui en fait
	// une, pas la résolution pure qui suit.
	foreach ( $room_ids as $room_id ) {
		lme_brands_resolve_room_or_log( $room_id );
	}

	$resolution = lme_brands_resolve_brand_for_rooms( $config, $room_ids );
	$identity   = lme_brands_mail_identity( $config, $resolution, isset( $booking['lang'] ) ? $booking['lang'] : null );

	if ( 'ok' !== $resolution['status'] ) {
		lme_brands_log(
			'error',
			'mail_brand_undetermined',
			sprintf(
				"Marque indéterminée (%s) pour le message client de la réservation #%d : envoi sous l'expéditeur neutre '%s', jamais sous une marque.",
				$resolution['reason'],
				$booking_id,
				$identity['sender_name']
			),
			array(
				'booking_id'    => $booking_id,
				'reason'        => $resolution['reason'],
				'room_ids'      => $resolution['room_ids'],
				'brand_keys'    => $resolution['brand_keys'],
				'unknown_rooms' => $resolution['unknown_rooms'],
			)
		);
	}

	lme_brands_apply_mail_identity( $mail, $identity, $booking_id );
}

/**
 * Pose l'identité sur le message et contrôle le résultat.
 *
 * @param object $mail
 * @param array  $identity   Voir lme_brands_mail_identity().
 * @param int    $booking_id
 */
function lme_brands_apply_mail_identity( $mail, array $identity, $booking_id ) {
	// null = garder l'adresse que Vik a posée. getSenderMail() se replie
	// lui-même sur `senderemail` de la configuration de Vik (wrapper.php:193).
	$sender_email = null !== $identity['sender_email'] ? $identity['sender_email'] : (string) $mail->getSenderMail();
	$sender_name  = (string) $identity['sender_name'];

	if ( '' === $sender_name || $sender_name === $sender_email ) {
		// Le registre est validé à son chargement, ce cas ne devrait pas
		// exister ; mais s'il existait, VBOMailWrapper effacerait le nom et
		// se replierait sur le titre global du site, donc sur l'autre marque.
		lme_brands_log(
			'error',
			'mail_sender_name_unusable',
			sprintf(
				"Nom d'expéditeur inutilisable pour la réservation #%d (vide, ou identique à l'adresse) : VBOMailWrapper se replierait sur le titre global du site.",
				$booking_id
			),
			array(
				'booking_id'   => $booking_id,
				'sender_email' => $sender_email,
				'sender_name'  => $sender_name,
			)
		);
	} else {
		$mail->setSender( $sender_email, $sender_name );
	}

	if ( null !== $identity['reply_to'] ) {
		$mail->setReply( $identity['reply_to'] );
	}

	$subject = null !== $identity['subject'] ? $identity['subject'] : (string) $mail->getSubject();
	$subject = lme_brands_apply_text_replacements( $subject, $identity['replacements'] );
	$mail->setSubject( $subject );

	$content = lme_brands_apply_text_replacements( (string) $mail->getContent(), $identity['replacements'] );

	if ( null !== $identity['signature_html'] ) {
		$content = lme_brands_inject_html_before_body_end( $content, $identity['signature_html'] );
	}

	$mail->setContent( $content );

	lme_brands_report_mail_leak( $subject . "\n" . $content, $identity, $booking_id );
}

/**
 * Contrôle de fuite : le message d'une marque ne doit nommer aucune autre.
 *
 * Quatrième cas d'alerte, ajouté aux trois du chapitre 6 du brief. Il mérite
 * une alerte et pas un simple avertissement parce qu'il constate en
 * production l'échec du critère de recette n°6, « le contenu et la signature
 * de cette marque » : un message déjà parti ne se rattrape pas, seule
 * l'alerte du lendemain évite qu'il parte mille fois.
 *
 * Il criera d'abord, et c'est normal : tant que le corps du gabarit unique de
 * Vik n'est pas séparé par marque (§5 du constat de phase 3), tout message
 * Sexcape Room contient encore des URL sur linstantcle.ch. L'alerte se tait
 * d'elle-même le jour où le corps est juste — ce qui en fait la vérification
 * automatique du critère n°6, et pas seulement un bruit.
 *
 * @param string $haystack
 * @param array  $identity
 * @param int    $booking_id
 */
function lme_brands_report_mail_leak( $haystack, array $identity, $booking_id ) {
	if ( empty( $identity['foreign_tokens'] ) ) {
		return;
	}

	$found = lme_brands_find_foreign_tokens( $haystack, $identity['foreign_tokens'] );

	if ( empty( $found ) ) {
		return;
	}

	lme_brands_log(
		'error',
		'mail_brand_leak',
		sprintf(
			"Le message client de la réservation #%d, envoyé sous la marque '%s', nomme encore une autre marque : %s.",
			$booking_id,
			$identity['brand_key'],
			implode( ', ', $found )
		),
		array(
			'booking_id' => $booking_id,
			'brand_key'  => $identity['brand_key'],
			'found'      => $found,
		)
	);
}

// --- La pièce jointe iCal ----------------------------------------------------

add_action( 'vikbooking_before_create_mail_ical', 'lme_brands_brand_mail_ical', 10, 3 );

/**
 * Marque la pièce jointe iCal du client.
 *
 * Addition au §4.4 du brief, qui ne parle que du message. Vik attache un
 * fichier `.ics` au message client quand `attachical` le permet — c'est le
 * cas ici, valeur `1` relevée le 15 septembre 2026 dans
 * `sir_vikbooking_config` — et il y écrit le titre global de l'installation
 * dans `SUMMARY`, dans `PRODID` et dans `LOCATION`
 * (`lib.vikbooking.php:5536`, `:5543` et `:5553`). Un client Sexcape Room qui ajoute son
 * séjour à son agenda y verrait donc « L'Instant Clé ». C'est une fuite de
 * marque visible, et elle survit à la réécriture du message : les pièces
 * jointes sont des chemins de fichier, pas du contenu.
 *
 * `onBeforeCreateMailIcalVikBooking` passe `$ics_str` **par référence**
 * (`lib.vikbooking.php:5563`, `[&$ics_str]` dans le tableau d'arguments),
 * contrairement à `onBeforeParseEmailTemplate` qui passe son gabarit par
 * valeur. C'est ce qui rend ce point d'accroche utilisable et l'autre non.
 *
 * Ce qui reste, et qu'on laisse : `PRODID` porte aussi `JUri::root()`, donc
 * l'URL de l'installation. Invisible pour le client dans tous les agendas
 * courants, et la réécrire demanderait de deviner quelle racine vaut pour
 * quel envoi — on ne devine pas.
 *
 * @param mixed  $recip   'customer' ou 'admin'.
 * @param mixed  $booking Ligne de réservation, ou tableau synthétique côté admin.
 * @param string $ics_str Contenu du fichier .ics, par référence.
 */
function lme_brands_brand_mail_ical( $recip, $booking, &$ics_str ) {
	if ( false !== stripos( (string) $recip, 'admin' ) ) {
		// La copie de l'administrateur garde l'identité de la maison.
		return;
	}

	if ( ! is_string( $ics_str ) || '' === $ics_str ) {
		return;
	}

	if ( ! is_array( $booking ) || empty( $booking['id'] ) || ! empty( $booking['channel'] ) ) {
		return;
	}

	if ( ! class_exists( 'VikBooking' ) || ! method_exists( 'VikBooking', 'getFrontTitle' ) ) {
		lme_brands_log(
			'warning',
			'ical_front_title_unavailable',
			sprintf( "Titre global de l'installation illisible : pièce jointe iCal de la réservation #%d laissée telle quelle.", (int) $booking['id'] ),
			array( 'booking_id' => (int) $booking['id'] )
		);
		return;
	}

	$config     = lme_brands_get_config();
	$booking_id = (int) $booking['id'];
	$room_ids   = lme_brands_booking_room_ids( $booking_id );
	$resolution = lme_brands_resolve_brand_for_rooms( $config, $room_ids );
	$identity   = lme_brands_mail_identity( $config, $resolution, isset( $booking['lang'] ) ? $booking['lang'] : null );

	// Marque indéterminée : l'étiquette neutre, jamais celle d'une marque.
	// Le message lui-même journalise et alerte sur ce même envoi
	// (`mail_brand_undetermined`) ; ne pas alerter deux fois pour un seul fait.
	$label = (string) $identity['label'];

	if ( '' === $label ) {
		return;
	}

	$front_title = (string) VikBooking::getFrontTitle();

	foreach ( array_unique( array( $front_title, strip_tags( $front_title ) ) ) as $needle ) {
		if ( '' === $needle || $needle === $label ) {
			continue;
		}

		$ics_str = str_replace( $needle, $label, $ics_str );
	}
}
