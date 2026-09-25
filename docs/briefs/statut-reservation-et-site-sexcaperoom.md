# Statut du chantier réservation Sexcape Room et du site sexcaperoom.ch

**Version 3.12, 25 septembre 2026.** Remise à l'état du plan de marche 2.20 : la version 3.11 du 22 septembre s'arrêtait avant la recette, l'incident du 24 et le premier parcours complet.

Document de synthèse. Il ne remplace aucun fichier, il les relie : le chantier réservation vit dans le dépôt `icl-dev`, la construction du site sexcaperoom.ch vit dans le dossier Communication, et ce document dit où en est chacun. **Pour le chantier réservation, `plan-de-marche.md` fait foi pour les décisions et les constats pour les faits.** Ce document n'en est qu'un résumé daté, et relève les endroits où les deux ne disent plus la même chose.

À lire en entier avant toute prochaine session sur l'un ou l'autre chantier.

---

## 1. Ce qui a changé depuis le 4 septembre

Les onze fichiers de doctrine du dossier Communication datent du 4 septembre. Deux d'entre eux étaient devenus partiellement faux ; les deux corrections sont faites, voir chapitre 4. Depuis le 14 septembre, la priorité va au moteur de réservation, et le site sexcaperoom.ch attend, voir chapitre 5.

---

## 2. Chantier réservation, état au 25 septembre

**Source : `plan-de-marche.md` 2.20**, lu par le miroir synchronisé le 25 septembre à 13 h 49, commit `7c71c85`, zéro fichier non validé, et recoupé avec `constat-reprise-1836-1837.md`, `constat-recette-vcm-inactif.md`, `constat-integrations-sortantes.md` et `incident-preproduction-vers-plateformes-2026-09-24.md`.

### 2.1 En cinq lignes

- **Le moteur tourne en préproduction**, `staging13.linstantcle.ch`, et le paiement Stripe de test va au bout pour les deux marques : 1830 et 1831 le 24 septembre, 1836 et 1837 le 25, `confirmed` et payées en base.
- **La marque d'expéditeur des confirmations arrive jusqu'au client**, `reservations@sexcaperoom.ch` et `reservations@linstantcle.ch`. Composée juste, transcription à l'appui ; reçue telle quelle, en-têtes bruts relus par Thomas dans la fourre-tout. La copie à l'administrateur garde l'expéditeur natif de Vik, et c'est voulu.
- **La vérification 7, le rappel avant séjour, attend une décision de Thomas**, et l'occasion de l'exercer sur les deux marques se referme, voir 2.5.
- **L'incident du 24 septembre est clos** : la préproduction avait poussé ses réservations d'essai vers Airbnb, Booking.com et Expedia. Disponibilité réelle repoussée depuis la production, Vik Channel Manager retiré de la préproduction.
- **Rien n'est déployé en production.** B10 attend la vérification 7, B11b, G0 et G3.

### 2.2 La recette, vérification par vérification

| # | Ce qu'on vérifie | État | Établi par |
|---|---|---|---|
| 1 | L'hôte résout la bonne marque | **OK**, deux passes | recette du 24 septembre |
| 2 | Aucune chambre étrangère servie | **OK**, deux passes, mesurée sur les identifiants de chambre et non plus sur les noms | B11, `constat-correctif-room-filter.md` |
| 3a | La garde refuse une chambre étrangère active | **OK**, `403` dans les deux sens | B9h |
| 3b | La garde refuse une chambre désactivée | **non concluant, connu** : la chambre désactivée n'apparaît dans aucun résultat de Vik, le parcours ne peut pas la soumettre | `constat-recette-automatisee.md` |
| 4 | Apparence Sexcape Room en passe Sexcape Room, aucun jeton `--srlm-*` en passe L'Instant Clé | **OK** en préproduction ; la production reste à faire | recette du 24 septembre |
| 5 | Expéditeur de la marque sur la confirmation | **OK de bout en bout**, composé et reçu | `constat-reprise-1836-1837.md`, en-têtes bruts |
| 6 | Métadonnée de marque, URL de retour, page de confirmation | **OK** : Stripe Checkout atteint, page de réservation réelle affichée `confirmed`. **Deux chemins non couverts** : `cancel_url` et les réservations à marques mêlées, les deux seuls où la métadonnée peut être fausse | B9g, B9i ; les deux trous par `constat-phase-4-paiement.md`, remontés au plan le 23 septembre |
| 7 | Rappel avant séjour sous la bonne identité | **`??`**, constatée sans déclenchement | B9i, décision de Thomas |
| 8 | Hors liste blanche, redirection 302 vers `sexcaperoom.ch` | **KO annoncé**, G2 n'existe pas encore | chantier G |

**« Vérifications 1 à 6 vertes » est donc vrai à deux réserves près**, 3b non concluante par construction et 6 muette sur deux chemins. Aucune des deux n'est portée comme prérequis de B10 dans le plan : c'est à trancher, pas à supposer.

### 2.3 Ce qui s'est passé depuis la version 3.11

- **23 septembre.** Recette automatisée livrée, `recetter-moteur.sh`, commit `42cc5b1`. Vingt-trois prérequis dormants remontés des briefs, `prerequis-dormants-2026-09-23.md`, et la règle qui en sort : un prérequis se remonte au plan dans la même passe.
- **24 septembre.** Le script suit le bouton PAY NOW jusqu'à Stripe Checkout, B9g, `f246db6` ; le réglage `skipbtn`, d'abord accusé, n'y était pour rien. Première recette complète en deux passes. B9h, `5d498dc` : le `200` de la passe L'Instant Clé venait du verrou posé par la propre réservation du script. B11, `d8c1753` : `room-filter.php` agissait après le rendu de Vik, il agit désormais sur `init` priorité 1, et les vues `availability` et `roomslist` sont fermées. Revue de B5, trois points. **Incident** : Vik Channel Manager, actif sur la préproduction, a poussé les réservations d'essai 1822 à 1832 vers les trois plateformes ; réparé le jour même depuis la production. B9j, `fb92b1c` : inventaire des intégrations sortantes, WooCommerce Payments trouvé en mode réel sur la préproduction, garde-fou de messagerie 1.1.0 sur `phpmailer_init`. Première reprise : le paiement de la phase 4 aboutit, 1830 et 1831. Le soir, la désactivation de Vik Channel Manager posée contre l'incident fait planter toute réservation sur la préproduction, `9b357f8`.
- **25 septembre.** La cause en est établie : Vik Booking détecte Vik Channel Manager par ses fichiers, jamais par son statut d'activation. Le greffon se **retire** donc de la préproduction, il ne se désactive pas (`constat-recette-vcm-inactif.md`, `41cc6c2`). B9i, `ce801e4` : la 6c était verte par construction, l'annulation en libre-service est fermée par réglage, la vérification 7 constate sans déclencher. Premier parcours complet, 1836 et 1837 : vérification 5 établie de bout en bout. Une conclusion inverse de Cowork sur le relais Gmail est corrigée, et la tâche C6 qu'elle avait créée est retirée. Opus 5.5 effort moyen devient le modèle par défaut de Code.

### 2.4 Les chantiers

| Chantier | État au 25 septembre |
|---|---|
| **A. Tarifs** | A1 à A4 faits. A5 à A8 ouverts ; le périmètre de A6 est à trancher d'abord (`Fall vacation 2027` absente, `Carnival vacation 2027` sans doublet « rooms »). |
| **B. Moteur** | Faits et revus : B1 à B5, B4a, B4b, B8, B9, B9b à B9j, B11. **Déployés en préproduction seulement** : `lme-brands`, B5 comprise, et `lme-mail-guard` 1.1.0, empreintes identiques au dépôt (`constat-reprise-1836-1837.md` §1). Ouverts : **B11b**, avant B10 ; **B6**, lançable, attend les deux valeurs que Thomas pose dans `wp-config.php` ; **B7**, attend une décision ; **B10**. |
| **C. Infrastructure** | Fait, sauf C5, l'observation DMARC. C6 retiré le 25 septembre. |
| **D. Habillage** | D1 et D2 faits. D3, D4 et D5 ouverts. |
| **E. Surveillance** | Pas commencé ; attend A5, sauf E4 qui n'attend rien. |
| **F. Contenu des e-mails de Vik** | F1 à F3 ouverts à la connaissance du plan. **F3 devait précéder la réouverture des chambres 8, 9 et 10 le 24 septembre** ; son état n'est consigné nulle part. |
| **G. Verrou d'hôte** | G1 fait. **G0**, geste de Thomas, ouvert ; G2 lançable après lui ; G2b et G3 ouverts. |
| **H. Signalement à l'éditeur** | H1 ouvert, Cowork. |

### 2.5 La décision qui presse : la vérification 7

**Recommandation du plan, chapitre 4 : l'exercer, et le jour même.** Je la tiens, pour la raison qu'il donne : la fenêtre d'un passage lancé aujourd'hui couvre les arrivées du 25 au 27 septembre, et elle contient des réservations des deux marques, 1817 et 1434 au Boudoir, 1490 à L'Entracte. C'est le seul moyen d'éprouver le critère décisif de B5 : une exécution, deux marques, deux expéditeurs composés différents.

**Ce qui est établi pour demain, et ce qui ne l'est pas.** 1817 et 1490 arrivent aujourd'hui. Un passage lancé demain couvre le 26 au 28 : il garde 1434, Sexcape Room, et aucune arrivée L'Instant Clé n'a été relevée pour le 27 ou le 28. Ce n'est pas qu'il n'y en a pas, c'est que personne n'a regardé.

**Ce que le déclenchement fait**, `constat-reprise-1836-1837.md` §4.3 : trois rappels composés pour trois vrais clients, tous détournés vers la fourre-tout par le garde-fou, WP-CLI compris ; leurs adresses apparaissent en `X-Original-To` et dans le journal d'enveloppe de la préproduction ; Vik écrit `flag_char`, `last_exec` et `logs` dans la base de la préproduction. Rien n'atteint la production.

**Le geste** : Thomas dit oui ; Code lance `wp cron event run vikbooking_cron_email_reminder_7` sur la préproduction seulement ; Thomas relit les en-têtes bruts des trois messages dans la fourre-tout.

### 2.6 Ce que le plan 2.20 dit et qui ne tient plus

Relevé en recoupant le plan avec les constats du 24 et du 25 septembre. **À corriger dans le plan 2.21**, Cowork, en même temps que la prochaine mise à jour.

1. **« Garde-fou 1.1.0 pas encore déployé »**, ligne B9j du tableau B et paragraphe B9j. Il est déployé sur `staging13`, empreinte identique au dépôt, relevé le 25 septembre (`constat-reprise-1836-1837.md` §1).
2. **« Rouvrir l'envoi attend deux choses. »** Des messages de la préproduction ont été reçus le 25 septembre : l'envoi est donc ouvert de fait. Or l'état « Do not send » de WP Mail SMTP n'a pas été relu (même constat, §7.4), et la désactivation de WooCommerce Payments et de PayPal n'est consignée nulle part. **Le seul barrage établi est le garde-fou.** Relire les deux, par clé et par la liste des greffons.
3. **Le paragraphe B9e figure deux fois**, et la seconde copie porte encore « La réserve, et elle est bloquante », levée par `d9a123e` le 22 septembre. Un lecteur pressé lit l'inverse de l'état réel.
4. **« Les rappels de Vik sur les vraies réservations copiées partiront d'un coup »**, paragraphe B9j. À préciser : un passage n'envoie que la fenêtre de son jour, mais à de vrais clients (§4.3 du constat). Le crochet `vikbooking_cron_door_access_control`, lui aussi en retard, n'a pas été examiné.
5. **Le chapitre 4 ne porte pas l'annulation à la main des réservations d'essai**, demandée par `constat-reprise-1836-1837.md` §7.1 : 1826 à 1832, 1834 à 1837, et 1833 une fois son origine confirmée. C'est exactement le cas que la règle du 23 septembre voulait empêcher.
6. **« F3, avant le 24 septembre »**, chapitre 4 : la date est passée, et rien ne dit si c'est fait.
7. **En-têtes périmés** : le tableau des chantiers est daté « État au 20 septembre », et le séquencement du chapitre 3 porte encore le redéploiement et la revue de B5 comme ouverts.
8. **`journal-vik.md` n'a aucune ligne depuis le 19 septembre**, alors que le retrait de Vik Channel Manager de la préproduction, la poussée de disponibilité depuis la production du 24 septembre (notification 4033) et les désactivations de greffons sur la préproduction sont des gestes d'administration. Et sa section « Rappels » dit encore que Thomas seul y écrit, contraire à la décision du 19 septembre.

**Reste ouverte, sans être périmée** : la contradiction sur `DISABLE_WP_CRON`, absent selon `constat-integrations-sortantes.md`, à `true` selon `constat-reprise-1836-1837.md`. Le plan la porte déjà.

### 2.7 Ce qui ne se rouvre pas

- **Un seul Vik Booking**, sur linstantcle.ch, sert les deux marques. Le tunnel Sexcape Room tourne en direct sur un second hôte, `reservation.sexcaperoom.ch`, habillé aux couleurs de la marque : ni iframe, ni proxy.
- **Le compte MySQL est en lecture seule.** Toute correction passe par les écrans de Vik, faite par Thomas, vérifiée par Code.
- **Le déploiement passe par `deployer-moteur.sh`**, jamais par le « Push to Live » de SiteGround, qui emporterait `staging` et l'adresse fourre-tout en production.
- **La préproduction est une copie complète de la production**, intégrations sortantes comprises. Après chaque recréation, la liste du chapitre 4 du plan se déroule avant toute autre chose, Vik Channel Manager retiré et non désactivé.
- **Le déploiement en production est une décision distincte de la recette**, B10 : `lme-brands` change aussi le parcours de linstantcle.ch, la marque qui vend.

### 2.8 Pour mémoire, et toujours vrai en production

- **Le rappel avant séjour fuit la marque** : il part de `L'Instant Clé <info@maisonnette-enchantee.ch>` pour toutes les chambres, Sexcape Room comprise. B5 le corrige et n'est pas en production ; le chantier F corrige le contenu.
- **Un client peut être débité sans réservation confirmée** : 137 sessions Stripe jamais soldées, 42 297.85 CHF depuis février 2024, la plupart sans doute des paniers abandonnés. Seule la lecture chez Stripe, geste de Thomas, tranchera. Zone non couverte en plus : 177 paiements d'avant octobre 2025, 40 319.90 CHF, sans numéro de réservation (`revue-encaissements-non-confirmes.md`, dossier Communication).
- **Le défaut de `stripe.php:365` est intact.** La sortie de NitroPack de la page 845 en retire le déclencheur le plus fréquent, pas les autres. Le signalement à E4J, chantier H, reste à écrire.

L'historique détaillé jusqu'au 22 septembre, que cette version remplace, reste lisible dans la version 3.11, commit `bcfc5ec`.

---

## 3. Site sexcaperoom.ch, où ça en est réellement

Trois documents s'enchaînent dans le dossier Communication : l'audit du 9 septembre, la passe de correctifs du 10, la revue du 11. Voici l'état qui en ressort, pas un résumé de chacun.

**Le design est arrêté.** Direction « Laque, variante Miroir ». Palette et typographie synchronisées dans le kit Elementor depuis le 11 septembre : Étain, Noir, Ivoire, Or comme couleurs système, Marcellus, Lora et Jost chargées en polices auto-hébergées et réellement appliquées, vérifié en direct. `design-sexcaperoom.md` dit encore, à la date du 4 septembre, que ces rubriques sont « en grande partie à décider » : ce n'est plus vrai, voir chapitre 4.

**Le site est en ligne, en production, et structurellement inachevé.** Quatre points bloquent tout trafic réel, dans l'ordre de gravité :

1. **Aucun chemin de réservation ni de contact.** Le bouton « Réserver » de la page d'accueil affirme que le moteur est en cours de branchement et renvoie vers `/contact/`, qui est lui-même une page « Cette page arrive ». Un visiteur qui veut réserver n'a strictement aucune issue.
2. **Le site est invisible des moteurs de recherche.** `noindex` sur toutes les pages, `/robots.txt` et `/sitemap.xml` en 404.
3. **Trois pages légales sont vides** : conditions, mentions légales, confidentialité. Le site collecte déjà une adresse e-mail par la capture newsletter et affiche déjà des conditions d'annulation chiffrées, sans qu'aucun texte légal ne les couvre. C'est une exposition juridique réelle en Suisse (LPD/nLPD), pas un simple manque de référencement.
4. **La page des bons cadeaux est vide** alors que la page d'accueil pousse activement vers elle.

**Un cinquième point, plus subtil, à ne pas laisser filer.** L'îlot de réservation sur la page d'accueil n'est pas un lien mort au sens strict : c'est un formulaire aux champs actifs et au bouton cliquable, dont la soumission ne mène nulle part. Un visiteur qui le remplit croit avoir amorcé une réservation. C'est le défaut que le brief du 5 septembre voulait justement éviter, et il existe quand même. Le corriger est un geste d'une heure, indépendant de tout le reste : soit griser les champs et retirer le bouton pour signaler clairement un aperçu, soit basculer dès maintenant sur un bouton simple vers `/contact/`.

**Le nœud entre les deux chantiers, à retenir.** Le point 1 ci-dessus ne se résout pas ici. Il se résout quand le chantier B (moteur) et le chantier D (habillage) livrent `reservation.sexcaperoom.ch`. D'ici là, le seul geste qui a du sens sur ce point précis est le stopgap déjà identifié par l'audit du 9 : publier une vraie page Contact fonctionnelle, pour que le renvoi que la page d'accueil promet existe réellement. Ce geste-là ne dépend de rien d'autre et peut se faire cette semaine.

**Ce qui est bloqué et pourquoi.** Cent quinze outils EMCP sont désactivés par un réglage WordPress (`emcp_tools_disabled_tools`), dont ceux qui permettraient d'auto-héberger un fichier et d'ajouter un widget Elementor Pro. Trois éléments en dépendent : l'auto-hébergement de police (déjà fait entre-temps, donc ce point est réglé), le widget de menu de navigation Pro, et rien d'autre d'important en attente. **Fait le 14 septembre 2026 :** `add-pro-widget` et d'autres outils sont réactivés sur les deux sites. Le lecteur SQL en lecture seule, `query`, est lui aussi disponible sur les deux, ce qui lève plusieurs constats d'outil manquant de l'audit, dont celui du pied de page ci-dessus.

**Ce qui a été vérifié comme non cassé, pour ne pas y revenir :** les points de rupture responsive du CSS personnalisé fonctionnent, aucun débordement horizontal mesuré de 320 à 1445 pixels ; les dix-sept conteneurs vides signalés par l'audit sont tous des conteneurs de mise en page délibérés, pas des déchets ; le lazy-load est correctement absent sur l'unique image, qui est au-dessus du pli.

**Question refermée le 14 septembre 2026 :** il n'existe qu'un seul gabarit de pied de page, `Sexcape Room Footer - Global`, identifiant 23, publié et assigné à tout le site par la condition `include/general`. Les « seconds gabarits » relevés par l'audit sont des révisions WordPress du même gabarit, encore nommées `Elementor Footer #23` avant son renommage. **Rien à supprimer.** Vérifié en SQL sur `sxu_posts`.

**Pas de staging pour ce site.** Chaque essai se fait en production. C'est le vrai manque d'autonomie du chantier, plus limitant que n'importe quel outil désactivé.

---

## 4. Doctrine corrigée dans le dossier Communication

**Les deux corrections proposées le 12 septembre ont été faites.** Ce chapitre garde la trace de ce qui a changé, il ne demande plus rien.

**`marques-et-experiences.md`, version 1.1 du 12 septembre.** La désignation « chambre nord » est retirée, les deux groupes de disponibilité sont nommés (villa Entracte, chambres 1, 7 et 10 ; villa Aparté, chambres 2 et 4), La Parenthèse et L'Indécent sont posées comme indépendantes, et la chambre 7 figure au tableau de correspondance sous son nom `L'Entracte all inclusive`. La version 1.2 du 13 septembre y a ajouté la décision d'ouverture des chambres 8, 9 et 10.

**`design-sexcaperoom.md`, version 2.0 du 12 septembre.** Le fichier porte désormais les valeurs arrêtées et mesurées en direct : palette Étain, Noir, Ivoire, Or ; Marcellus, Jost et Lora auto-hébergées ; six points de rupture ; motifs récurrents et technique CSS. Ce qui reste ouvert y est marqué comme tel plutôt que deviné, quatre points seulement.

**Le point contesté du 14 septembre a été tranché le jour même.** La traduction automatique est active sur linstantcle.ch. `wordpress-et-web.md` 1.4 porte la décision et la vigilance qu'elle impose : mieux vaut une chaîne mal traduite que de l'anglais au milieu du français, mais toute chaîne source modifiée perd son appariement et repasse à la portée de l'automatique, donc se relit en français après coup.

---

## 5. Prochaines étapes, par ordre et par qui

**Décision du 14 septembre 2026 : priorité au moteur de réservation.** Les gestes de mise en ligne de sexcaperoom.ch attendent. Nous livrerons un moteur fonctionnel plutôt que de prendre des réservations à la main, et l'îlot non fonctionnel sera remplacé par le moteur dès qu'il tiendra, pas neutralisé entre-temps.

### 5.1 Chantier réservation

1. **Remettre ce statut à l'état du plan.** Fait par cette version 3.12.
2. **Vérification 7**, sur décision de Thomas, aujourd'hui de préférence. Voir 2.5.
3. **B11b**, Code : recenser les six autres vues de Vik joignables par `view`, et corriger `constat-phase-0.md` Q4 à la source, qui nie l'existence de `vikbooking_before_display_<vue>`.
4. **G0**, Thomas : exclure `reservation.sexcaperoom.ch` du cache dynamique SiteGround et de NitroPack, et confirmer le 302 jusqu'à la fin de la recette. **Puis G2**, Code, effort élevé, **G2b** et **G3**, la recette du verrou en six points comprise.
5. **B6**, la parade de paiement, une fois posées par Thomas la clé Stripe restreinte en lecture et le secret de signature du webhook. **Puis B10**, décision de Thomas.

**En parallèle, hors du chemin critique et pour cette raison à ne pas laisser dormir** : F3 puis F1 et F2, gestes de Thomas dans Vik ; la lecture Stripe des 137 sessions ; l'annulation à la main des réservations d'essai ; H1. Restent aussi à Thomas quatre arbitrages, le périmètre de A6, l'identité d'envoi des repas et des bons cadeaux pour B7, le critère n°8 du libellé de relevé et l'adresse de réponse du rappel, et quatre lectures courtes : l'historique des restaurations de Site Tools du 15 septembre, l'origine de l'archive `.local/wp-vikstripe-new`, la relecture de `journal-vik.md`, et la clé SSH à recharger dans l'agent après chaque redémarrage. Ordre et détail au chapitre 4 du plan.

### 5.2 Site sexcaperoom.ch, reporté par décision jusqu'à la livraison du moteur

1. Publier une vraie page Contact.
2. Neutraliser l'îlot de réservation actuel ; il sera remplacé par le moteur.
3. Lever le `noindex`, faire résoudre `/robots.txt` et `/sitemap.xml`.
4. Publier les trois pages légales. **Seul report qui porte un risque juridique**, pas seulement commercial : l'exposition LPD/nLPD du chapitre 3 court tant que la capture d'infolettre tourne.

### 5.3 Les points 5 à 20 de la version 3.11

Tous fermés ou repris par le plan. Faits : l'accès MySQL, les requêtes et la revue de la grille tarifaire, les corrections de doctrine, les outils EMCP, l'ouverture des chambres 8, 9 et 10, le relevé visuel du tunnel, le volet e-mail, la phase 2, la divergence du brief de verrou d'hôte, et les lignes manquantes de `journal-vik.md` pour les 13 et 14 septembre, qui y figurent désormais. Repris par le plan : les phases du moteur au chantier B, le rappel qui fuit la marque et la réserve de paiement au chapitre 6 du plan et en 2.8 ci-dessus.

---

## 6. Reprendre ce chantier dans une nouvelle conversation

Les conversations de ce chantier sont longues et coûteuses. **Elles se coupent sans rien perdre, à une condition : que tout soit écrit.** Ce chapitre vaut pour toute reprise.

**Ordre de lecture :**

1. `ETAT-MIROIR.md` du miroir `~/Work/_Mirrors/icl-dev-lecture` : annoncer l'âge de la copie et son commit avant d'en tirer quoi que ce soit.
2. `docs/briefs/plan-de-marche.md`, la référence opérationnelle : qui fait quoi, dans quel ordre, avec modèle et effort, et les prompts des tâches ouvertes.
3. Ce document, pour l'état résumé et les écarts relevés entre le plan et les constats, chapitre 2.6.
4. `procedures.md` du dossier Communication, sur la lecture du miroir : ne conclure ni d'une absence ni d'une présence sans `git ls-files`.
5. `wordpress-et-web.md`, les pièges connus, dont celui du lecteur SQL qui répond un jeu vide au lieu d'une erreur.
6. Les constats du dépôt, selon le sujet.

**Comment ce document et le plan entrent dans le dépôt.** Cowork les écrit dans le dossier Communication ; Thomas les **déplace** dans `~/Documents/icl-dev/docs/briefs/`, jamais une copie, puis lance `git diff --stat` **avant** de commiter. Le plan a été vidé ou perdu deux fois en transitant par le dossier Communication : le `diff --stat` montre un fichier vidé avant qu'il soit commité.

**Règles de travail, apprises à nos dépens sur ce chantier :**

- **Établir avant d'affirmer.** Toute affirmation sur un fichier, un réglage ou un comportement se vérifie à la source avant d'être écrite. Cowork a posé au moins six conclusions non vérifiées entre le 22 et le 25 septembre, corrigées ensuite par Code ou par Thomas ; la dernière déduisait une réécriture de l'expéditeur par Gmail de copies administrateur prises pour des messages clients.
- **Qualifier une observation rapportée avant de l'interpréter** : quel message, quelle page, quel environnement. L'objet d'un message aurait suffi à éviter la dernière erreur.
- **La transcription d'enveloppe montre ce que WordPress compose, jamais ce qui part.** La preuve d'un envoi est dans les en-têtes bruts reçus. **Vik envoie deux messages par réservation** : le message client, sans numéro dans l'objet, et la copie à l'administrateur, avec `#<id>`.
- **Rien n'est inoffensif par défaut sur la préproduction** : c'est une copie complète de la production, intégrations sortantes comprises. L'incident du 24 septembre en est venu.
- **Un fichier, un auteur.** Code écrit les constats, Cowork les briefs, les revues, le plan et ce statut, et `journal-vik.md` est ouvert à Code et à Cowork sous l'autorité de Thomas depuis le 19 septembre. Un fichier écrit hors du dépôt s'y **déplace**, il ne se recopie pas.
- **Ce qui n'est pas écrit dans `docs/` n'existe pas**, et ce qui est écrit dans `.local/` n'existe pour personne, puisque Git l'ignore. Un prérequis posé dans un brief ou un constat remonte au plan dans la même passe.
- **Aucun secret, jamais**, ni en commit ni en conversation. Les lectures se font par clé explicite et les valeurs sensibles par empreinte.
- **Style des réponses, demandé par Thomas** : un résumé court qui l'aide à décider quoi lire, des instructions précises sans rien à deviner, et les détails dans les fichiers de suivi.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-12 | Création. Synthèse des chantiers réservation et site sexcaperoom.ch, réconciliation avec `brief-construction-sexcaperoom.md` du 5 septembre, propositions de correction pour `marques-et-experiences.md` et `design-sexcaperoom.md`. |
| 1.1 | 2026-09-13 | Mise à jour de l'état d'ouverture des chambres 8, 9 et 10 : réservables dans Vik, fermées jusqu'au 24 septembre exclu. Décision détaillée dans `marques-et-experiences.md`. |
| 1.2 | 2026-09-14 | Priorité au moteur actée, statuts des douze points du chapitre 5 mis à jour, ajout du point 13 (volet e-mail). Chapitre 4 refermé : les deux corrections de doctrine sont faites. Question du pied de page refermée par lecture SQL. État du chantier C mis à jour. |
| 1.3 | 2026-09-14 | Lecture du miroir `icl-dev`. `plan-de-marche.md`, `revue-tarifs.md` et `brief-habillage-tunnel.md` ne figurent pas dans le dépôt validé : état des points 7 et 11 corrigé. Point contesté de la traduction retiré, tranché par `wordpress-et-web.md` 1.4. Ajout du point 14, le journal Vik vide. |
| 1.4 | 2026-09-14 | Miroir complet et sans travail non validé : les trois fichiers donnés pour absents en 1.3 sont là, points 7 et 11 clos, point 12 vérifié en base et deux manques tarifaires nommés, point 14 réduit à ce qui manque encore au journal Vik. Ajout du point 15, la divergence de `brief-verrou-hote-reservation.md`. |
| 1.5 | 2026-09-14 | Phase 2 du moteur livrée (commit `6b6b273`) et revue : conforme au prompt B2, trois réserves rendues à Code au point 16, dont la neutralisation de la catégorie 1 par la chambre de test 5. Point 10 mis à jour, ancien point 15 renuméroté 17. |
| 1.6 | 2026-09-14 | Le point 16 renvoie au brief de correctif `brief-correctif-phase-2-filtrage.md`. |
| 1.7 | 2026-09-14 | Décision du modèle de chambres : toute chambre active porte une marque, `avail` de Vik fait foi pour la désactivation, le statut `excluded` disparaît. Point 16 réécrit, brief de correctif en version 2. |
| 1.8 | 2026-09-15 | Correctif de la phase 2 livré (commit `ddefdd8`) et revu. Point 16 réécrit avec les trois réserves restantes, dont la garde qui tombe sur un hôte non reconnu. |
| 1.9 | 2026-09-15 | Les trois réserves du commit `ddefdd8` sont tranchées et parties dans `brief-correctif-phase-2-suite.md`. Copie périmée du brief précédent vidée en pointeur. |
| 2.0 | 2026-09-15 | Phase 2 close, correctifs compris, trois commits revus. Les deux moitiés de la décision du 14 septembre sont démontrées. Deux erreurs de Cowork consignées. |
| 2.1 | 2026-09-15 | D2 livré (commit `13c4085`) et revu. Signalement de l'écriture non autorisée d'un agent de recherche dans `config/brands.php`. Prérequis B4 ajouté après le redépôt de Vik Stripe dans `.local/`. |
| 2.2 | 2026-09-15 | Vik Stripe : retour à l'ancienne version, prérequis B4 retiré. Ajout de B4a, la réserve de paiement sans webhook, avec son bloc d'insertion au plan de marche. |
| 2.3 | 2026-09-15 | Phase 3 et B4a intégrés. Trois points ajoutés : déploiement de la phase 3 conditionné à C2, fuite de marque du rappel avant séjour en production, et réserve de paiement chiffrée. Blocs de révision du plan dans `plan-de-marche-ajout-phase-3.md`. |
| 2.4 | 2026-09-16 | Chantier e-mail terminé : C2 fait, phase 3 déployable, domaine principal inchangé, relais et sous-domaines d'envoi déclassés, observation DMARC en seule suite ouverte. Cause réelle du blocage de B3 consignée. |
| 2.5 | 2026-09-17 | Plan de marche version 2 écrit et prêt à remplacer celui du dépôt : état réel de chaque tâche, chantiers F et G ajoutés, prompts des tâches ouvertes, liste ordonnée des gestes de Thomas. |
| 2.6 | 2026-09-17 | Phase 4 livrée et revue. Décision de Thomas sur le critère n°8, métadonnée sur la session et non sur le PaymentIntent, libellé de relevé non livré. Plan v2 en place, bannière de transfert à retirer. |
| 2.7 | 2026-09-17 | Incident de production : réservation 1818 payée et non confirmée, `paymentlog` rempli, trois sessions Checkout, session stockée qui n'est pas la payée. Distinct du défaut du §7 déjà chiffré. Dossier et prompt dans `incident-paiements-non-confirmes-2026-09-17.md`. |
| 2.8 | 2026-09-18 | Cause de l'incident 1818 établie, comparaison de montants dans VikStripe et rendus multiples par NitroPack. Trois gestes de parade. Aucune exposition client : les 398.00 CHF venaient d'une restauration de sauvegarde. Ajout de B4b, examen de la nouvelle version du greffon avant tout signalement à E4J. |
| 2.9 | 2026-09-19 | Retrait du paragraphe périmé sur l'incident, dont la cause est désormais établie. Ajout du chapitre 6, comment reprendre le chantier dans une nouvelle conversation : ordre de lecture et quatre règles de travail. |
| 3.0 | 2026-09-19 | B4b revu. Conclusion tenue sur trois questions, cause surinterprétée : hypothèse concurrente de la restauration de sauvegarde du 15 septembre, non écartée. Recommandation confirmée sur l'argument d'identité. Chantier H ouvert pour le signalement E4J. Découverte que B5 est livrée depuis le 17 septembre sans revue. Plan de marche réécrit en version 2.1, `plan-de-marche-v2.1-a-remplacer.md`, à déplacer dans le dépôt. |
| 3.1 | 2026-09-19 | Parade NitroPack en service et G1 résorbé. Observation directe : l'hôte de réservation sert L'Instant Clé sans habillage, donc rien du moteur n'est déployé, et le plan ne portait aucune tâche de déploiement. Ajout de B8, la recette du moteur, et de G3. `journal-vik.md` s'ouvre à Code. Plan de marche passé en 2.1 par Thomas, miroir non rafraîchi depuis le 18 septembre 16 h 27. |
| 3.2 | 2026-09-20 | Plan de marche passé en 2.2 : B8 et G3 écrits dedans, chapitre 4 remis à jour, prompt de B8 ajouté. Le fichier d'ajout est vidé en pointeur. |
| 3.3 | 2026-09-20 | B8 revu et validé. Plan en 2.3 : B9 et D5 ajoutés, B6 relevé en Opus 5 élevé et découplé de la lecture Stripe. Revue de B5 toujours ouverte, et c'est le dernier verrou avant B6. |
| 3.4 | 2026-09-20 | Déploiement par script, tâche B9 ; l'ancien B9 devient B10. Recréation de `staging10` depuis la production avant tout, et les trois gestes qui ne survivent pas à la copie. Plan en 2.4. |
| 3.5 | 2026-09-21 | B9 revu et validé, une réserve sur `tests/` avant la production. Parade de paiement tranchée, C, D et E avec le critère de K ; G écartée et remplacée. Préproduction recréée sous `staging13`. Plan en 2.5. |
| 3.6 | 2026-09-21 | **Premier déploiement réel** : le moteur tourne en préproduction, établi par lecture directe, recette n°1 acquise et n°4 à moitié. Un défaut du script, qui ne suit pas la redirection de TranslatePress et nie un succès. Première recette : elle se mène en deux passes, une par marque. Deux correctifs en B9b, la réécriture d'URL et l'erreur fatale de la page de paiement. Plan en 2.7. |
| 3.7 | 2026-09-22 | B9b revu. Correctif d'URL bon et porté. La fatale est établie : la phase 4 casse le paiement des deux marques sur les deux environnements, par une prémisse fausse du constat de phase 0 que les tests validaient. Ajout de B9c. Plan en 2.8. |
| 3.8 | 2026-09-22 | B9c revu et validé : la phase 4 est corrigée et reste à recetter. Correction d'une affirmation fausse de ma part sur les tests, qui ne couvraient pas la fonction fautive. Ajout de B9d, les signatures de crochets, avant B10. Doublon de numéro 3.6 du journal résorbé. Plan en 2.9. |
| 3.9 | 2026-09-22 | B9d revu et validé : treize crochets, aucun écart. Le script est corrigé, réserve `tests/` levée. Plus rien ne retient le redéploiement et la recette. Plan en 2.10. |
| 3.10 | 2026-09-22 | B9e revu : garde-fou de messagerie bon dans l'ensemble, avec une réserve bloquante hors requête HTTP, où la production n'est pas reconnue et l'envoi est abandonné sans avertissement. Plan en 2.11. |
| 3.11 | 2026-09-22 | Réserve de B9e levée, et il est établi qu'aucun rappel de production n'aurait disparu. Interdiction du « Push to Live » de SiteGround. Correction d'une seconde affirmation non vérifiée de ma part. Plan en 2.12. |
| 3.12 | 2026-09-25 | Remise à l'état du plan de marche 2.20. Chapitre 2 réécrit : état en cinq lignes, recette vérification par vérification, ce qui s'est passé du 23 au 25 septembre, état des chantiers, la décision de la vérification 7, huit écarts entre le plan et les constats à corriger en 2.21, ce qui ne se rouvre pas. L'historique jusqu'au 22 septembre sort du document, lisible au commit `bcfc5ec`. Chapitre 5 réordonné sur le chantier réservation, points 5 à 20 fermés. Chapitre 6 : ordre de lecture par le miroir, transit par déplacement et `git diff --stat`, règles de la reprise du 25 septembre. |
