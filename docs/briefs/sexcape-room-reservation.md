# Brief de construction — Réservation Sexcape Room sur le Vik Booking existant

Version 1, 8 septembre 2026. Destinataire : Claude Code. Décideur : Thomas.

---

## 1. Décision déjà prise, ne pas la rouvrir

On garde **Vik Booking**, unique et hébergé sur linstantcle.ch, et on rend le tunnel de réservation **étanche par marque**. Aucune migration vers un SaaS, aucune seconde instance, aucune synchronisation iCal entre marques.

Trois raisons, dans l'ordre :

1. 1625 réservations réelles, le channel manager Airbnb, Booking.com et Expedia, et un chantier Airtable/Make qui lit le MySQL de Vik en direct dépendent de ce moteur. Le remplacer déplacerait le risque sans le réduire.
2. Le coût de mise en œuvre d'un SaaS égale celui d'une intégration propre, adaptation des processus comprise.
3. Les deux marques vendent des espaces physiques partagés. Deux inventaires séparés, quel que soit le mécanisme de synchronisation, produisent tôt ou tard une surréservation. Un seul moteur supprime la classe de panne entière.

Le grief réel contre Vik reste valable : qualité de code médiocre, support qui renvoie la faute au client, un tarif weekend non appliqué pendant deux mois pour 1800 francs et dix heures perdues. La parade n'est pas de changer de moteur, elle est de **le surveiller** : voir la phase 5.

---

## 2. Carte de vérité

Sept expériences, aucune autre n'existe. La marque se déduit **toujours** de l'identifiant de chambre Vik, jamais de son nom.

| Chambre Vik | Expérience | Marque | Espace physique | Ouverte à la vente |
|---|---|---|---|---|
| 1 | L'Entracte | L'Instant Clé | villa Entracte | oui |
| 7 | L'Entracte all inclusive | L'Instant Clé | villa Entracte | oui |
| 10 | À Huis Clos | Sexcape Room | villa Entracte | au lancement |
| 2 | L'Aparté | L'Instant Clé | villa Aparté | oui |
| 4 | Le Boudoir du Désir | Sexcape Room | villa Aparté | oui |
| 8 | La Parenthèse | L'Instant Clé | chambre indépendante | au lancement |
| 9 | L'Indécent | Sexcape Room | chambre indépendante | au lancement |
| 5, 6 | chambres de test | aucune | aucun | non, jamais vendues |

Conséquences non négociables :

- **Groupe de disponibilité « villa Entracte »** : les chambres 1, 7 et 10 se bloquent mutuellement.
- **Groupe de disponibilité « villa Aparté »** : les chambres 2 et 4 se bloquent mutuellement.
- Les chambres 8 et 9 sont indépendantes, aucun blocage croisé.
- Les chambres 5 et 6 servent aux tests, restent hors de toute analyse et hors de tout affichage public.
- Le nom « chambre nord » est abandonné. `L'Indécent` est le nom retenu. `La Chambre Interdite` et `La Suite Interdite` sont abandonnés aussi.
- La chambre 7 s'appelle **`L'Entracte all inclusive`** partout : dans Vik, dans le registre, dans le code, dans Airtable, dans les journaux. Seules les campagnes marketing adaptent le verbiage au segment visé.
- **La correspondance chambre vers expérience est plusieurs vers une.** Les chambres 1 et 7 pointent toutes deux vers l'expérience `L'Entracte`, la 7 portant en plus l'attribut forfait `all inclusive`. Le registre doit donc distinguer `experience` et `forfait`, et l'ingestion Airtable doit rattacher la chambre 7 à l'enregistrement `L'Entracte` de la table `Expériences`, sans créer d'enregistrement dédié. Cette forme survit intacte au jour où le forfait deviendra un plan tarifaire de la chambre 1 plutôt qu'une chambre.
- **Mesure.** Le taux d'occupation se calcule par espace physique, jamais par expérience : deux expériences d'un même espace partagent une capacité, et leur attribuer chacune un taux divise deux fois le même dénominateur. Nuits vendues, revenu et délai de réservation se calculent par chambre puis s'additionnent par expérience et par marque.

---

## 3. Le résultat attendu, vu du client

Un visiteur de sexcaperoom.ch réserve À Huis Clos sans jamais rencontrer le nom L'Instant Clé : ni dans une URL, ni dans le code source d'une page, ni dans un e-mail, ni sur la page de confirmation.

Indispensable :

- seules les chambres 4, 9 et 10 apparaissent et sont réservables depuis Sexcape Room ; seules les chambres 1, 2, 7 et 8 depuis L'Instant Clé ;
- expéditeur, signature, ton et contenu des e-mails de confirmation et de rappel propres à la marque ;
- page de confirmation après paiement propre à la marque, servie par le site appelant ;
- retour de Stripe vers le site appelant, toujours.

Confort, à faire seulement si tout le reste tient :

- facture et conditions générales par marque ;
- libellé sur le relevé bancaire par marque.

Langues : français seul au lancement, avec le chemin vers l'anglais et l'allemand tracé et testable, pas livré.

---

## 4. Architecture retenue

### 4.1 Un hôte de réservation par marque, une seule installation

`reservation.sexcaperoom.ch` est un **alias** de l'installation WordPress qui porte Vik. Même code, même base, même Vik, hôte différent. Le panier de Vik vit dans une session PHP liée au domaine : servir le tunnel sous l'hôte de la marque supprime d'un coup le problème de cookies inter-domaines, qui est la cause d'échec classique de ce genre de montage.

À faire :

1. ajouter le sous-domaine comme domaine garé **sur le site linstantcle.ch**, dans SiteGround Site Tools, avec certificat Let's Encrypt, après avoir créé chez name.com un enregistrement `A` pointant sur l'adresse IP de **ce serveur-là** ;
2. un mu-plugin rend WordPress conscient de l'hôte : quand la requête arrive sur l'hôte Sexcape Room, `home_url`, `site_url`, `content_url`, `option_home`, `option_siteurl`, `upload_dir` et `wp_get_attachment_url` produisent des URL sur cet hôte.

**Deux serveurs distincts, et ce que cela impose.** Les deux sites partagent un compte SiteGround mais pas une machine : linstantcle.ch vit sur `gfram1004.siteground.biz`, sexcaperoom.ch sur `gvam1277.siteground.biz`, chacun avec son installation, son utilisateur SSH et son adresse IP. Deux conséquences.

D'abord, l'enregistrement `A` de `reservation.sexcaperoom.ch` doit viser l'IP de `gfram1004`. Le pointer sur celle de sexcaperoom.ch servirait silencieusement le mauvais site : l'erreur ne produit pas de panne, elle produit une page qui ne parle pas de réservation.

Ensuite, et c'est le point coûteux : **les pages du tunnel ne partagent rien avec le site sexcaperoom.ch.** Autre WordPress, autre thème, autres gabarits Elementor, autre médiathèque. L'apparence de marque du tunnel doit donc être **reconstruite dans l'installation linstantcle.ch** : logo, palette, typographie, en-tête et pied de page Sexcape Room, servis sous condition d'hôte. Le visiteur passe d'un serveur à l'autre sans le voir, à condition que la continuité visuelle soit tenue à la main. Prévoir que toute évolution graphique de sexcaperoom.ch se répercute ici, et l'inscrire dans les critères de recette.

**Précisé après la phase 0.** Filtrer `option_home` atteint bien l'URL de retour de paiement, que Vik construit sur `home_url()`. Filtrer `option_siteurl` reste indispensable pour une autre raison : les appels AJAX du tunnel passent par `admin_url()`, bâti sur `siteurl`, et partiraient sinon vers linstantcle.ch depuis l'hôte Sexcape Room. Enfin, Vik mémorise le résultat de `JUri::base()` pour la durée de la requête : les filtres doivent être posés **avant le premier appel**, ce que la place en mu-plugin garantit.

Garde-fou de mise en œuvre : la réécriture d'URL ne s'applique **qu'aux requêtes front-end sur l'hôte de la marque**. Jamais en administration, jamais sur l'API REST, jamais en cron, parce que réécrire `option_siteurl` en administration casse les URL de l'administration et rend le site inutilisable.

Si la phase 0 démontre que les deux domaines ne peuvent pas partager une installation, replier sur un tunnel entièrement reconstruit qui interroge Vik en coulisses. C'est le palier le plus coûteux et le plus fragile aux mises à jour de Vik : ne l'ouvrir que sur constat, pas sur intuition.

### 4.2 Le registre des marques, source unique

Aucune correspondance chambre, marque, espace physique, expéditeur ou page de confirmation n'est écrite en dur dans du code. Tout vit dans **un seul fichier de configuration** qui ne contient que des données, sans logique, exposé à un filtre pour permettre une surcharge sans édition :

```
wp-content/mu-plugins/lme-brands/config/brands.php   → retourne un tableau, rien d'autre
apply_filters( 'lme_brands', $config )
```

Le registre porte, par marque : clé, nom d'affichage, hôte, expéditeur et adresse de réponse, signature, identifiant de la page de confirmation, chambres vendues, langues actives. Et par chambre : marque, nom d'expérience, forfait éventuel, groupe de disponibilité.

Trois règles de robustesse :

- **une chambre inconnue du registre est une erreur, pas un cas par défaut.** Le code refuse de l'afficher, refuse de créer la réservation, journalise et alerte. Ne jamais deviner une marque.
- **un écran de santé en administration** compare le registre à `sir_vikbooking_rooms` et signale toute chambre présente d'un côté et absente de l'autre. Ajouter une expérience sans mettre le registre à jour devient visible en une journée.
- ajouter une expérience se fait en éditant ce seul fichier. Si ce point devient gênant parce qu'Aurore doit pouvoir le faire, l'évolution prévue est un écran de réglages qui écrit une option WordPress, avec le fichier comme valeur initiale. À ne pas construire maintenant.

### 4.3 Filtrage des chambres, deux couches

D'abord chercher ce que Vik sait déjà faire : catégories de chambres, listes d'identifiants acceptées par ses shortcodes, filtres natifs. **Préférer la configuration au code partout où elle existe.**

Ensuite, deux couches obligatoires :

1. **Présentation** : les pages de recherche et de résultat de chaque hôte n'offrent que les chambres de sa marque, à partir du registre.
2. **Garde** : sur le chemin de création de réservation, une requête qui vise une chambre étrangère à la marque de l'hôte est refusée, journalisée, et déclenche une alerte.

La couche 2 existe parce qu'une URL fabriquée à la main contourne la couche 1, et qu'un client qui atterrit sur la chambre de l'autre marque anéantit toute la promesse.

**Précisé après la phase 0.** La couche 1 se fait par le filtre **`vikbooking_apply_search_results_filtering`**, qui retire une annonce des résultats quand le rappel retourne exactement `false`. Deux limites établies dans le code :

- ce filtre ne couvre **que la vue `search`**. `roomslist`, `availability` et `roomdetails` n'exposent aucun point d'accroche : sur ces écrans, la présentation se règle par les attributs de shortcode, `category_id` pour les deux premiers, `roomid` pour le dernier ;
- un attribut de shortcode est un **défaut, pas une contrainte** : Vik l'injecte par `def()`, qui cède devant un paramètre GET ou POST de même nom. La couche 1 n'est donc opposable sur aucun écran.

La couche 2 est le seul mécanisme réellement opposable. La greffer sur `vikbooking_before_create_booking_record`, qui se déclenche avant l'insertion.

### 4.4 E-mails

C'est le point dur du chantier, pas le tunnel.

**Émission.** Vérifier en phase 0 si Vik passe par `wp_mail`. Le point d'accroche dépend de la réponse et doit être constaté dans le code, pas supposé.

**Résolution de marque.** L'expéditeur ne peut pas se déduire de l'hôte de la requête : un envoi peut partir d'une action en administration, d'un cron ou d'une réservation OTA. La marque se résout depuis **la réservation concernée**, donc depuis sa chambre. Si la marque reste indéterminée, l'envoi part sous un expéditeur neutre, journalise un avertissement et alerte. **Jamais sous la mauvaise marque.**

**Contenu. Corrigé après la phase 0 : on réécrit le message en vol, on n'en émet pas un second.** Aucun réglage de Vik ne permet de désactiver les e-mails clients, et le hook d'envoi est un `do_action` incapable d'annuler. On se greffe donc sur **`vikbooking_before_send_booking_mail`**, qui reçoit `[$who, $booking, $mail]`, et on réécrit expéditeur, adresse de réponse, objet et corps par les mutateurs de `VBOMailWrapper`. Un seul chemin d'envoi, donc aucun risque de doublon, et les pièces jointes iCal de Vik sont conservées.

Le hook ne transporte pas les identifiants de chambre : les relire dans `sir_vikbooking_ordersrooms` par `idorder`, puis résoudre la marque par le registre.

Périmètre strict : on ne réécrit que si `$who` vaut `guest` et que `$booking['channel']` est nul, c'est-à-dire une réservation directe. Ne pas toucher aux messages liés aux canaux OTA, qui ont leurs propres règles.

Piège relevé dans `VBOMailWrapper` : passer la même chaîne comme adresse et comme nom d'expéditeur fait disparaître le nom.

**Les rappels avant séjour existent** : ils sont produits par une tâche planifiée définie dans Vik lui-même, et non par les émetteurs relevés en phase 0. **À vérifier en phase 3 :** que ce chemin passe bien par `sendBookingEmail`, donc par le même hook. S'il court-circuite `VBOMailWrapper`, le rappel partira sous l'expéditeur global et trahira la marque, ce qui en fait un critère de recette à part entière.

**Délivrabilité.** Un expéditeur `@sexcaperoom.ch` émis par le serveur qui héberge linstantcle.ch casse l'alignement DKIM et part en indésirable. Donc : SPF, DKIM et DMARC publiés sur sexcaperoom.ch, et un seul service d'envoi transactionnel authentifié pour les deux domaines. Recette : un envoi de test vers une boîte Gmail et une boîte Outlook, en-têtes vérifiés, `dkim=pass` et `spf=pass` pour les deux marques.

### 4.5 Paiement

Recommandation, contre la première intuition : **garder un seul compte Stripe** au lancement.

La séparation comptable s'obtient avec des métadonnées de marque sur chaque `PaymentIntent` plus un `statement_descriptor_suffix` par marque, ce qui donne à la fois la ventilation comptable et le libellé soft sur le relevé du client. Deux comptes obligeraient à faire cohabiter deux passerelles Stripe dans une configuration Vik dont on connaît la fragilité, pour un bénéfice que les métadonnées apportent déjà. Ouvrir un second compte plus tard reste possible et n'invalide rien de ce qui est construit ici : à décider avec la fiduciaire, pas avec le moteur.

**Retour après paiement.** L'URL de retour doit porter l'hôte de la marque. Vik peut la construire au moment de la création de la commande à partir de l'option `siteurl` stockée en base, ce qui contournerait la réécriture faite sur la requête : c'est précisément pourquoi `option_home` et `option_siteurl` sont filtrées. À tester explicitement, pas à déduire.

### 4.6 Cache, indexation, redirections

- Exclure l'hôte Sexcape Room et toutes les pages du tunnel de NitroPack et du cache dynamique SiteGround. Le cache est le premier suspect de tout comportement bizarre, en particulier sur Safari et iPhone.
- L'hôte de réservation porte `noindex`. C'est un tunnel, pas du contenu.
- linstantcle.ch cesse de porter les expériences chaudes : redirections 301 des anciennes URL vers sexcaperoom.ch, et `Redirection depuis` renseigné dans Airtable pour chaque URL changée.
- TranslatePress voit un second domaine sur la même installation : vérifier la licence et la structure d'URL avant de promettre l'anglais et l'allemand. Les chaînes propres à Vik passent par Loco.

---

## 5. Surveillance, la vraie réponse au grief contre Vik

Tout vit dans Make, équipe 2185539, **hors de WordPress**, parce qu'un site en panne ne peut pas alerter sur sa propre panne. Créneaux 05:30, 06:00 et 06:15 déjà pris : prendre 06:30. Alertes par le bot Telegram.

1. **Oracle tarifaire, quotidien.** Pour un jeu de scénarios figés couvrant semaine, weekend, haute saison et séjour minimum sur chaque chambre vendue, interroger le **prix public par requête HTTP**, jamais la base, et le comparer aux valeurs attendues tenues dans une table Airtable. Interroger le prix public plutôt que la base est un choix, pas un pis-aller : c'est le prix que le client voit qui compte, et c'est lui qui était faux pendant deux mois. Tout écart alerte.

Note d'accès : SiteGround **bloque le transfert de port SSH**, donc aucun tunnel vers MySQL depuis Make. Sans effet ici, l'oracle n'ayant pas besoin de la base. Les valeurs attendues viennent de la grille extraite une fois pour toutes en Q6 du constat et validée par Thomas. Si le chantier analytique a besoin d'un accès MySQL depuis Make, la seule voie restante est l'accès distant MySQL de Site Tools avec liste blanche d'adresses IP, à instruire séparément. C'est exactement ce qui aurait transformé le bug du tarif weekend en alerte du lendemain au lieu de deux mois de perte.
2. **Détecteur de silence Stripe, quotidien.** Aucun paiement abouti depuis 48 heures en pleine saison alerte. Un tunnel cassé se voit d'abord par l'absence de recette, pas par un message d'erreur.
3. **Réservation factice de bout en bout, hebdomadaire.** Sur la chambre de test 5, par l'hôte Sexcape Room puis par l'hôte L'Instant Clé, avec un mode de paiement hors ligne pour ne rien encaisser. Vérifier la création, l'expéditeur et le contenu de l'e-mail dans une boîte dédiée, puis annuler et supprimer.
4. **Veille de version, quotidienne.** Mise à jour automatique de Vik désactivée. Le scénario relève la version installée et alerte si elle a changé, ce qui déclenche une passe manuelle complète de la recette.

Règle de recette de la surveillance : **tester l'alarme, pas seulement le système.** Casser volontairement un tarif en préproduction et vérifier que le message Telegram arrive. Une alarme jamais déclenchée n'est pas une alarme.

---

## 6. Gestion d'erreur, exigence transverse

- Aucun échec silencieux. Chaque chemin de code personnalisé se termine par un succès explicite ou par une erreur journalisée.
- Une seule fonction de journalisation, préfixe unique, niveaux avertissement et erreur, plus alerte Telegram au-delà de l'avertissement, avec limitation de débit pour éviter la pluie de messages.
- Les trois cas qui doivent alerter sans exception : chambre absente du registre, marque indéterminée lors d'un envoi d'e-mail, tentative de réservation d'une chambre étrangère à l'hôte.
- Rien ne dépend d'une intervention humaine régulière. Le système tient seul ou il crie.

---

## 7. Phases

**Phase 0 — Constat, aucun code.** Répondre par la lecture du code et de l'hébergement, pas par déduction :

1. sexcaperoom.ch et linstantcle.ch sont-ils sur le même compte SiteGround, et le sous-domaine peut-il pointer sur la même installation ?
2. Vik envoie-t-il ses e-mails par `wp_mail`, et quels points d'accroche expose-t-il autour de la création et de la confirmation d'une réservation ?
3. `sir_vikbooking_calendars_xref` reflète-t-il les groupes du chapitre 2 : 1, 7 et 10 liés, 2 et 4 liés, 8 et 9 indépendantes ?
4. Vik sait-il filtrer nativement les chambres offertes, par catégorie ou par liste d'identifiants ?
5. Vik construit-il l'URL de retour de paiement depuis la requête ou depuis l'option `siteurl` en base ?
6. Les tarifs et saisons actuellement configurés correspondent-ils à la grille attendue, chambre par chambre ?

Livrable : une note de constat qui répond aux six points avec la référence du fichier ou de la table consultée. **Arrêt et validation par Thomas avant la phase 1.**

**Phase 1** — mu-plugin : registre, résolution de marque, réécriture d'URL consciente de l'hôte, écran de santé, journalisation.

**Phase 2** — filtrage des chambres, présentation puis garde.

**Phase 3** — e-mails par marque, gabarits propres, expéditeur, délivrabilité.

**Phase 4** — paiement : métadonnées, libellé, URL de retour, page de confirmation par marque.

**Phase 5** — surveillance dans Make et alertes Telegram.

**Phase 6** — recette complète, redirections, exclusions de cache, `noindex`, bascule.

Un commit par phase, une phase par session. Ne pas enchaîner deux phases sans validation.

---

## 8. Recette, critères de sortie

Chaque ligne est une assertion vérifiable, pas une impression.

1. Le code source de chaque étape du tunnel Sexcape Room ne contient **aucune** occurrence de `linstantcle`, `L'Instant Clé` ou d'une URL de média sur l'autre domaine.
2. La recherche sur l'hôte Sexcape Room retourne exactement les chambres 4, 9 et 10. Sur l'hôte L'Instant Clé, exactement 1, 2, 7 et 8.
3. Une URL fabriquée visant la chambre 1 sur l'hôte Sexcape Room est refusée, journalisée et alertée.
4. Réserver la chambre 10 rend les chambres 1 et 7 indisponibles sur les mêmes dates, et réciproquement. Réserver la chambre 4 rend la chambre 2 indisponible, et réciproquement.
5. Réserver la chambre 8 ne change rien à la disponibilité de la chambre 9, et réciproquement.
6. L'e-mail de confirmation d'une réservation Sexcape Room part de l'expéditeur Sexcape Room, avec le contenu et la signature de cette marque, et passe SPF et DKIM sur Gmail et sur Outlook.
7. Le retour après paiement Stripe atterrit sur sexcaperoom.ch, sur la page de confirmation de cette marque.
7 bis. Placées côte à côte, une page de sexcaperoom.ch et une page du tunnel montrent le même logo, la même palette, la même typographie et le même pied de page. Les deux vivent sur des serveurs différents : la continuité est tenue à la main, donc elle se vérifie à l'oeil à chaque livraison.
8. Le `PaymentIntent` porte la métadonnée de marque et le libellé attendu.
9. Le prix affiché pour un weekend, sur chaque chambre vendue, correspond à la grille tarifaire de référence.
10. Un tarif volontairement cassé en préproduction déclenche le message Telegram en moins de 24 heures.
11. Une réservation créée par le tunnel Sexcape Room arrive dans `sir_vikbooking_orders` avec la bonne chambre, et l'ingestion Airtable lui attribue la bonne marque sans intervention.
12. Les réservations OTA continuent d'entrer et de sortir par le channel manager sans changement de comportement.

---

## 9. Retour arrière

- Le mu-plugin est autonome : le retirer restaure le comportement actuel. Aucune modification dans les fichiers de Vik, dans le thème parent ou dans un plugin tiers.
- Toute modification faite dans l'administration de Vik est consignée avec sa valeur avant et après, dans `docs/briefs/journal-vik.md`.
- Instantané de base de données avant la première modification de configuration Vik, et avant la bascule de la phase 6.
- Le sous-domaine se retire en une opération DNS, sans toucher au code.

---

## 10. Garde-fous permanents

- Greffer par mu-plugin et hooks, jamais dans les fichiers de Vik, parce qu'une mise à jour effacerait tout et que Vik a déjà coûté 1800 francs sur un bug.
- Déduire la marque de l'identifiant de chambre, jamais de son nom, parce que les noms changent au marketing et que l'analytique Airtable repose déjà sur les identifiants.
- Laisser Vik seul maître de la disponibilité. Ne jamais recalculer une disponibilité en parallèle : deux calculs finissent par diverger et produisent une surréservation.
- Laisser les chambres 5 et 6 en chambres de test, hors affichage public et hors analyse.
- Ne pas approcher le flux Contenus, Assets et Campagnes d'Airtable, qui tourne en production.
- Vérifier chaque effet en français d'abord, le chemin des deux autres langues prévu mais non livré, parce que le lancement est francophone.
- Traiter NitroPack et le cache SiteGround comme premiers suspects de tout comportement inexpliqué du tunnel, en particulier sur Safari et iPhone.
- Aucun identifiant, aucune clé, aucun mot de passe dans le code ou dans un commit. Thomas les saisit lui-même.

---

## 11. Ce qui reste à trancher par Thomas

1. Registre en fichier de configuration, ou écran de réglages en administration pour qu'Aurore puisse l'éditer. Recommandation : fichier maintenant, écran plus tard si le besoin se manifeste.
2. Un compte Stripe avec métadonnées, ou deux comptes. Recommandation : un seul, à confirmer avec la fiduciaire.
3. Fréquence de la réservation factice de bout en bout : hebdomadaire suffit si l'oracle tarifaire tourne chaque jour.
4. Migration de `L'Entracte all inclusive` d'une chambre vers un plan tarifaire de la chambre 1. Hors périmètre de ce chantier : elle touche le moteur de prix, qui est précisément le composant qui a déjà coûté 1800 francs. À reprendre une fois l'oracle tarifaire de la phase 5 en service et éprouvé, jamais avant.
5. Emplacement physique réel de La Parenthèse et de L'Indécent, à confirmer une dernière fois : le brief les traite comme indépendantes en disponibilité, et la phase 0 le vérifie dans `calendars_xref`.
