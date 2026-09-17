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
 *   confirmation_page_id  identifiant de la page de confirmation. Vaut 845
 *                        pour les deux marques : constat-perimetre-tunnel.md
 *                        §4 (A3) établit qu'une seule page WordPress porte
 *                        les vues `booking`/`precheckin`/`revstay` et la
 *                        tâche `notifypayment` sur les quatre hôtes de cette
 *                        installation — détail, paiement, retour Stripe,
 *                        confirmation et erreur y aboutissent tous. Ce n'est
 *                        pas une page dupliquée par marque : c'est la même
 *                        page, dont l'apparence varie déjà par hôte (chantier
 *                        D, themes/astra-child/inc/sexcaperoom-tunnel.php).
 *   languages             langues actives pour cette marque.
 *   appearance            optionnel, chantier D (habillage du tunnel) — voir
 *                        le bloc dédié plus bas pour la forme exacte.
 *   mail                  optionnel, phase 3 (e-mails par marque) — voir le
 *                        bloc dédié plus bas.
 *
 * Forme attendue, l'identité neutre `neutral` (obligatoire, phase 3) :
 *   label         nom affiché quand aucune marque n'est nommable, utilisé
 *                 aussi dans la pièce jointe iCal.
 *   sender_name   nom d'expéditeur correspondant.
 *   sender_email  adresse d'expéditeur, ou null pour garder celle que Vik a
 *                 posée (`senderemail` de sa configuration).
 *   reply_to      adresse de réponse, ou null pour garder celle de Vik.
 *   subject       objet du message, par langue.
 *
 * Forme attendue, le bloc `mail` d'une marque (phase 3, chapitre 4.4) :
 *   subject         objet du message par langue ; table vide ou absente =
 *                   garder l'objet natif de Vik.
 *   replacements    substitutions littérales appliquées à l'objet et au
 *                   corps, dans l'ordre de déclaration.
 *   signature_html  fragment HTML inséré avant `</body>`, ou null.
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
 * expéditeur, adresse de réponse et signature ci-dessous restent des valeurs
 * de départ, cohérentes en forme mais pas arrêtées sur le fond.
 *
 * **Point bloquant pour le déploiement de la phase 3.** Aujourd'hui, tout
 * e-mail client de cette installation part de `info@maisonnette-enchantee.ch`
 * (`senderemail` de `sir_vikbooking_config`, relevé du 15 septembre 2026).
 * La phase 3 remplace cette adresse par `sender_email` de la marque résolue,
 * pour les deux marques, L'Instant Clé comprise. Avant de déployer, les
 * quatre adresses ci-dessous (`sender_email` et `reply_to` de chaque marque)
 * doivent exister en tant que boîtes et être autorisées à émettre par le
 * service d'envoi transactionnel du chantier C2 : sans quoi la réponse d'un
 * client rebondit, et c'est une régression sur une marque qui fonctionne.
 * Voir §6 de docs/briefs/constat-phase-3-emails.md.
 *
 * Chantier D (habillage du tunnel), chapitre 4.1 du brief principal et
 * docs/briefs/brief-habillage-tunnel.md en entier. Clé optionnelle
 * `appearance`, présente uniquement sur les marques dont le tunnel a besoin
 * d'un habillage propre — aujourd'hui `sexcaperoom` seule, `linstantcle`
 * gardant l'apparence Astra existante. Données seulement, comme le reste de
 * ce fichier : c'est le thème enfant (themes/astra-child/) qui lit cette
 * clé et construit le CSS, jamais l'inverse.
 *   colors   couleurs `--srlm-*` relevées le 9 septembre 2026 dans le CSS
 *            personnalisé du kit Elementor de sexcaperoom.ch, plus les deux
 *            couleurs d'erreur tranchées avec Thomas le 12 septembre 2026.
 *   radii    rayons `--srlm-arc*`, signature « haut arrondi, bas presque
 *            droit » du site.
 *   fonts    par rôle ('serif', 'sans', 'voix') : `family` (nom nu, pour le
 *            descripteur font-family de @font-face — doit correspondre au
 *            premier nom de `stack`), `stack` (la pile CSS complète, avec
 *            repli) et `faces` (liste de fichiers `.woff2`, chacun avec
 *            `weight`, `style`, `file`). Fichiers servis par l'installation
 *            linstantcle.ch elle-même (contrainte d'implémentation n°4 du
 *            brief d'habillage : aucune dépendance à un domaine tiers).
 *            Chemins relatifs à themes/astra-child/assets/fonts/sexcaperoom/.
 *   shell    largeur et gouttière communes à toutes les pages du tunnel.
 *   logo     chemin d'un logo, ou null. Décision de Thomas, 15 septembre
 *            2026 (docs/briefs/brief-habillage-tunnel.md, « État au 12
 *            septembre 2026 ») : aucun logo ni favicon n'existe aujourd'hui
 *            sur sexcaperoom.ch, seulement un nom en toutes lettres. Le
 *            tunnel affiche donc ce nom en typographie (`--srlm-serif`),
 *            comme le fait déjà le pied de page du site — jamais une
 *            supposition de chemin d'image. Le jour où un logo existe,
 *            renseigner ce champ suffit : aucune ligne de CSS à changer.
 *   favicon  chemin d'un favicon, ou null. Même décision, même raison.
 */

return array(

	/**
	 * Identité neutre, phase 3. Elle sert quand la marque d'une réservation
	 * ne peut pas être établie : chambre absente du registre, ou réservation
	 * qui mêle deux marques (composable à la main dans l'administration de
	 * Vik, la garde de réservation l'interdisant depuis le tunnel).
	 *
	 * §4.4 du brief : « Si la marque reste indéterminée, l'envoi part sous un
	 * expéditeur neutre, journalise un avertissement et alerte. Jamais sous la
	 * mauvaise marque. »
	 *
	 * `sender_email` et `reply_to` valent null, et c'est un choix, pas un
	 * oubli. Relevé du 15 septembre 2026, en lecture seule : Vik est configuré
	 * avec `senderemail` = `info@maisonnette-enchantee.ch`
	 * (`sir_vikbooking_config`), une adresse qui ne nomme ni L'Instant Clé ni
	 * Sexcape Room et dont la boîte existe, puisqu'elle sert déjà. Garder
	 * cette adresse et ne remplacer que le nom affiché donne un expéditeur qui
	 * ne nomme aucune marque, sans inventer une adresse dont les réponses
	 * rebondiraient. Le jour où le service d'envoi transactionnel du chantier
	 * C2 est en place, renseigner ici une adresse dédiée est le geste attendu.
	 */
	'neutral' => array(
		'label'        => 'Réservations',
		'sender_name'  => 'Réservations',
		'sender_email' => null,
		'reply_to'     => null,
		'subject'      => array(
			// L'allemand figure ici parce qu'il est déjà une langue réelle de
			// l'installation : 453 des 1758 réservations de la base portent
			// `lang` = `de-CH` ou `de-DE` (relevé du 15 septembre 2026), alors
			// que `languages` de L'Instant Clé ne déclare que `en` et `fr`.
			// Écart signalé en §7 de docs/briefs/constat-phase-3-emails.md ;
			// le corriger est une décision de marque, pas un geste de code.
			'fr' => 'Votre réservation',
			'en' => 'Your reservation',
			'de' => 'Ihre Reservierung',
		),
	),

	'brands' => array(

		'linstantcle' => array(
			'label' => "L'Instant Clé",
			// Vérifié le 15 septembre 2026, en lecture seule : `sir_options`
			// porte `home` = `https://linstantcle.ch` et `siteurl` =
			// `https://linstantcle.ch`. La valeur ci-dessous est donc exacte,
			// et non plus approximative comme le notait la phase 1.
			'host'                 => 'linstantcle.ch',
			'sender_email'         => 'reservations@linstantcle.ch',
			'sender_name'          => "L'Instant Clé",
			'reply_to'             => 'reservations@linstantcle.ch',
			'signature'            => "L'équipe L'Instant Clé",
			// Page partagée avec Sexcape Room, voir la note de tête de
			// fichier sur 'confirmation_page_id' — constat-perimetre-tunnel.md
			// §4 (A3), relevé le 12 septembre 2026.
			'confirmation_page_id' => 845,
			'languages'            => array( 'en', 'fr' ),

			'mail' => array(
				// Objet laissé à Vik. Il le compose avec le titre global de
				// l'installation (`VBOMAILSUBJECT` + `fronttitle`), et ce
				// titre vaut exactement « L'Instant Clé » (relevé du
				// 15 septembre 2026 dans `sir_vikbooking_texts`) : l'objet
				// natif nomme donc déjà la bonne marque pour celle-ci. Rien à
				// remplacer, donc rien de remplacé — phase 3 ne change pas un
				// e-mail qui est déjà juste.
				'subject'        => array(),
				// Aucune substitution : le gabarit global d'e-mail est celui
				// de L'Instant Clé, il n'a rien d'étranger à corriger.
				'replacements'   => array(),
				// Le gabarit porte déjà son propre pied de page.
				'signature_html' => null,
			),
		),

		'sexcaperoom' => array(
			'label'                => 'Sexcape Room',
			'host'                 => 'reservation.sexcaperoom.ch',
			'sender_email'         => 'reservations@sexcaperoom.ch',
			'sender_name'          => 'Sexcape Room',
			'reply_to'             => 'reservations@sexcaperoom.ch',
			'signature'            => "L'équipe Sexcape Room",
			// Même page que L'Instant Clé (845), voir la note de tête de
			// fichier sur 'confirmation_page_id' — constat-perimetre-tunnel.md
			// §4 (A3), relevé le 12 septembre 2026.
			'confirmation_page_id' => 845,
			'languages'            => array( 'fr' ), // anglais et allemand tracés, non livrés au lancement.

			'mail' => array(
				// Une réservation allemande retomberait sur cette entrée,
				// faute de mieux, et c'est voulu : l'objet d'une autre langue
				// de la bonne marque vaut mieux que l'objet natif de Vik, qui
				// nommerait L'Instant Clé.
				'subject' => array(
					'fr' => 'Votre réservation — Sexcape Room',
				),

				/**
				 * Volontairement vide, et ce vide est une position, pas un
				 * trou à combler plus tard.
				 *
				 * Le corps du message vient du gabarit unique de Vik
				 * (`site/helpers/email_tmpl.php`), qui est celui de L'Instant
				 * Clé : logo, photos de la villa, blocs « occasions
				 * spéciales », « repas », « massages », et treize URL sur
				 * linstantcle.ch. Y substituer « L'Instant Clé » par « Sexcape
				 * Room » ne rendrait pas ce message sexcapien : cela le
				 * rendrait faux, et cela ferait taire le contrôle de fuite qui
				 * signale précisément que ce corps n'est pas encore le bon.
				 *
				 * Le corps se sépare par marque dans Vik, pas ici : les textes
				 * conditionnels (`{condition: ...}`) portent déjà toute la
				 * copie de cette installation — 72 enregistrements dans
				 * `sir_vikbooking_condtexts` au 15 septembre 2026 — et la
				 * règle native `rooms.php` conditionne un bloc aux chambres
				 * réservées. Marche à suivre détaillée en §5 de
				 * docs/briefs/constat-phase-3-emails.md.
				 */
				'replacements'   => array(),

				// La copie Sexcape Room n'est pas arrêtée : livrable des
				// chantiers D et B3. Renseigner ici un fragment HTML le pose
				// avant `</body>`, sans toucher une ligne de code.
				'signature_html' => null,
			),

			'appearance' => array(

				'logo'    => null, // voir la décision du 15 septembre 2026 ci-dessus.
				'favicon' => null,

				'colors' => array(
					'noir'          => '#0C0A09', // fond principal.
					'laque'         => '#16110E', // fond de surface.
					'laque_haute'   => '#1E1712', // surface en relief.
					'laque_basse'   => '#100C0A', // surface en creux.
					'puits'         => '#0A0807', // fond le plus sombre ; fond des champs de formulaire.
					'or_haut'       => '#F0BE5A',
					'or'            => '#E3AA3E', // or de référence, accent.
					'or_bas'        => '#A87C22',
					'or_poli'       => 'linear-gradient(158deg,#F0BE5A 0%,#E3AA3E 46%,#A87C22 100%)',
					'or_poli_vif'   => 'linear-gradient(158deg,#F7CC72 0%,#EDB94E 45%,#BC8C28 100%)', // survol.
					'or_sombre'     => '#241802',
					'laiton'        => '#B08A2E', // bordure des champs au repos.
					'laiton_mat'    => '#7A5E1E',
					'ivoire'        => '#F2EDE4', // texte clair.
					'etain'         => '#B6ADA2', // texte secondaire.
					'focus'         => '#F2EDE4', // anneau de focus sur fond sombre.
					'focus_sombre'  => '#0C0A09', // anneau de focus sur fond clair.
					// Tranchées avec Thomas le 12 septembre 2026, absentes du
					// CSS personnalisé du kit avant cette date.
					'alerte'        => '#C9463C', // bordure de champ en erreur, titre de bandeau.
					'alerte_clair'  => '#E2776A', // message d'erreur sous le champ, accent de bandeau.
				),

				'radii' => array(
					// --srlm-arc de base ; le kit le redéfinit par requête de
					// média entre 140px et 395px, non reproduit ici : le
					// tunnel n'emprunte pas l'ornementation en arche du site
					// (« Ce qui ne se copie pas » du brief d'habillage).
					'arc'       => '150px',
					'arc_btn'   => '18px 18px 4px 4px',
					'arc_puce'  => '14px 14px 3px 3px',
					'arc_champ' => '10px 10px 3px 3px',
				),

				'fonts' => array(
					'serif' => array(
						'family' => 'Marcellus', // doit correspondre au premier nom de 'stack', pour le descripteur font-family de @font-face.
						'stack'  => "'Marcellus','Hoefler Text','Times New Roman',serif", // titres.
						'faces'  => array(
							array( 'weight' => 400, 'style' => 'normal', 'file' => 'marcellus-v14-latin_latin-ext-regular.woff2' ),
						),
					),
					'sans' => array(
						'family' => 'Jost',
						'stack'  => "'Jost','Avenir Next','Helvetica Neue',Arial,sans-serif", // interface, surtitres, boutons.
						'faces'  => array(
							array( 'weight' => 400, 'style' => 'normal', 'file' => 'jost-v20-latin_latin-ext-regular.woff2' ),
							array( 'weight' => 500, 'style' => 'normal', 'file' => 'jost-v20-latin_latin-ext-500.woff2' ),
							array( 'weight' => 600, 'style' => 'normal', 'file' => 'jost-v20-latin_latin-ext-600.woff2' ),
							array( 'weight' => 700, 'style' => 'normal', 'file' => 'jost-v20-latin_latin-ext-700.woff2' ),
						),
					),
					'voix' => array(
						'family' => 'Lora',
						'stack'  => "'Lora','Iowan Old Style','Georgia',serif", // textes de voix, citations.
						'faces'  => array(
							array( 'weight' => 400, 'style' => 'normal', 'file' => 'lora-v37-latin_latin-ext-regular.woff2' ),
							array( 'weight' => 400, 'style' => 'italic', 'file' => 'lora-v37-latin_latin-ext-italic.woff2' ),
						),
					),
				),

				'shell' => array(
					'max_width' => '1180px',
					'padding'   => '22px',
				),
			),
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
