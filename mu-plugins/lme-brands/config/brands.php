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
 * Décision de Thomas, 14 septembre 2026 (docs/briefs/brief-correctif-phase-2-filtrage.md,
 * chapitre 2) : toute chambre active dans Vik (`avail = 1`) porte une marque,
 * sans exception. Une chambre désactivée dans Vik (`avail = 0`) n'est servie
 * nulle part — ce fait vit dans Vik, pas ici (chapitre 3 du même brief : ne
 * pas dupliquer `sir_vikbooking_rooms.avail` dans une seconde liste tenue à
 * la main). Il n'y a donc plus de troisième statut « exclue » : une chambre
 * du registre est vendue ou elle n'y est pas.
 *
 * Les chambres 5 et 6 sont des chambres de test, désactivées dans Vik
 * (`avail = 0`), jamais vendues au public. Elles portent néanmoins une
 * marque comme les autres, pour que l'écran de santé les traite normalement
 * et que le statut 'unknown' redevienne une vraie anomalie (chambre créée
 * dans Vik que personne n'a enregistrée), pas un état qui se confond avec
 * une exclusion volontaire.
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

		// Chambres de test, désactivées dans Vik (avail = 0), jamais vendues
		// au public — brief-correctif-phase-2-filtrage.md, chapitre 4.
		// availability_group vérifié null pour les deux : aucune ligne pour
		// ces chambres dans sir_vikbooking_calendars_xref (14 septembre 2026).
		5 => array(
			'brand'              => 'linstantcle',
			// Nom Vik réel (sir_vikbooking_rooms.name) : « TEST room -
			// chambre de TEST (Cinema clone) ».
			'name'               => 'Chambre de test (clone Cinéma)',
			'experience'         => 'Chambre de test',
			'forfait'            => null,
			'availability_group' => null,
		),
		6 => array(
			// Affectation délibérée, pas une filiation : par sa nature la
			// chambre 6 est un clone de la Maisonnette, donc de L'Instant
			// Clé. Elle est affectée à Sexcape Room pour que chaque marque
			// dispose d'une chambre de test et que la recette exerce les
			// deux hôtes. Ne pas « corriger » vers linstantcle en croyant
			// réparer une erreur : c'est le choix voulu.
			'brand'              => 'sexcaperoom',
			// Nom Vik réel (sir_vikbooking_rooms.name) : « TEST room -
			// chambre de TEST (Maisonnette clone) ».
			'name'               => 'Chambre de test (clone Maisonnette)',
			'experience'         => 'Chambre de test',
			'forfait'            => null,
			'availability_group' => null,
		),

	),

);
