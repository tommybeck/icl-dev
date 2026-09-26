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
 * --- Chantier B5 : la source des rappels avant séjour -----------------------
 *
 * Les rappels avant séjour n'empruntent pas ce chemin d'envoi. La tâche
 * planifiée `email_reminder` de Vik n'appelle jamais `sendBookingEmail()` :
 * elle construit son propre `VBOMailWrapper` et appelle le mailer
 * directement (`jv_helper.php:123-137`). `vikbooking_before_send_booking_mail`
 * ne les voit donc pas — établi ligne à ligne en §4 de
 * docs/briefs/constat-phase-3-emails.md.
 *
 * D'où le second point d'accroche, en bas de ce fichier :
 * `vikbooking_before_send_mail`, le seul hook que **tous** les e-mails de Vik
 * traversent, rappels compris. Il pose l'expéditeur, le nom affiché et
 * l'adresse de réponse, et rien d'autre : le contenu du rappel vit dans le
 * gabarit de la tâche planifiée, côté Vik, et se sépare par marque là-bas
 * (chantier F).
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
	// Ce message est passé par le hook métier, qui voit sa réservation et son
	// destinataire sans avoir à les deviner. Quelle que soit la décision prise
	// ci-dessous — y compris « ne rien faire », pour l'administrateur ou pour
	// une réservation OTA —, elle est prise. Le point d'accroche générique en
	// bas de ce fichier voit passer le même objet quelques instants plus tard,
	// dans `prepare()` : il ne doit ni la refaire, ni la contredire.
	lme_brands_mail_wrapper_decided( $mail, true );

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
 * Pose l'expéditeur et le nom affiché, et refuse une identité bancale.
 *
 * Partagée par les deux points d'accroche de ce fichier : le hook métier, qui
 * réécrit tout le message client, et le hook générique du chantier B5, qui y
 * ajoute seulement l'adresse de réponse. Le piège de `setSender()` n'a ainsi
 * qu'un seul gardien.
 *
 * @param object $mail
 * @param array  $identity   Voir lme_brands_mail_identity().
 * @param int    $booking_id
 * @return bool True si l'expéditeur a été posé.
 */
function lme_brands_apply_mail_sender( $mail, array $identity, $booking_id ) {
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

		return false;
	}

	$mail->setSender( $sender_email, $sender_name );

	return true;
}

/**
 * Pose l'identité sur le message et contrôle le résultat.
 *
 * @param object $mail
 * @param array  $identity   Voir lme_brands_mail_identity().
 * @param int    $booking_id
 */
function lme_brands_apply_mail_identity( $mail, array $identity, $booking_id ) {
	lme_brands_apply_mail_sender( $mail, $identity, $booking_id );

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

// --- La source de tous les autres e-mails de Vik, chantier B5 ----------------

add_action( 'vikbooking_before_send_mail', 'lme_brands_brand_mail_source', 10, 1 );

/**
 * Pose l'expéditeur, le nom affiché et l'adresse de réponse de la marque sur
 * un message que le hook métier n'a pas vu — au premier chef le rappel avant
 * séjour.
 *
 * `vikbooking_before_send_mail` (`onBeforeSendMail`, émis par
 * `platform/org/wordpress/mailer.php:52`) est le seul point d'accroche que
 * **tous** les e-mails de Vik traversent. Il est déclenché depuis `prepare()`,
 * donc avant `$service->send($mail)` (`mailer.php:34-37`) : la réécriture y est
 * encore possible. Son défaut est connu et c'est le seul : il ne transporte
 * aucun contexte métier, ni réservation, ni chambre.
 *
 * --- Ce qui est posé, et ce qui ne l'est pas --------------------------------
 *
 * **L'expéditeur, le nom affiché et l'adresse de réponse, rien d'autre.**
 * L'adresse de réponse s'aligne sur l'expéditeur de la marque, comme pour le
 * message client : décision de Thomas du 26 septembre 2026, qui lève la
 * réserve de B5 — la boîte `reservations@` de chaque marque existe depuis
 * C2. Vik la passe en quatrième argument de `sendMail()`
 * (`email_reminder.php:660`), elle arrive dans le wrapper par `bind()`
 * (`jv_helper.php:127`), et le service PHPMailer ne la lit qu'à l'envoi,
 * après ce hook (`phpmailer.php:52-56`) : `setReply()` ici suffit. Marque
 * mêlée ou indéterminée : l'identité neutre n'en porte pas, l'adresse de Vik
 * reste — voir lme_brands_mail_source_reply_to().
 *
 * Ni objet, ni corps. Le corps d'un rappel vient du gabarit de la tâche
 * planifiée, saisi dans l'administration de Vik, où vivent aujourd'hui le
 * logo et le titre L'Instant Clé, inconditionnels : c'est là qu'ils se
 * séparent par marque, par les textes conditionnels natifs, et c'est le
 * chantier F. Y toucher depuis ici reviendrait à substituer des chaînes dans
 * un texte dont on ne sait rien — §5 du constat de phase 3 explique pourquoi
 * ce n'est pas la bonne voie pour le message client, et la raison vaut mot
 * pour mot ici.
 *
 * **Pas de contrôle de fuite sur ce chemin non plus, et c'est une décision.**
 * Le corps du rappel nomme L'Instant Clé, on le sait, c'est inventorié en
 * chantier F : une alerte à chaque envoi répéterait un fait déjà connu au
 * lieu d'en signaler un nouveau. Le jour où F sera fait, brancher ici le
 * `lme_brands_report_mail_leak()` du message client rendra ce travail
 * vérifiable en continu, comme il l'est pour la confirmation. Non branché
 * aujourd'hui, volontairement.
 *
 * --- Comment la réservation est retrouvée, et pourquoi ce n'est pas deviner -
 *
 * L'émetteur dépose sa réservation dans `VikBookingHelperConditionalRules`
 * avant de composer son message — c'est le mécanisme par lequel Vik transporte
 * lui-même sa réservation jusqu'au moteur de textes conditionnels
 * (`email_reminder.php:729-731`, et sept autres émetteurs dont
 * `lib.vikbooking.php:5734`). Le magasin est un `protected static` avec un
 * accesseur public `get()` (`conditional_rules.php:360`), et l'ordre est le
 * bon : le dépôt précède le déclenchement.
 *
 * Mais Vik **ne vide jamais** ce magasin entre deux envois. D'où le
 * recoupement de `lme_brands_mail_booking_matches_recipients()` : on n'y croit
 * que si le client de la réservation déposée est parmi les destinataires du
 * message. Sinon on ne touche à rien, et le message part comme avant ce
 * plugin — jamais sous une marque devinée.
 *
 * --- Le périmètre réel, qui dépasse le rappel -------------------------------
 *
 * Ce hook voit tous les e-mails de Vik, et le recoupement en retient tous ceux
 * qui sont adressés au client d'une réservation directe dont la marque se
 * résout : le rappel avant séjour (tâche `email_reminder`, la seule publiée),
 * mais aussi le rappel de pré-enregistrement, les factures, la messagerie en
 * lot et le message envoyé à la main depuis l'écran d'une réservation. Ce
 * n'est pas un débordement : **tous** partent aujourd'hui sous
 * `L'Instant Clé`, y compris pour une chambre Sexcape Room, et la marque de
 * leur réservation est la bonne pour chacun. La recette, elle, porte sur le
 * rappel avant séjour : c'est le message qui part toutes les heures.
 *
 * Restent dehors, et c'est voulu : les messages qui ne vont pas au client
 * d'une réservation (notifications à l'administrateur, devis sans réservation
 * déposée), ceux d'une réservation OTA (§4.4 du brief), et ceux que le hook
 * métier a déjà tranchés.
 *
 * @param mixed $mail L'instance `VBOMailWrapper` du message en partance.
 */
function lme_brands_brand_mail_source( $mail ) {
	if ( ! is_object( $mail )
		|| ! method_exists( $mail, 'setSender' )
		|| ! method_exists( $mail, 'getSenderMail' )
		|| ! method_exists( $mail, 'getRecipient' )
		|| ! method_exists( $mail, 'setReply' )
		|| ! method_exists( $mail, 'getReply' ) ) {
		lme_brands_log(
			'error',
			'mail_wrapper_unexpected',
			"L'objet passé à vikbooking_before_send_mail n'expose pas les mutateurs de VBOMailWrapper : message laissé tel quel, donc potentiellement sous la mauvaise marque.",
			array( 'type' => is_object( $mail ) ? get_class( $mail ) : gettype( $mail ) )
		);
		return;
	}

	if ( lme_brands_mail_wrapper_decided( $mail ) ) {
		// Le hook métier a déjà statué sur ce message.
		return;
	}

	$booking = lme_brands_mail_context_booking();

	if ( null === $booking ) {
		return;
	}

	if ( ! lme_brands_mail_booking_matches_recipients( $booking, $mail->getRecipient() ) ) {
		// État périmé, ou message qui ne va pas au client de cette
		// réservation : on ne devine pas. Silence volontaire — c'est le cas
		// ordinaire de la plupart des e-mails de Vik, pas une anomalie.
		return;
	}

	if ( ! empty( $booking['channel'] ) ) {
		// Réservation OTA, §4.4 du brief, même limite que pour le message
		// client. La tâche de rappel les notifie pourtant (`ota_res = 1`) :
		// c'est une décision du brief, à rouvrir le jour où les chambres
		// Sexcape Room seront distribuées en OTA.
		return;
	}

	$config     = lme_brands_get_config();
	$booking_id = (int) $booking['id'];
	$room_ids   = lme_brands_booking_room_ids( $booking_id );

	// Chapitre 6 : une chambre absente du registre est une erreur journalisée
	// et alertée, à chaque résolution réelle.
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
				"Marque indéterminée (%s) pour un message de Vik adressé au client de la réservation #%d : envoi sous l'expéditeur neutre '%s', jamais sous une marque, et adresse de réponse de Vik gardée (%s).",
				$resolution['reason'],
				$booking_id,
				$identity['sender_name'],
				(string) $mail->getReply()
			),
			array(
				'booking_id'    => $booking_id,
				'reason'        => $resolution['reason'],
				'room_ids'      => $resolution['room_ids'],
				'brand_keys'    => $resolution['brand_keys'],
				'unknown_rooms' => $resolution['unknown_rooms'],
				// Même code que pour le message client : c'est la même
				// situation. Ce qui change, c'est le chemin d'envoi, et c'est
				// ce que porte cette clé.
				'chemin'        => 'vikbooking_before_send_mail',
				'reply_to_kept' => $mail->getReply(),
			)
		);
	}

	// Rien n'est marqué ici, et c'est délibéré : `prepare()` ne se déclenche
	// qu'une fois par envoi, et si le même message repassait, poser la même
	// identité une seconde fois ne changerait rien. Marquer coûterait, sur le
	// repli `SplObjectStorage`, de retenir en mémoire chacun des messages d'une
	// exécution de la tâche de rappel, qui en envoie autant qu'il y a
	// d'arrivées à deux jours.
	$sender_applied = lme_brands_apply_mail_sender( $mail, $identity, $booking_id );
	$reply_to       = lme_brands_mail_source_reply_to( $identity, $sender_applied );

	if ( null !== $reply_to ) {
		$mail->setReply( $reply_to );
	}
}

/**
 * La réservation déposée par l'émetteur du message, ou null.
 *
 * Retourner null n'est jamais une devinette et n'est jamais silencieux au
 * mauvais sens : un magasin vide veut dire « ce message ne concerne aucune
 * réservation », ce qui est le cas ordinaire pour une bonne part des e-mails
 * de Vik. Seule l'absence du mécanisme lui-même — Vik renommé, déplacé ou
 * profondément modifié — est une anomalie, et celle-là est journalisée.
 *
 * @return array|null
 */
function lme_brands_mail_context_booking() {
	if ( ! class_exists( 'VikBooking' ) || ! method_exists( 'VikBooking', 'getConditionalRulesInstance' ) ) {
		lme_brands_log(
			'error',
			'mail_context_unavailable',
			"VikBooking::getConditionalRulesInstance() est introuvable : impossible de rattacher un e-mail de Vik à sa réservation, les rappels partent donc sous l'expéditeur global de l'installation.",
			array()
		);
		return null;
	}

	$rules = VikBooking::getConditionalRulesInstance();

	if ( ! is_object( $rules ) || ! method_exists( $rules, 'get' ) ) {
		lme_brands_log(
			'error',
			'mail_context_unavailable',
			"Le magasin de VikBookingHelperConditionalRules n'expose plus get() : impossible de rattacher un e-mail de Vik à sa réservation, les rappels partent donc sous l'expéditeur global de l'installation.",
			array( 'type' => is_object( $rules ) ? get_class( $rules ) : gettype( $rules ) )
		);
		return null;
	}

	$booking = $rules->get( 'booking' );

	return is_array( $booking ) ? $booking : null;
}

/**
 * Mémoire des messages déjà tranchés par le hook métier, pour la durée de la
 * requête.
 *
 * `sendBookingEmail()` déclenche `vikbooking_before_send_booking_mail`, puis
 * appelle le mailer, qui déclenche `vikbooking_before_send_mail` sur **le même
 * objet** (`lib.vikbooking.php:6502` puis `:6504`, `mailer.php:34-37`). Sans
 * cette mémoire, le message client serait traité deux fois, et le message de
 * l'administrateur — que le hook métier laisse délibérément tranquille —
 * pourrait être repris par le hook générique.
 *
 * Seul le hook métier marque, et il ne voit qu'une poignée de messages par
 * requête. `VBOMailWrapper` est `final` : on ne peut pas lui ajouter de
 * propriété, la marque doit donc vivre à côté. Et jamais par `spl_object_id()`, qui est
 * réattribué après libération de l'objet : un identifiant réutilisé ferait
 * passer un rappel pour un message déjà traité, et ce rappel partirait sous la
 * mauvaise marque. `WeakMap` ne retient pas ses clés, ce qui convient à un
 * envoi en lot ; `SplObjectStorage`, qui les retient, sert de repli sur une
 * installation antérieure à PHP 8.0. Les deux répondent à `isset()` et à
 * l'affectation par index, donc le code est le même.
 *
 * @param mixed $mail L'instance `VBOMailWrapper`.
 * @param bool  $mark True pour marquer le message, false pour interroger.
 * @return bool
 */
function lme_brands_mail_wrapper_decided( $mail, $mark = false ) {
	static $decided = null;

	if ( ! is_object( $mail ) ) {
		return false;
	}

	if ( null === $decided ) {
		$decided = class_exists( 'WeakMap' ) ? new WeakMap() : new SplObjectStorage();
	}

	if ( $mark ) {
		$decided[ $mail ] = true;

		return true;
	}

	return isset( $decided[ $mail ] );
}
