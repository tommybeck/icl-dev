<?php
/**
 * Registre des marques et des chambres. Chapitre 4.2 du brief
 * docs/briefs/sexcape-room-reservation.md, carte de vérité au chapitre 2.
 *
 * Données seulement, aucune logique. Toute correspondance chambre, marque,
 * expérience ou hôte vit ici et nulle part ailleurs dans le code : ajouter
 * une expérience se fait en éditant ce seul fichier.
 *
 * Surcouche possible sans éditer ce fichier :
 *     add_filter( 'lme_brands', function ( array $config ) {
 *         // ... modifier $config ...
 *         return $config;
 *     } );
 *
 * Forme attendue, chaque marque (clé arbitraire et stable, jamais affichée
 * au client) :
 *   label                 nom d'affichage.
 *   host                  hôte de réservation de cette marque.
 *   sender_email          expéditeur des e-mails de cette marque.
 *   sender_name           nom affiché à côté de sender_email.
 *   reply_to              adresse de réponse.
 *   signature             signature en pied d'e-mail.
 *   confirmation_page_id  identifiant de la page de confirmation (phase 4).
 *   languages             langues actives pour cette marque.
 *
 * Forme attendue, chaque chambre (clé = identifiant Vik, entier) :
 *   brand                clé de marque ci-dessus.
 *   name                 nom de la chambre, tel qu'affiché et journalisé
 *                        partout — y compris "L'Entracte all inclusive"
 *                        pour la chambre 7.
 *   experience           rattachement pour l'ingestion Airtable ; les
 *                        chambres 1 et 7 partagent la même expérience
 *                        "L'Entracte", la 7 portant en plus le forfait
 *                        ci-dessous. Ne jamais créer d'enregistrement
 *                        Airtable dédié pour la 7.
 *   forfait               null, ou le nom du forfait qui différencie cette
 *                        chambre de son expérience partagée.
 *   availability_group    les chambres qui partagent un groupe se
 *                        bloquent mutuellement dans sir_vikbooking_calendars_xref ;
 *                        null pour une chambre indépendante.
 *
 * Les chambres 5 et 6 sont des chambres de test, jamais vendues, hors
 * affichage public et hors analyse (chapitre 2, chapitre 10). Elles sont
 * listées dans excluded_room_ids pour que l'écran de santé ne les signale
 * pas comme anomalie — mais elles ne résolvent jamais vers une marque :
 * toute tentative de le faire est une chambre exclue, journalisée en
 * avertissement, jamais un cas par défaut.
 *
 * expéditeur, adresse de réponse et signature ci-dessous sont des valeurs
 * de départ, cohérentes en forme mais pas encore arrêtées sur le fond :
 * la copie finale par marque est un livrable des chantiers D (habillage) et
 * B3 (e-mails), à confirmer avant l'ouverture de la phase 3.
 */

return array(

	'brands' => array(

		'linstantcle' => array(
			'label' => "L'Instant Clé",
			// À confirmer contre l'option `home` réelle du site avant la
			// phase 6. Un écart ici est sans risque pour la réécriture
			// d'URL (voir README, "pourquoi un host approximatif ne casse
			// rien"), mais doit être exact pour que l'écran de santé et les
			// phases suivantes s'appuient sur la bonne valeur.
			'host'                 => 'linstantcle.ch',
			'sender_email'         => 'reservations@linstantcle.ch',
			'sender_name'          => "L'Instant Clé",
			'reply_to'             => 'reservations@linstantcle.ch',
			'signature'            => "L'équipe L'Instant Clé",
			'confirmation_page_id' => 0, // à renseigner en phase 4.
			'languages'            => array( 'en', 'fr' ),
		),

		'sexcaperoom' => array(
			'label'                => 'Sexcape Room',
			'host'                 => 'reservation.sexcaperoom.ch',
			'sender_email'         => 'reservations@sexcaperoom.ch',
			'sender_name'          => 'Sexcape Room',
			'reply_to'             => 'reservations@sexcaperoom.ch',
			'signature'            => "L'équipe Sexcape Room",
			'confirmation_page_id' => 0, // à renseigner en phase 4.
			'languages'            => array( 'fr' ), // anglais et allemand tracés, non livrés au lancement.
		),

	),

	'rooms' => array(

		// Villa Entracte : 1, 7 et 10 se bloquent mutuellement.
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

		// Villa Aparté : 2 et 4 se bloquent mutuellement.
		2 => array(
			'brand'              => 'linstantcle',
			'name'               => "L'Aparté",
			'experience'         => "L'Aparté",
			'forfait'            => null,
			'availability_group' => 'villa-aparte',
		),
		4 => array(
			'brand'              => 'sexcaperoom',
			'name'               => 'Le Boudoir du Désir',
			'experience'         => 'Le Boudoir du Désir',
			'forfait'            => null,
			'availability_group' => 'villa-aparte',
		),

		// Chambres indépendantes : aucun blocage croisé.
		8 => array(
			'brand'              => 'linstantcle',
			'name'               => 'La Parenthèse',
			'experience'         => 'La Parenthèse',
			'forfait'            => null,
			'availability_group' => null,
		),
		9 => array(
			'brand'              => 'sexcaperoom',
			'name'               => "L'Indécent",
			'experience'         => "L'Indécent",
			'forfait'            => null,
			'availability_group' => null,
		),

	),

	// Chambres de test 5 et 6 : jamais vendues, hors affichage public, hors
	// analyse (chapitre 2). Déclarées ici pour que l'écran de santé ne les
	// traite pas comme une anomalie, sans jamais leur attribuer de marque.
	'excluded_room_ids' => array( 5, 6 ),

);
