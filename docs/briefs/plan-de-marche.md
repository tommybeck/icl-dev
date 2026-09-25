# Plan de marche — réservation Sexcape Room

**Version 2.20, 25 septembre 2026.** La version 2 du 17 septembre remplaçait la version 1 du 9, devenue fausse sur la moitié de ses lignes. La 2.20 établit que la marque d'expéditeur des confirmations arrive jusqu'au client, dans les deux marques, et ferme B9i.

Documents de référence : `sexcape-room-reservation.md` pour le quoi, `constat-phase-0.md` pour l'établi, `handoff-acces-mysql.md` pour les accès, `revue-tarifs.md` et `convention-tarifs-annuelle.md` pour le chantier A, `constat-reserve-paiement.md` pour la réserve de paiement, `constat-phase-3-emails.md` pour les e-mails, `constat-incident-1818.md` et `revue-constat-vikstripe-b4b.md` pour le défaut de réconciliation et le signalement à l'éditeur.

---

## 1. La ligne de partage

**Claude Code tient le dépôt. Cowork tient tout le reste.**

Ce n'est pas une répartition par difficulté, c'est une répartition par **matière** : Code manipule des fichiers versionnés et une ligne de commande, Cowork manipule des services distants, des arbitrages et des textes. Chacun est mauvais dans le métier de l'autre, et un travail rendu au mauvais endroit se paie en allers-retours.

| | Claude Code | Cowork |
|---|---|---|
| **Matière** | fichiers du dépôt, PHP, SQL, SSH, git | Airtable, Make, Elementor par MCP, Trello, Telegram, Drive |
| **Écrit dans** | `mu-plugins/`, `themes/`, `docs/constat-*.md` | `docs/brief-*.md`, `docs/revue-*.md`, `docs/plan-de-marche.md` |
| **Lit** | tout le dépôt, `.local/` en lecture seule | tout le dépôt, par le miroir en lecture seule |
| **Ne fait jamais** | écrire dans Airtable ou Make, déployer en production, modifier `.local/` | écrire une ligne de code du dépôt |
| **Modèle** | **Opus 5.5, effort moyen, par défaut** depuis le 25 septembre ; effort bas pour le mécanique, élevé sur les tâches marquées ; Sonnet 5 seulement pour les recherches en lecture seule | Opus 5.5 |

**Thomas seul** touche aux identifiants, au DNS, à Site Tools, aux comptes Stripe et Google, et à l'administration de Vik. Ni l'un ni l'autre des deux outils ne demande, ne détient ni ne saisit un secret.

**Le compte MySQL est en lecture seule, et le reste.** Décision du 12 septembre 2026. Code lit, vérifie et constate ; il n'écrit jamais dans la base. Quand une correction est nécessaire, la chaîne est toujours la même : Cowork la formule, Thomas l'exécute dans Vik, Code la vérifie par requête.

### Règle du fichier unique

**Un fichier, un auteur.** Code écrit les constats, Cowork écrit les revues et les briefs.

**`journal-vik.md` s'ouvre à Code, décision du 19 septembre 2026.** Il appartenait à Thomas seul. Désormais Code y écrit quand un fait est avéré ou quand Thomas annonce une action faite, et Cowork peut fournir les lignes toutes faites. Thomas garde l'autorité : il corrige ou il refuse. Motif : le journal accumulait du retard parce qu'il dépendait d'une seule personne, et c'est ce retard qui a coûté le constat B4b.

**Corollaire appris à nos dépens :** un brief écrit par Cowork hors du dépôt se **déplace** dans `docs/briefs/`, il ne se recopie pas. Deux exemplaires ont déjà divergé, et c'est la copie du dépôt qui était la mauvaise.

### Le dépôt est le seul canal

Rien d'important ne vit dans une conversation. Une conclusion qui n'est pas écrite dans `docs/` n'existe pas : la session suivante ne la connaîtra pas. **Corollaire :** un constat écrit dans `.local/`, qui est ignoré par Git, est invisible à tout le monde. C'est arrivé une fois, sur la question de `avail` en présentation.

---

## 2. Les chantiers

| Chantier | Tenu par | État au 20 septembre |
|---|---|---|
| **A. Tarifs** | Code, Thomas, Cowork | en cours, A1 à A4 faits, A5 à A8 ouverts |
| **B. Moteur** | Code | B1 à B5, B4a, B4b et B8 faits. **Rien n'est déployé, et le prochain geste est celui de Thomas.** Revue de B5 ouverte ; B6 et B7 attendent une décision |
| **C. Infrastructure** | Thomas | fait, sauf l'observation DMARC en cours |
| **D. Habillage** | Cowork puis Code | D1 et D2 faits, D3 et D4 ouverts |
| **E. Surveillance** | Cowork | pas commencé, attend A5 |
| **F. Contenu des e-mails de Vik** | Thomas | **le plus urgent des ouverts**, F3 avant la première vente des chambres 8, 9 et 10 |
| **G. Verrou d'hôte** | Cowork puis Code | G1 fait le 19 septembre, G2 lançable, G3 ajouté |
| **H. Signalement à l'éditeur** | Cowork puis Thomas | **nouveau**, ouvert par B4b |

### A. Tarifs

| # | Quoi | Qui | État |
|---|---|---|---|
| A1 | Anomalie B, saison à `idrooms` vide | Code | **fait** |
| A2 | Six requêtes de grille par SSH | Code | **fait** |
| A3 | Liste des corrections, `revue-tarifs.md` | Cowork | **fait**, version 2 du 12 septembre |
| A4 | Appliquer les corrections dans Vik | Thomas | **fait** pour les trois suppressions du 12 septembre |
| A5 | Valider la grille corrigée comme référence | Thomas et Cowork | **ouvert**, attend A8 ; débloque tout le chantier E |
| A6 | Fiche de saisie des lignes restantes | Code, Sonnet 5, lecture seule | **ouvert**, périmètre à confirmer, voir ci-dessous |
| A7 | Saisir ces lignes dans Vik | Thomas | ouvert |
| A8 | Vérifier par requête | Code, Sonnet 5, lecture seule | ouvert |

**Périmètre de A6, à confirmer avant de lancer.** Relevé en base le 14 septembre : `Fall vacation 2026 (rooms)` **existe** désormais, ligne 148, chambres 8 et 9, 30 CHF par nuit, donc elle sort du périmètre. En revanche **`Fall vacation 2027` n'existe pas du tout, ni villas ni rooms**, alors que `revue-tarifs.md` suppose la villa existante. Et `Carnival vacation 2027` n'a toujours pas son doublet « rooms ». Trancher ces deux points avant A6, sans quoi la fiche décrira des lignes qui n'ont pas de parent.

Reste ouvert sans instruction : l'asymétrie de tarification par occupation entre les chambres 1 et 10 et les cinq autres.

### B. Moteur

| # | Phase | Modèle | État |
|---|---|---|---|
| B1 | Registre, résolution de marque, URL par hôte, santé, journalisation | Sonnet 5, moyen | **fait** |
| B2 | Filtrage des chambres, présentation puis garde | Sonnet 5, moyen | **fait**, deux correctifs compris |
| B3 | E-mails par marque | Opus 5, élevé | **écrite et revue. Déployable** : C2 est fait |
| B4a | Réserve de paiement, lecture seule | Opus 5, élevé | **fait**, et la réponse est mauvaise, voir §6 |
| B4 | Paiement, retour, page de confirmation | Sonnet 5, moyen | livrée le 17 septembre, **cassée jusqu'au 22**, corrigée par B9c. **Toujours pas recettée** : elle n'a jamais tourné une seule fois. Critère de recette n°8 toujours ouvert |
| B4b | Examiner la nouvelle version de VikStripe avant d'écrire à E4J | Opus 5, élevé | **fait** le 18 septembre, commit `6fe7ef0`. C'est la même 2.2.4, le défaut est intact, aucun webhook. Revue du 19 : conclusion tenue, mais « elle n'a jamais tourné » n'est pas démontré |
| B5 | Marquer les rappels avant séjour, par `vikbooking_before_send_mail` | Opus 5, élevé | **fait** le 17 septembre, commit `c22f5b7`, `constat-b5-rappels.md`. **Revue faite le 24 septembre**, trois points ci-dessous |
| B6 | Parade de la réserve de paiement | **Opus 5, élevé** | **lançable** : la parade est tranchée le 21 septembre, voir ci-dessous. Passe après la revue de B5 |
| B7 | Identité d'envoi des repas et des bons cadeaux | Sonnet 5, moyen | ouvert, attend une décision de Thomas |
| B8 | Levier de préproduction et inventaire de déploiement | Sonnet 5, moyen | **fait** le 20 septembre, commit `fade08b`, `constat-deploiement-moteur.md`, revue faite. 120 tests au vert |
| B9 | Script de déploiement, de vérification et de retour arrière | Sonnet 5, moyen | **fait** le 21 septembre, commits `289339e` et `125e65b`, `constat-script-deploiement.md`, revue faite. Une réserve avant la production, ci-dessous |
| B9b | Réécriture d'URL en préproduction, et constat de l'erreur fatale | Sonnet 5, moyen | **fait** le 22 septembre, commit `cb46ba8`, deux constats, revue faite |
| B9c | Corriger `payment-brand.php`, et la prémisse fausse qui l'a produit | Sonnet 5, moyen | **fait** le 22 septembre, commit `f7a740a`, `constat-correctif-signature-paiement.md`, revue faite |
| B9d | Vérifier la signature réelle de chaque crochet auquel `lme-brands` s'accroche | Sonnet 5, faible | **fait** le 22 septembre, commit `4a34466`, `constat-signatures-crochets.md`, revue faite. Treize crochets, **aucun écart** hors celui déjà corrigé |
| B9e | Garde-fou de redirection des e-mails hors production | Sonnet 5, faible | **fait** le 22 septembre, commits `8327ba0` et `d9a123e`, `constat-mail-guard.md`, revue faite. Réserve levée |
| B9f | Recette automatisée : transcription d'enveloppe et `recetter-moteur.sh` | Sonnet 5, moyen | **fait** le 23 septembre, commit `42cc5b1`, `constat-recette-automatisee.md`, revue faite |
| B9h | Établir pourquoi la garde ne rend pas de `403` net en passe L'Instant Clé | Sonnet 5, faible | **fait** le 24 septembre, commit `5d498dc`. Le verrou de Vik posé par la propre réservation du script, pas la garde ; dates séparées, `403` dans les deux sens |
| B11 | Corriger `room-filter.php` et la vérification 2 | Sonnet 5, moyen | **fait** le 24 septembre, commit `d8c1753`, `constat-correctif-room-filter.md`, revue faite. Vérification 2 verte dans les deux sens |
| B9j | Inventorier les intégrations sortantes, refuser toute réservation si Vik Channel Manager est actif, durcir le garde-fou sur `phpmailer_init` | Sonnet 5, moyen | **fait** le 24 septembre, commit `fb92b1c`, `constat-integrations-sortantes.md`, revue faite. **Garde-fou 1.1.0 pas encore déployé** |
| B9i | Corriger le script de reprise, lire la transcription entière, établir le crochet du rappel et le refus d'annulation | Opus 5.5, moyen | **fait** le 25 septembre, commit `ce801e4`, `constat-reprise-1836-1837.md`, revue faite |
| B11b | Recenser les autres vues de Vik joignables par `view`, et corriger `constat-phase-0.md` Q4 | Sonnet 5, faible | ouvert, avant B10 |
| B9g | Faire relever par `recetter-moteur.sh` le lien Stripe Checkout sur la page du bouton PAY NOW, puis rendre la main au navigateur | Sonnet 5, faible | **fait** le 24 septembre, commit `f246db6`. Les deux passes atteignent Stripe Checkout, réservations 1828 et 1829 |
| B10 | Déployer en production, après la recette | Thomas, décision séparée | ouvert. **Prérequis restants : la vérification 7, B11b, G0 et G3** |

Après chaque phase : **revue par Cowork** avant d'ouvrir la suivante. **Cette règle a été enfreinte une fois** : B5 est livrée depuis le 17 septembre et n'a été revue par personne, tombée entre la revue de la phase 4 et l'incident 1818 du même jour.

**Point à vérifier dans la revue de B5 :** le constat justifie de ne pas poser l'adresse de réponse par marque au motif que `reservations@sexcaperoom.ch` n'existe pas encore, alors que C2 est donné pour fait depuis le 15 septembre, groupe Google compris. L'une des deux lignes est périmée.

**Pourquoi B8 existait.** Le plan disait « ne déploie rien » à chaque phase et ne disait nulle part qui déploie, où, ni selon quelle recette. Sept phases écrites, aucune en service : le 19 septembre, `https://reservation.sexcaperoom.ch/` servait la page d'accueil de L'Instant Clé, sans habillage.

**Ce que B8 a trouvé, et qui justifie à lui seul le détour.** `themes/astra-child/functions.php` et `style.css` du dépôt n'étaient pas des copies du serveur : ils avaient été écrits le 9 septembre sous l'hypothèse, jamais vérifiée, qu'on récupérerait le vrai thème par SFTP. Les déployer aurait **effacé en silence 7 390 octets de logique de production réelle** — suivi de conversion, règles de référencement, calendrier Vik — sans une seule erreur PHP. Le constat de D2 avait hérité de la même hypothèse et affirme encore aujourd'hui qu'une feuille `astra-child-style` existe sur le serveur, ce qui est faux : le handle réel est `astra-child-theme-css`. **`constat-habillage-tunnel.md` est donc à corriger sur ce point**, sans quoi le prochain lecteur refera l'erreur.

**Ce que B8 a établi et qu'il ne faut pas perdre de vue.** Rien dans ce déploiement n'est isolé à l'hôte de réservation : `lme-brands` est un mu-plugin accroché à des hooks de Vik Booking, installation unique partagée par les deux marques. Dès l'upload, et sur linstantcle.ch aussi, le filtrage de présentation retire un paramètre de chambre étrangère, la garde refuse une réservation hors marque par un 403, et chaque paiement Stripe reçoit une métadonnée de marque. Ce sont les correctifs recherchés, mais ce sont des changements de comportement du parcours de la marque qui vend aujourd'hui. **D'où B10 : le déploiement en production est une décision distincte de la recette, avec sa propre fenêtre**, et non la suite mécanique d'une préproduction verte.

### B9 — le déploiement passe par un script

**Décision du 20 septembre.** Le chapitre 6 de `constat-deploiement-moteur.md` décrit une douzaine de copies et cinq vérifications à la main, à refaire deux fois. Un protocole manuel exécuté deux fois n'est pas exécuté deux fois de la même façon : c'est la classe d'erreur que B8 vient d'éviter de justesse sur le thème. **Le même script tourne sur `staging13` puis en production**, et la symétrie devient une propriété du geste plutôt qu'une intention.

**Ce que le script doit faire, et qui n'est pas une simple copie :**

- **La fusion de `functions.php` n'est pas un remplacement.** Le fichier réel diffère entre les deux environnements et porte de la logique de production. Le script ajoute la ligne `require_once` si elle est absente, ne la duplique jamais, et **refuse de continuer** si le fichier cible ne ressemble pas au thème réel, contrôle de taille du chapitre 6 à l'appui.
- **Préalables refusants.** Le script s'arrête plutôt que de deviner : `wp_get_environment_type()` conforme à l'environnement visé ; empreintes attendues de ce qui est déjà en place ; et sur la préproduction, **VikStripe en clés de test**, vérifié par le seul préfixe `sk_test_` contre `sk_live_`, jamais par la valeur.
- **Simulation par défaut.** Sans option explicite, il imprime ce qu'il changerait et ne touche à rien. C'est le mode dans lequel on le lit avant de le croire.
- **Sauvegarde et retour arrière.** Il prend sa propre sauvegarde avant d'écrire et porte un `--rollback` qui la remet. Le chapitre 7 du constat devient exécutable au lieu d'être une procédure.
- **Idempotent.** Deux exécutions de suite donnent le même état, et la seconde ne signale rien à changer.

**Qui l'exécute.** Code le lance sur la préproduction. **Thomas seul le lance en production**, la règle de `CLAUDE.md` valant pour l'outil comme pour la main.

**Le point dur de B8.** Le registre résout la marque par l'hôte exact. Sur `staging13.linstantcle.ch` il ne résout rien, et c'est voulu (`registry.php` : « un hôte qui n'est celui d'aucune marque retourne null : on ne devine jamais une marque »). La préproduction ne peut donc pas exercer le chemin Sexcape Room sans un levier explicite : une constante lue seulement quand `wp_get_environment_type()` vaut `staging`, définie dans le `wp-config.php` de la préproduction et absente partout ailleurs.

**Avertissement, avant tout essai de paiement en préproduction.** VikStripe y utilise les clés que porte la base copiée, c'est-à-dire **les clés de production**. Un test écrirait dans le Stripe réel, créerait des sessions parasites et fausserait la réconciliation que B6 doit construire. Basculer la préproduction sur les clés de test d'abord, geste de Thomas.

**Adresse destinataire des essais : une adresse jetable suffit, et c'est décidé.** La recette vérifie les en-têtes et l'identité d'envoi, pas le contenu. D4 ne bloque donc pas la recette.

### Ce que la recette automatisée a trouvé — 23 septembre

`constat-recette-automatisee.md`, commit `42cc5b1`. Le script existe, il tourne, et il a rendu plus que ce qu'on lui demandait.

**La garde de réservation fonctionne, et c'est la seule garantie qui compte.** Vérification 3a nette et reproductible dans les deux sens : une tentative de réserver la chambre étrangère active est refusée par un `403` portant le titre de `lme_brands_reject_booking_attempt()`, jamais un refus natif de Vik.

**Un défaut réel, mais de faible portée, tâche B11.** Sous le levier Sexcape Room, une URL portant `?view=roomdetails&roomid=2` fait charger **L'Aparté** au composant de réservation de Vik, et l'inverse sous l'autre levier. Reproduit sur cinq requêtes consécutives, avec un paramètre anti-cache différent à chaque fois, donc ce n'est pas du cache. `room-filter.php` **s'exécute pourtant** : `debug.log` porte `foreign_room_param_stripped` avec le bon identifiant. La chaîne de références de `JInput` a été relue jusqu'à la source et **devrait** refléter l'`unset()`. Code s'arrête là plutôt que de deviner la cause, et il a raison : c'est deux fois que ce dépôt paie une hypothèse non vérifiée. **Portée, établie par Cowork au navigateur le 24 septembre, en comparant la même page avec et sans le paramètre.**

- **Sans paramètre**, la page du Boudoir porte **un seul** composant de réservation Vik, celui du Boudoir, chambre 4. Son bloc descriptif est masqué par la mise en page de la page elle-même ; **le calendrier et le formulaire de réservation sont visibles**. C'est le comportement normal de Vik : un composant par page, pour la chambre de la page. **Aucun formulaire des autres chambres n'est présent dans le DOM**, donc ni surcharge ni lenteur à craindre.
- **Avec `?view=roomdetails&roomid=2`**, le même composant unique **bascule sur L'Aparté** : le formulaire visible porte `roomdetail = 2`, et le calendrier renvoie vers la page de L'Aparté.

**Donc en mode Sexcape Room, avec ce paramètre, L'Aparté est bel et bien proposé à la location, dans le formulaire visible.** Une première lecture, faite sur le seul bloc descriptif masqué, avait conclu l'inverse : c'était faux.

**Pourquoi c'est bloquant pour B10, et c'est la raison décisive.** Le paramètre `roomid` n'est pas qu'une URL fabriquée à la main : la navigation de Vik elle-même en produit, et ces URL circulent et s'indexent. **Aujourd'hui, sans `lme-brands`, un client qui suit un tel lien réserve la chambre étrangère, et la réservation aboutit.** Après B10, le même client remplira le formulaire jusqu'au bout et recevra un `403` de la garde à la dernière étape. **Déployer sans B11 transforme un parcours qui fonctionne en impasse**, sur linstantcle.ch aussi, la marque qui vend aujourd'hui. La garde a raison de refuser ; c'est à la présentation de ne jamais proposer ce qu'elle refusera.

**Le blocage des vérifications 5, 6 et 7 tient au script, pas à un réglage. Correction du 24 septembre.** Le constat attribuait l'arrêt au paramètre `skipbtn = 1` de la passerelle Stripe, et la 2.14 en tirait un geste pour Thomas. **C'était faux, et le geste est retiré.** Lu dans `wp-vikstripe/stripe.php:233` : `skipbtn` est le paramètre **« Auto-redirect »** de VikStripe, aux options **inversées**, `1 => No` et `0 => Yes`. `skipbtn = 1` signifie donc que le client voit le bouton **PAY NOW** et doit cliquer ; il ne contourne pas Stripe. Et la redirection automatique, quand elle est active, est un **script JavaScript** (`stripe.php:549`), qu'un parcours en `curl` n'exécute pas davantage.

**La vraie cause** : le script s'arrête sur la page qui porte le bouton, sans le suivre. La commande reste en `standby` parce que personne n'a payé, et aucun e-mail ne part avant paiement. **Le correctif est dans le script** : le lien de Stripe Checkout est l'attribut `href` du premier enfant de `.stripe__payment__form__wrapper`, lisible dans le HTML sans rien exécuter. Le script le relève, le rend, et l'étape au navigateur de Cowork reprend à partir de là, comme prévu par le brief.

**Ne pas toucher au réglage.** Le basculer ne débloquerait rien pour le script, et le faire un jour en production changerait le parcours des clients sans raison.

**Et une promesse de mon brief qui ne tenait pas.** J'avais posé que `--nettoyer` annulerait les réservations d'essai par le contrôleur de Vik. `task=docancelbooking` exige `status = 'confirmed'` : une commande `standby` y répond `403`, testé pour de vrai. **La règle de nettoyage change donc** : ce que le script ne peut pas annuler, il le **marque et le recense**, et la reprise revient à l'administration de Vik ou au balayage de la parade D. Deux réservations restent sur la préproduction, **1826** et **1827**, au nom `RECETTE AUTOMATISEE - NE PAS TRAITER`, sans paiement.

### La marque d'expéditeur arrive jusqu'au client — 25 septembre

**Premier parcours complet sans Vik Channel Manager** : réservations d'essai 1836, Boudoir, et 1837, L'Aparté, `confirmed` et payées, relues en base et sur la vraie page de réservation. `constat-reprise-1836-1837.md`.

**Vik envoie deux messages par réservation**, et c'est ce qui distingue le client de l'administrateur dans la fourre-tout : le message client, sans numéro dans l'objet, et la copie à `info@maisonnette-enchantee.ch`, avec le numéro, `#1836`.

| Réservation | Message client, `From` composé | Message client, `From` reçu | Copie administrateur, `From` |
|---|---|---|---|
| 1836, Sexcape Room | `Sexcape Room <reservations@sexcaperoom.ch>` | `reservations@sexcaperoom.ch` | `L'Instant Clé <info@maisonnette-enchantee.ch>`, voulu |
| 1837, L'Instant Clé | `L'Instant Clé <reservations@linstantcle.ch>` | `reservations@linstantcle.ch` | idem |

**La vérification 5 est établie de bout en bout** pour les confirmations : composée juste par WordPress, transcription à l'appui, et reçue telle quelle, en-têtes bruts relus par Thomas dans la fourre-tout. **Le relais Gmail n'efface pas l'expéditeur.** La copie administrateur garde l'expéditeur natif de Vik, comme la phase 3 le prévoit. À confirmer d'un coup d'œil à la prochaine occasion : le nom affiché du message client de 1836 est bien « Sexcape Room ».

**Une conclusion inverse, tirée trop vite, est corrigée ici.** Cowork avait d'abord conclu que Gmail réécrivait l'expéditeur, sur la foi de deux lignes `From` qui étaient celles des copies administrateur, sans demander de quel message elles venaient. L'objet l'aurait dit. La tâche C6 qui en découlait, déclarer des alias d'envoi dans Google Workspace, est retirée : **rien n'est à faire de ce côté.**

**Trois autres choses établies par B9i.**

- **La vérification 6c était verte par construction** jusqu'ici : le script lisait une adresse qui rend la page d'accueil. Elle lit désormais la vraie page de réservation et la base.
- **Aucune réservation d'essai ne s'annule en libre-service** : le réglage d'annulation de Vik est « Disabled, with Request » ; la page ne propose qu'une demande, qui n'annule rien. Annulation à la main dans Bookings, ou rien : sur la préproduction, sans Vik Channel Manager, ces réservations sont inertes.
- **La vérification 7 refuse désormais de déclencher le rappel**, parce qu'il écrit à toutes les arrivées réelles de sa fenêtre. Elle rend `??`. **L'exercer est une décision de Thomas**, voir chapitre 4.

**Une contradiction à trancher entre deux constats** : `constat-integrations-sortantes.md` dit `DISABLE_WP_CRON` absent, `constat-reprise-1836-1837.md` le dit à `true`. Elle décide si des tâches peuvent partir d'elles-mêmes sur la préproduction.

### Vik Channel Manager se retire, il ne se désactive pas — 25 septembre

`constat-recette-vcm-inactif.md`. La désactivation posée contre l'incident faisait planter **toute** réservation sur la préproduction : dans `saveorder()`, Vik Booking teste `class_exists('VCMRequestAvailability')`, que son propre autochargeur satisfait **dès que les fichiers existent**, puis charge Vik Channel Manager à la main, qui plante faute de sa constante d'amorce. Les autres appels, dont `notifypayment()`, testent la présence d'un fichier. **Établi par lecture de Vik Booking 1.8.15 sur la préproduction.**

**La parade est de retirer le dossier**, geste de Thomas, chapitre 4. Sans les fichiers, aucun des trois appels du frontal n'est pris, et aucune adresse d'e4jConnect n'existe plus sur le serveur. Les parades par mu-plugin sont écartées faute de pouvoir se prouver. **Seul effet connu** : une page qui porterait le module d'avis des plateformes planterait. Aucune n'est vérifiée comme le portant.

Le script ne déclare plus « non créée » une réservation insérée malgré une erreur : il la cherche en base par son adresse d'essai. **À noter aussi** : la copie `.local/vikbooking` est en 1.8.14, la préproduction en 1.8.15.

### Incident — la préproduction a poussé sa disponibilité vers les plateformes — 24 septembre

`incident-preproduction-vers-plateformes-2026-09-24.md`. **Clos le jour même.**

**Ce qui s'est passé.** Vik Channel Manager était actif sur `staging13`, avec la configuration de la production. Chaque réservation d'essai créée depuis le 21 septembre, de 1822 à 1832, a déclenché une mise à jour de disponibilité, et **Airbnb, Booking.com et Expedia ont répondu OK** à chacune. Établi en lecture seule dans `sir_vikchannelmanager_notifications`.

**Ce qui a réellement été exposé, établi après coup en production.** Seules trois chambres sont reliées aux plateformes (`sir_vikchannelmanager_roomsxref`) : L'Entracte et L'Aparté sur les trois, Le Boudoir du Désir sur Airbnb seul. Mais les calendriers partagés de Vik (`sir_vikbooking_calendars_xref`) propagent une réservation à la villa entière : 2 ↔ 4 pour la villa Aparté, 1 ↔ 7 ↔ 10 pour la villa Entracte. **La réservation d'essai d'À Huis Clos du 28 septembre a donc fermé L'Entracte sur les trois plateformes**, et celles du Boudoir ont fermé L'Aparté. La note d'incident listait À Huis Clos, qui n'est sur aucune plateforme : c'était imprécis.

**Réparé.** Thomas a poussé depuis la production la disponibilité réelle, notification 4033 du 24 septembre à 14 h 16 UTC : **L'Aparté et L'Entracte sur les trois plateformes, Le Boudoir sur Airbnb, du 24 septembre 2026 au 24 septembre 2027**, toutes réponses OK. C'est exactement l'ensemble des couples chambre et plateforme existants, sur une période qui couvre toutes les nuits touchées.

**Ce qui empêche la récidive.** Vik Channel Manager et MailPoet sont désactivés sur `staging13`, vérifié en base. `recetter-moteur.sh` refuse désormais de créer ou d'annuler une réservation si Vik Channel Manager est actif sur la cible. Et la recréation de la préproduction gagne une étape obligatoire, chapitre 4.

**Ce qui reste en attente sur la préproduction** : dix verrous Vik Channel Manager jamais traités et les réservations d'essai. **Tant que le greffon reste inactif, ils ne poussent rien. S'il était réactivé, la tâche `pending_locks` repartirait au premier passage du cron.**

### Les intégrations sortantes de la préproduction — B9j

`constat-integrations-sortantes.md`. Inventaire complet, lu en base et dans le code, secrets relevés comme présents ou absents, jamais affichés.

- **WooCommerce Payments est en mode réel sur la préproduction**, compte de production. Une commande WooCommerce payée sur `staging13` débiterait une vraie carte. **Aussi grave que des clés Stripe réelles**, et rien dans la recette ne le vérifiait. PayPal, mode non relu.
- **Les deux greffons maison n'envoient rien**, mais la **clé de production ouvre aussi la préproduction** : un consommateur pointé sur la mauvaise URL y lirait les clients d'essai, ou un calendrier iCal y fermerait des nuits par une autre porte.
- **WP-Cron ne tourne pas sur la préproduction.** Tout ce qui est planifié est en retard et partira d'un coup au premier passage : les rappels de Vik sur les **vraies réservations copiées**, et Vik Channel Manager s'il était réactivé.
- **Le garde-fou de messagerie reprend la main en dernier sur `phpmailer_init`** : hors production, il vide tous les destinataires et ne remet que l'adresse fourre-tout, et il **journalise** toute adresse ajoutée après son filtre. Vérifié contre les vrais `plugin.php`, `WP_Hook` et PHPMailer de la préproduction, avec un faux greffon enregistré après lui à la même priorité. **Sa version 1.1.0 n'est pas encore déployée.**
- **Ce que le garde-fou ne couvre pas, et ne peut pas couvrir** : ce qui ne passe pas par `wp_mail()`. MailPoet d'abord. La seule parade est de le désactiver sur la préproduction.

**Rouvrir l'envoi dans WP Mail SMTP attend donc deux choses** : le déploiement du garde-fou 1.1.0 par `deployer-moteur.sh`, et WooCommerce Payments désactivé.

**Deux recommandations du constat, une écartée et une retenue pour un autre motif, décisions du 24 septembre.** Le §5 de `constat-integrations-sortantes.md` proposait de régénérer les clés des deux greffons maison et de couper la traduction automatique sur la préproduction. **Le plan fait foi.**

- **Les clés des greffons maison.** Elles ne protègent que d'un consommateur pointé par erreur sur une URL de préproduction. Aucun appel à ces points d'accès n'apparaît dans les journaux de `staging13`, et le sous-domaine change à chaque recréation, `staging10` puis `staging13` : personne ne peut y avoir branché un consommateur stable. Les régénérer à chaque recréation serait un geste de plus sans risque réel en face.
- **La traduction automatique, finalement coupée sur la préproduction**, décision de Thomas du 24 septembre, parce qu'elle y consomme des crédits DeepL. La production n'est pas concernée : la décision de `wordpress-et-web.md` 1.4 y tient. **Conséquence à connaître** : sur la préproduction, une chaîne que la production afficherait traduite peut s'afficher en anglais. Aucune vérification de la recette ne porte sur le texte traduit, donc rien n'en dépend ; seul un contrôle visuel en français ou en allemand pourrait en être trompé.

### La première reprise, et ce qu'elle établit — 24 septembre

**Le paiement de la phase 4 aboutit pour la première fois.** Les réservations 1830 et 1831 sont `confirmed` dans Vik après le paiement de test, relu dans `sir_vikbooking_orders`. `constat-reprise-1830-1831.md`.

**La vérification 5 n'est pas établie, et pas pour la raison que dit le rapport.** Le script n'affiche que la **dernière** ligne de la transcription. Les deux paiements ayant eu lieu avant la première reprise, tous les e-mails étaient déjà transcrits : « rien pour la 1830 » est un effet d'affichage. **Il faut lire la transcription entière.** La ligne affichée est la copie de l'administrateur, que la phase 3 laisse délibérément intacte : son expéditeur ne dit rien de la marque du message client.

**Et même lue en entier, la transcription ne suffira pas.** Elle enregistre ce que WordPress **compose**. Le relais Gmail décide ensuite de l'expéditeur réel et remplace toute adresse qui n'est pas un alias d'envoi vérifié. Or `reservations@<marque>` est un **groupe** Google, qui n'émet pas par défaut : la famille exacte du piège `from_email_force` du 15 septembre. **La preuve finale est une lecture des en-têtes bruts dans la boîte fourre-tout**, une fois l'envoi rouvert. Elle vaut pour B3 et B5 à la fois.

**Quatre défauts du script, relevés par lui-même, et le refus d'annulation** : sortie en `0` malgré des vérifications au rouge ; vérification 5 liée à n'importe quelle ligne de l'hôte ; `--reprise` et `--nettoyer` qui sautent les préalables, dont `environment: staging` ; le nom du crochet du rappel **déduit**, et faux ; et `docancelbooking` qui répond `403` sur une réservation `confirmed`, cause non établie. Tâche **B9i**.

### Ce que B11 a trouvé — 24 septembre

**La cause était le moment, pas le chemin.** Établi par trace d'exécution : Vik dispatche son contrôleur **pendant `init`**, priorité 10, et met le HTML en réserve avant tout `template_redirect`. `room-filter.php` retirait bien le paramètre, mais une fois la page rendue. Accroché désormais sur `init` priorité 1, et un retrait trop tardif **alerte** au lieu de se taire.

**Un second vecteur, trouvé en mesurant.** `?view=availability` et `?view=roomslist` affichaient les sept chambres des deux marques sur n'importe quelle page. Fermé, y compris contre les variantes d'encodage trouvées à la revue.

**Ouvert, tâche B11b** : six autres vues de Vik joignables par `view` n'ont pas été mesurées ; et `constat-phase-0.md` Q4 affirme qu'aucun crochet n'existe avant l'affichage d'une vue, alors que `vikbooking_before_display_<vue>` existe. **Une seconde prémisse fausse dans le constat de phase 0**, à corriger à la source comme Q5.

### La revue de B5 — 24 septembre

**La conception est bonne.** La réservation se retrouve par le magasin natif de Vik et ne se croit que si son client figure parmi les destinataires ; en cas de doute, rien ne change. Le crochet passe **avant** le garde-fou de messagerie, donc il voit le vrai client.

1. **Son verrou de déploiement est périmé** : il attend C2, fait depuis le 15 septembre. Reste la question du relais Gmail ci-dessus.
2. **Le périmètre dépasse le rappel** : pré-enregistrement, factures, messagerie en lot, et **le message que Thomas envoie à la main depuis l'écran d'une réservation**, qui partira sous la marque de la réservation.
3. **L'adresse de réponse diffère** entre confirmation et rappel ; un client Sexcape Room qui répond au rappel voit `maisonnette-enchantee.ch`. Le motif de B5, « la boîte n'existe pas », ne tient plus depuis C2. **Décision de Thomas**, sans urgence.

### La première recette complète — 24 septembre

`recetter-moteur.sh --appliquer`, les deux passes d'un seul trait, levier restauré à sa valeur d'entrée à la fin.

| # | Sexcape Room | L'Instant Clé |
|---|---|---|
| 1 | OK | OK |
| 2 | KO, **mais la mesure est fausse**, voir ci-dessous | idem |
| 3a | **OK**, `403` net | **ambigu** : `200` sur `/fr/reserver/`, ni refus ni `sid`. Tâche B9h |
| 3b | non concluant, connu | non concluant, connu |
| 4 | OK | OK |
| 6a, 6b | OK, Stripe Checkout atteint, réservation 1830 | OK, réservation 1831 |
| 5, 6c | à la reprise, après paiement de test | idem |
| 8 | KO annoncé, G2 absent | — |

**La vérification 2 cherche le nom de la chambre étrangère dans toute la page.** Or la préproduction est une copie de linstantcle.ch, dont le menu de navigation nomme L'Aparté, L'Entracte et Le Boudoir du Désir sur chaque page. **Le KO des quatre vues est donc garanti par construction**, que `room-filter.php` fonctionne ou non. Seul le KO de `roomdetails` est établi pour de vrai, par comparaison au navigateur avec et sans paramètre ; les trois autres vues ne sont pas mesurées. **La vérification doit porter sur les identifiants de chambre dans les formulaires et les conteneurs de Vik** — `roomdetail`, `roomid`, les résultats de recherche — que le menu ne contient jamais. C'est la faute de mon brief, qui demandait une « assertion d'absence » sans dire de quoi.

**La garde n'a pas répondu nettement dans le sens L'Instant Clé.** En passe Sexcape Room, la tentative de réserver L'Aparté est refusée par un `403` : c'est ce qui était déjà établi. En passe L'Instant Clé, la tentative de réserver le Boudoir aboutit à une page `200` sur `/fr/reserver/`, sans refus et sans `sid`. **C'est précisément le sens qui protège linstantcle.ch, la marque qui vend.** Une soumission incomplète, que `saveorder()` renvoie sans bruit vers la recherche, produirait ce signal, mais c'est une hypothèse. Tâche B9h, avant B11, parce qu'on ne corrige pas la présentation tant que la seule garantie opposable n'est pas prouvée dans les deux sens.

### Les prérequis dormants, relevés le 23 septembre

`prerequis-dormants-2026-09-23.md` remonte **vingt-trois prérequis** qui vivaient dans des briefs et des constats sans être portés ici, dont **huit bloquent une tâche nommée**. Ceux qui touchent une tâche lançable sont entrés dans les tableaux ci-dessus : la page 6586 pour G2, le nouveau G2b, et la recette en six points pour G3. Les autres sont dans le relevé, avec leur source et leur destinataire.

**Trois à traiter avant la prochaine recette.** La commande de test **1822**, toujours en `standby`, verrouille la chambre 4 sur la préproduction. L'exemplaire de `tests/test-core.php` **déjà déployé** n'a pas été retiré : l'exclusion ne vaut que pour les déploiements futurs, `rsync` étant additif. Et `revue-constat-vikstripe-b4b.md`, que ce plan cite trois fois, **n'est pas dans le dépôt**, ce qui rend H1 lançable sur le papier seulement.

**Deux trous dans la recette elle-même.** La vérification n°6 ignore `cancel_url` et les réservations à marques mêlées, qui sont **les deux seuls chemins où la métadonnée de marque peut être fausse** (`constat-phase-4-paiement.md`). Et la vérification n°4 est câblée pour la passe Sexcape Room : le script sortira en erreur pendant la passe L'Instant Clé, limite du script et non défaut du moteur (`constat-recette-moteur.md`).

**La règle qui en sort, et elle vaut pour Code comme pour Cowork.** Un brief ou un constat qui pose un prérequis le **remonte au plan dans la même passe**. Le plan est la seule chose qu'on rouvre à chaque session : ce qui n'y est pas ne sera pas fait. Vingt-trois points viennent de le démontrer d'un coup.

### La recette du moteur — les huit vérifications

À mener après un déploiement réussi en préproduction. C'est ce que les autres chapitres appellent « la recette ».

**En deux passes, une par marque, et c'est obligatoire.** Le levier ne porte qu'une marque à la fois : tant que `LME_BRANDS_HOST_OVERRIDE` vaut `reservation.sexcaperoom.ch`, **la préproduction entière est Sexcape Room**. Basculer la constante sur `linstantcle.ch`, avec `--forcer-override`, pour la seconde passe. La vérification n°4 n'est pas vérifiable autrement, et c'est ce qui a égaré la première recette du 21 septembre.

| # | Ce qu'on vérifie | Où |
|---|---|---|
| 1 | L'hôte résout la bonne marque, et un hôte inconnu n'en résout aucune | préproduction |
| 2 | Les chambres des autres marques ne sont servies par aucune des quatre vues publiques | préproduction |
| 3 | La garde refuse une chambre hors marque et une chambre désactivée | préproduction |
| 4 | L'apparence Sexcape Room s'applique en passe Sexcape Room, et aucun jeton `--srlm-*` n'apparaît en passe L'Instant Clé | préproduction, deux passes, puis production |
| 5 | Les en-têtes d'une confirmation portent l'expéditeur de la marque, pour les deux marques | préproduction, puis une vraie confirmation en production |
| 6 | La session Stripe porte la métadonnée de marque, l'URL de retour pointe l'hôte appelant, la page de confirmation s'affiche | préproduction, clés de test |
| 7 | Le rappel avant séjour part sous la bonne identité | préproduction, après la revue de B5 |
| 8 | Hors liste blanche, l'hôte redirige vers `sexcaperoom.ch` en 302 | préproduction puis production |

### Ce que la première recette a trouvé — 21 septembre

**Deux observations n'en sont pas.** Sur `staging13`, seules les trois expériences Sexcape Room sont servies et L'Aparté est refusée : c'est le levier qui fait son travail, la préproduction entière étant Sexcape Room tant que la constante vaut l'hôte de réservation. D'où la recette en deux passes ci-dessus.

**Un défaut réel du levier.** Le levier force l'hôte de résolution, et `url-rewrite.php` réécrit ensuite les URL vers le `host` **déclaré au registre**, qui est celui de la production. Relevé dans le DOM de `staging13` le 21 septembre : les feuilles de style et les polices sont chargées depuis `https://reservation.sexcaperoom.ch/`, alors que `sexcaperoom-tunnel.css` répond bien en 200 sur `staging13` lui-même. La préproduction mélange donc deux hôtes, ce qui explique le calendrier à moitié habillé qu'a vu Thomas, **et rend la vérification n°4 sans valeur tant que ce n'est pas corrigé.** Nul en production, où le `host` déclaré est le bon.

**Une erreur fatale.** Réservation de test du Boudoir, une nuit, 253 CHF : le récapitulatif s'affiche, puis « Une erreur grave s'est produite sur ce site ». Après création de la commande, pendant le rendu de la page de paiement. `payment-brand.php` est le premier suspect, et la question décisive est de savoir si la cause tient au levier ou si elle frapperait aussi la production.

Les deux vont à **B9b**, brief `brief-correctif-levier-et-fatal-paiement.md`.

### B9b et B9c — la phase 4 n'avait jamais fonctionné

**Établi le 22 septembre.** À `payment-brand.php:73`, `$args[0]` était lu alors que le rappel reçoit l'objet de paiement directement. Erreur fatale avant même le test `isDriver('stripe')`, donc **avant toute résolution de marque** : le paiement de toute réservation était cassé, les deux marques, les deux environnements, pour n'importe quelle passerelle, dès que ce mu-plugin est actif. Aucun client touché, rien n'étant déployé en production.

**La cause, corrigée à sa source.** Vik appelle bien `do_action($hook, array(&$this))`, mais `do_action()` **déballe** ce motif — un tableau d'un seul élément contenant un objet — par compatibilité ascendante avec le style PHP4. La prémisse de `constat-phase-0.md` §Q5 était la lecture littérale du code de Vik seul : juste pour `payment.php:329` pris isolément, fausse une fois WordPress ajouté. Corrigée à la source, le paragraphe fautif conservé et désigné comme faux plutôt que remplacé en silence. Trois tests ajoutés, qui échouent sur le code du 17 septembre.

**Deux correctifs d'hôte, le même.** `url-rewrite.php` (B9b) et `lme_brands_correct_payment_urls()` (B9c) visaient le `host` déclaré au registre ; les deux visent désormais l'hôte réel de la requête, la marque résolue ne servant plus qu'à décider s'il faut réécrire. En production le geste est un no-op par construction, en préproduction il garde tout sur la préproduction.

**Ce qui reste.** La phase 4 est corrigée, elle n'est pas recettée : elle n'a jamais tourné une seule fois de bout en bout. La vérification n°6 sera la première à la mettre à l'épreuve.

### B9e — le garde-fou est bon, et sa réserve est levée

**Livré le 22 septembre, `constat-mail-guard.md`.** Les quatre défauts du brief sont corrigés, les deux durcissements sont en place, et la liste des hôtes de production est établie par preuve, inodes à l'appui, plutôt que devinée. Le greffon lit `$_SERVER['HTTP_HOST']` et non `home_url()`, donc il ne dépend plus de ce que `lme-brands` réécrit. Il ne touche ni `From`, ni `Sender`, ni `Reply-To`, et la vérification n°5 de la recette reste valable.

**La réserve, levée le jour même par `d9a123e`.** Hors requête HTTP — WP-CLI, ou un cron en ligne de commande — il n'y a pas d'hôte à comparer, et exiger quand même une correspondance revenait à ne jamais reconnaître la production dans ce contexte. `wp_get_environment_type()` y décide désormais seul : la préproduction, où il vaut `staging`, continue de tout détourner ; la production cesse d'avaler ses propres envois. Le test qui figeait l'ancien comportement a été corrigé plutôt que supprimé.

**Et un fait rassurant, établi plutôt que supposé** : le rappel avant séjour de Vik passe aujourd'hui par la boucle HTTP de WP-Cron, qui porte un `HTTP_HOST`. **Aucun rappel de production n'aurait disparu.** La réserve restait réelle pour autant, SiteGround pouvant basculer sur un vrai cron en ligne de commande sans que le dépôt n'en sache rien.

**Une erreur de Cowork, pour mémoire.** La revue demandait aussi de journaliser tout abandon d'envoi. C'était déjà fait, inconditionnellement, par `catchall_not_configured` dans `guard.php` : la demande venait d'une lecture du seul chemin d'avertissement de marque, sans aller voir le chemin d'abandon. Deuxième fois en deux jours qu'une affirmation part sans vérification.

**Le « Push to Live » de SiteGround est interdit.** Le garde-fou décide d'après `WP_ENVIRONMENT_TYPE` : un déploiement de la préproduction vers la production par l'outil natif y ferait remonter la valeur `staging`, et tous les e-mails clients partiraient vers l'adresse fourre-tout. Le déploiement passe par `deployer-moteur.sh`, jamais par Site Tools. **L'adresse fourre-tout elle-même vit aussi en production**, où elle est inerte : elle s'hérite ainsi de chaque copie de préproduction, et le jour où la détection se tromperait, le courrier client atterrit dans une boîte relevée plutôt que d'être abandonné.

**Un effet de bord à connaître.** `deployer-moteur.sh` construit sa liste avec `git ls-files` sous `mu-plugins/` : ce greffon **partira au prochain déploiement**, sans décision séparée. C'est le revers de la liste dynamique qu'on a saluée en B9, et c'est voulu — en production, il est inerte.

**Livré le 22 septembre, `constat-mail-guard.md`.** Les quatre défauts du brief sont corrigés, les deux durcissements sont en place, et la liste des hôtes de production est établie par preuve, inodes à l'appui, plutôt que devinée. Le greffon lit `$_SERVER['HTTP_HOST']` et non `home_url()`, donc il ne dépend plus de ce que `lme-brands` réécrit. Il ne touche ni `From`, ni `Sender`, ni `Reply-To`, et la vérification n°5 de la recette reste valable.

**La réserve, et elle est bloquante.** `lme_mail_guard_is_production_context()` rend `false` dès que l'hôte est absent (`includes/core.php:48`), et c'est explicitement testé. Or **l'hôte est absent hors requête HTTP** : WP-CLI, et le cron s'il est lancé en ligne de commande. En production, un envoi dans ce contexte n'est donc pas reconnu comme production, l'adresse fourre-tout n'y étant pas définie il n'est pas non plus détourné, et `pre_wp_mail` l'abandonne. **Le message disparaît, et sans avertissement** : l'alerte du chapitre 2.b ne se déclenche que si l'hôte résout une marque, et un hôte nul n'en résout aucune.

**La victime désignée est le rappel avant séjour**, tâche planifiée de Vik qui tourne toutes les heures. Selon la façon dont SiteGround déclenche le cron — boucle HTTP, qui porte un `HTTP_HOST`, ou appel en ligne de commande, qui n'en porte pas — les rappels de production partent ou disparaissent. **À établir, pas à supposer.**

**Le correctif est court** : quand l'hôte est absent, décider sur `wp_get_environment_type()` seul. La préproduction, où il vaut `staging`, continue de tout détourner ; la production cesse d'avaler ses propres envois. Et dans tous les cas, **un abandon d'envoi se journalise**, avec ou sans marque résolue : la règle n°6 ne souffre pas d'exception pour les messages dont on ne sait pas à qui ils étaient destinés.

**Un effet de bord à connaître.** `deployer-moteur.sh` construit sa liste avec `git ls-files` sous `mu-plugins/` : ce greffon **partira donc au prochain déploiement**, sans décision séparée. C'est le revers de la liste dynamique qu'on a saluée en B9, et cela veut dire que la réserve ci-dessus atteindrait la production en même temps que le moteur.

### B9d — les treize crochets sont vérifiés, et la règle reste

**Fait le 22 septembre, `constat-signatures-crochets.md`.** Les treize `add_action` et `add_filter` de `lme-brands` sont passés en revue, forme reçue établie par lecture croisée de l'appel — Vik ou cœur WordPress — **et** du traitement du cœur, `do_action()`, `do_action_ref_array()`, `apply_filters()` et la troncature par `accepted_args`. **Aucun écart**, hors celui déjà corrigé par B9c. La garde de réservation, celle dont un écart aurait cassé toute création de réservation des deux marques, reçoit bien ses cinq arguments dans l'ordre déclaré, sur les deux branches de `saveorder()`.

**Deux limites à garder en tête.** L'inventaire vérifie la **forme** des arguments, pas le type de retour attendu : un filtre comme `upload_dir` doit rendre un tableau aux mêmes clés, et rien ici ne le prouve. C'est la recette qui le couvre. Et c'est un instantané : **tout crochet ajouté par la suite repasse par cette vérification**, à porter dans `mu-plugins/lme-brands/README.md` pour que la règle survive à la mémoire.

### Le script est corrigé — commit `6bb5d18`

Deux défauts levés dans la même passe. Sa vérification d'apparence suit désormais la redirection 301 de TranslatePress, `curl -L`, au lieu de lire un corps vide et de conclure à l'absence des jetons ; **un succès bruyamment nié est le symétrique de la règle « aucun échec silencieux »**, et toute vérification qui conclut d'une absence se relit de la même façon. Et le motif `tests/` est exclu du déploiement, par motif et non par énumération, ce qui lève la réserve posée sur B9.

### Réserve sur B9, à lever avant la production, pas avant la préproduction

**Pourquoi, et c'est la vraie leçon.** Cowork a d'abord écrit que « les 120 tests passaient parce qu'ils simulaient la forme fausse ». **C'est faux, et c'était écrit sans vérification** : les tests d'alors ne touchaient pas du tout `payment-brand.php`, ni sous sa forme fausse ni sous aucune forme. La faute est du même ordre que celle qu'elle décrivait.

La leçon réelle est plus large. **Une fonction accrochée à un crochet tiers a été livrée et revue sans un seul test.** Les tests de ce plugin couvrent `core.php`, des fonctions pures ; aucun rappel accroché à un crochet de Vik ou de WordPress n'est exercé, ce qui se défend puisqu'ils demandent WordPress. Ce qui ne se défend pas : **la forme des arguments que chaque rappel reçoit repose sur une lecture du code de Vik seul**, alors que c'est WordPress qui les livre et qu'il les transforme.

**Ce que B9d vérifie**, une fois, sans rien déployer : pour chaque crochet auquel `lme-brands` s'accroche — les cinq filtres d'URL, le filtrage de présentation, la garde de réservation, les deux crochets d'e-mail, le crochet de paiement — la signature réellement reçue, établie en lisant **à la fois** l'appel côté Vik et le traitement côté WordPress. Une ligne par crochet, forme attendue et forme reçue en regard, tout écart nommé.

**Le risque couvert.** Un rappel qui se trompe de forme ne dégrade pas, il lève une erreur fatale. Sur la garde de réservation, cela casserait toute création de réservation des deux marques, comme le paiement vient de l'être.

### Pour mémoire — la réserve sur B9, levée le 22 septembre

Le script tire sa liste de `git ls-files`, jamais d'une énumération figée : c'est la bonne décision, et elle respecte la leçon du 15 septembre, un fichier présent n'est pas un fichier suivi. Elle a une conséquence que le constat ne relève pas : **`mu-plugins/lme-brands/tests/test-core.php` est suivi par Git, donc il part sur le serveur.**

WordPress ne le charge pas, les mu-plugins n'étant chargés qu'à la racine de `mu-plugins/`. Mais le fichier reste **exécutable par une simple requête HTTP** sur `wp-content/mu-plugins/lme-brands/tests/test-core.php`, et il ne porte aucune garde `ABSPATH`, volontairement, puisqu'il doit tourner hors de WordPress. Il n'expose ni secret ni écriture, seulement des fonctions pures et, en cas d'échec, des chemins de fichiers.

**Correctif appliqué le 22 septembre**, commit `6bb5d18` : le motif `tests/` est exclu sous les racines déployées. Un motif, pas une énumération, donc l'argument de B9 contre les listes figées ne s'y applique pas.

### La parade de paiement est tranchée — décision du 21 septembre

**Retenues : C, D et E de `constat-reserve-paiement.md` §8**, dans un seul mu-plugin.

- **C, webhook `checkout.session.completed`**, chemin principal, de serveur à serveur, qui rejoue `notifypayment` par son `sid` et son `ts`.
- **D, réconciliation planifiée**, en filet, qui rattrape aussi l'existant. **Avec un correctif de critère** : elle retient la session par `metadata.booking_id`, **jamais** par l'option `stripe_order_<id>`. C'est la parade K de `constat-incident-1818.md` §5, et sans ce correctif D aurait échoué exactement comme `notifypayment` sur la 1818.
- **E, alerte**, pour qu'aucun rattrapage ni aucun échec ne passe en silence.

**A et B ne sont pas des parades, ce sont des mesures.** Le §8 les range dans la même liste, ce qui prête à confusion : A chiffre le dégât passé, elle ne conditionne pas le choix de la parade. La lecture Stripe reste à faire, pour elle-même.

**F tombe largement** avec la sortie de NitroPack, et le reste est couvert par C et D.

**G est écartée, et remplacée.** Allonger `minuteslock` aggrave le cas le plus fréquent : le verrou n'est pas conscient du client, donc la session abandonnée sur un téléphone bloque la nuit contre la personne elle-même passée sur son ordinateur, et Thomas a déjà reçu l'appel. Le constat en porte la trace chiffrée : **53 des 137 commandes en attente ont vu la page de paiement présentée au moins deux fois.** À la place, **traiter le relâchement plutôt que la durée** : le balayage de D annule la commande et libère la chambre quand la session Stripe est expirée, ou ouverte et non payée au-delà d'un délai. Même passe, critère inverse de celui du rattrapage. **Cette libération est une écriture sur les données de Vik : elle passe par le contrôleur de Vik, jamais par SQL**, le compte MySQL restant en lecture seule. `constat-reserve-paiement.md` §8 est à corriger sur ce point, tâche de Code au lancement de B6.

**Le piège qui a coûté le plus cher sur ce chantier**, à savoir avant toute recette d'e-mail : `from_email_force` et `from_name_force` de WP Mail SMTP écrasent en silence l'expéditeur composé par le code. Désactivés sur linstantcle.ch le 15 septembre.

### C. Infrastructure

| # | Quoi | État |
|---|---|---|
| C1 | DNS et certificat de `reservation.sexcaperoom.ch` | **fait** |
| C2 | Trois domaines sous un Workspace : MX Google, DKIM propre, SPF, DMARC `p=none` avec rapport, `reservations@` par groupe Google | **fait le 15 septembre** |
| C3 | `~/.my.cnf` en mode 600 | **fait** |
| C4 | Copie du greffon Stripe dans `.local/` | **fait**, VikStripe 2.2.4. Une seconde copie, `wp-vikstripe-new/`, déposée le 18 septembre : **c'est la même 2.2.4**, à une virgule et un garde-fou inerte près. **Il n'existe pas de version plus récente**, et la prémisse « la version récente est buguée » est fausse |
| C5 | Période d'observation DMARC, une à deux semaines, avant tout durcissement | **en cours**, arrêt franc |

Trois questions sont closes et ne se rouvrent pas : le changement de domaine principal est écarté, `maisonnette-enchantee.ch` reste principal et les deux autres sont des alias ; le relais transactionnel est déclassé, l'envoi passant par l'API Gmail depuis 2023 ; **les sous-domaines d'envoi par marque tombent avec le relais**, incompatibles avec Gmail, et ne se rouvrent qu'avec lui.

### D. Habillage du tunnel

| # | Quoi | Qui | État |
|---|---|---|---|
| D1 | Relever le système visuel de sexcaperoom.ch | Cowork | **fait** |
| D2 | Implémenter dans le thème enfant, sous condition d'hôte | Code, Sonnet 5 | **fait**, par le registre et non par une comparaison de chaîne |
| D3 | Comparer une page du site et une page du tunnel côte à côte | Cowork | ouvert, attend qu'une page du tunnel existe |
| D4 | Copie des e-mails par marque : corps, signature, images, liens | Cowork | ouvert ; tant qu'il manque, l'alerte `mail_brand_leak` reste allumée |
| D5 | Corriger `constat-habillage-tunnel.md` : la feuille `astra-child-style` n'existe pas sur le serveur, le handle réel est `astra-child-theme-css` | Code | **nouveau**, établi par B8 |

L'en-tête et le pied de page du tunnel restent à construire dans Elementor, par Ultimate Addons, geste Cowork.

### E. Surveillance

| # | Quoi | Attend |
|---|---|---|
| E1 | Table des valeurs attendues dans Airtable | A5 |
| E2 | Scénario Make « oracle tarifaire », 06:30, alerte Telegram | E1 |
| E3 | Scénario « détecteur de silence Stripe » | B8 |
| E4 | Scénario « veille de version Vik » : surveiller **l'empreinte des fichiers**, jamais le numéro de version | rien |
| E5 | Réservation factice hebdomadaire de bout en bout | B8 |
| E6 | Casser volontairement un tarif en préproduction et vérifier que l'alerte arrive | E2 |
| E7 | Contrôler les règles `rooms.php` des textes conditionnels contre le registre | E1 |

**Pourquoi E4 change de critère.** E4J republie des constructions différentes sous le même numéro 2.2.4, sans tenir son journal : deux d'entre elles ont été comparées ligne à ligne en B4b. Un scénario qui surveille le numéro de version ne verrait rien passer.

E6 n'est pas une formalité : une alarme jamais déclenchée n'est pas une alarme. **E5 suppose d'activer une chambre de test dans Vik le temps du test, puis de la désactiver, y compris quand le test échoue.**

### F. Contenu des e-mails de Vik — Thomas, dans l'administration

Trois défauts qui touchent des clients qui paient aujourd'hui, indépendants de toute histoire de marque. À consigner dans `journal-vik.md`.

| # | Quoi | Portée |
|---|---|---|
| F1 | Corriger `{condition:access_map_cinema}` en `{condition: access_map_cinema}` dans le gabarit de la tâche 7 | chambres 1 et 5, plan d'accès jamais envoyé |
| F2 | Donner une règle `rooms.php` aux textes conditionnels 70 à 73, inertes | chambres 8 et 9 |
| F3 | Créer les blocs de check-in manquants pour les chambres 7, 8, 9 et 10 | quatre chambres sans nom, ni horaire, ni code de porte, ni adresse dans leur rappel |

F1 est une minute de travail et le plus ancien des trois. **F3 doit précéder la première vente des chambres 8, 9 et 10**, qui rouvrent le 24 septembre.

**Arbitrage préalable à F3 :** Vik groupe ses textes conditionnels par bâtiment, `maisonnette` (2, 4, 6) et `cinema` (1, 5) ; le brief groupe par calendrier, villa Aparté (2, 4) et villa Entracte (1, 7, 10). Les deux ne se recouvrent pas et la chambre 7 tombe entre les deux. À trancher avant de dupliquer, pas après.

### G. Verrou d'hôte

Le brief `brief-verrou-hote-reservation.md` décrit ce chantier depuis le 12 septembre sans qu'il ait jamais eu de numéro. Il conditionne la phase 6.

| # | Quoi | Qui | État |
|---|---|---|---|
| G1 | Résorber la divergence du brief entre le dossier Communication et le dépôt | Thomas | **fait le 19 septembre** |
| G0 | Deux gestes de Thomas avant G2 : exclure `reservation.sexcaperoom.ch` du cache dynamique SiteGround **et** de NitroPack ; et confirmer que la redirection reste un 302 jusqu'à la fin de la recette | Thomas | **ouvert**, et personne ne le portait jusqu'ici |
| G2 | Implémenter la liste blanche et la redirection conditionnées à l'hôte | Code, Sonnet 5, moyen | **lançable après G0**. La liste tranchée ignore la page **6586**, fiche de L'Indécent |
| G2b | Produire le fragment `.htaccess` : `X-Robots-Tag` conditionné à l'hôte, et `robots.txt` propre à cet hôte | Code, Sonnet 5, faible | **nouveau**, le plan n'en portait aucune trace |
| G3 | Déployer G2 et G2b dans la même fenêtre que le moteur, et mener la **recette en six points** du verrou | Code puis Thomas | attend G2, G2b et B8 |

Pourquoi G0 existe. Sans l'exclusion de cache, une réponse mise en cache pour un hôte est servie pour l'autre, et un verrou PHP est sans effet sur une réponse qui ne passe pas par PHP. Et une 301 posée une heure de trop survit dans le navigateur du visiteur : ni une purge ni un correctif ne la rattrapent. C'est le seul geste du chantier dont l'erreur survit à sa correction.

**Vérification d'entrée de G2, conservée.** Le brief du dépôt doit contenir la décision du 302 pendant la recette et le filtre par `get_queried_object_id()`. S'il ne les porte pas, c'est l'ancienne copie et il faut s'arrêter.

**G3 porte aussi la recette en six points du verrou**, décrite dans `brief-verrou-hote-reservation.md` et jusqu'ici portée nulle part : la vérification n°8 de la recette du moteur n'en reprend qu'un, et elle se rejoue après purge des deux caches, sur Safari et sur iPhone. C'est elle qui conditionne le passage du 302 au 301.

**Motif de G3 :** tant que la liste blanche n'est pas en service, l'hôte de réservation sert tout le site L'Instant Clé, en `index, follow`. C'est une fuite de marque en production aujourd'hui, pas un risque futur.

### H. Signalement à l'éditeur

Ouvert par B4b. Le défaut de `stripe.php:365` est celui de E4J et non un artefact local : la ligne fautive de production est mot pour mot celle de l'archive de l'éditeur, établi au §4.6 de `constat-vikstripe-nouvelle-version.md`. Aucune construction publiée ne le corrige. Plan du signalement au §6 de `revue-constat-vikstripe-b4b.md`.

| # | Quoi | Qui | État |
|---|---|---|---|
| H1 | Rédiger le signalement : le défaut à la ligne avec son cas reproductible, l'empreinte `sha256` de la construction visée, les deux amplificateurs comme demandes d'évolution, la pratique de publication | Cowork | ouvert |
| H2 | L'envoyer et suivre la réponse | Thomas | attend H1 |

**Quatre éléments, et pas un de plus.** Le défaut d'arrondi ; l'empreinte du `stripe.php` de l'archive du 26 mai 2026, parce que deux constructions circulent sous le numéro 2.2.4 ; la case unique `stripe_order_<id>` et l'absence totale de webhook, **présentées comme des demandes d'évolution et non comme des bogues, sans quoi le ticket se ferme sur le correctif d'arrondi alors que la classe de panne « client débité, réservation non confirmée » reste entière** ; et la republication sans journal.

**Ne pas y mêler une incompatibilité de la nouvelle version** : elle n'est pas établie, et l'invoquer ferait fermer le ticket sur le mauvais sujet.

**Le signalement n'attend pas la réponse sur la restauration du 15 septembre.** Quelle qu'elle soit, elle ne change ni ce qu'on écrit à E4J ni ce qu'on décide de faire du greffon.

---

## 3. Le séquencement

```
fait ── A1 A2 A3 A4 ── B1 B2 B3 B4a B4 B4b B5 ── C1 C2 C3 C4 ── D1 D2 ── G1 ── NitroPack

maintenant ─┬─ redéploiement ─ recette 2 passes ─ B10 ─ D3 ───────┐ le test
            ├─ G2 ──── G3 ─────────────────┤   Code puis Thomas
            ├─ revue B5 ───────────────────┤   Cowork
            ├─ F1, F2, F3 ─────────────────┤   Thomas, urgent, hors chemin critique
            ├─ H1 ──── H2 ─────────────────┤   Cowork puis Thomas
            ├─ lecture Stripe ─ décision ─ B6  Thomas puis Code
            ├─ A6 ── A7 ── A8 ── A5 ───────┤── E1 ─ E2 ─ E7 ─ E6
            ├─ D4 ─────────────────────────┤
            └─ C5, observation DMARC ──────┴─ E3, E5 ── phase 6, bascule
```

**Ce qui doit être vrai avant la bascule :** les huit vérifications de la recette passées, B5 revu et déployé, G2 et G3 en place, D3 validé, D4 livré, E2 et E6 en service, et la réserve de paiement tranchée.

**Ce qui n'est sur le chemin critique de rien, et doit passer en premier quand même :** le chantier F et la lecture Stripe. Le premier touche des clients qui paient aujourd'hui, la seconde porte sur de l'argent déjà encaissé. C'est précisément parce qu'ils ne bloquent rien qu'ils risquent d'attendre indéfiniment.

---

## 4. Ce qui revient à Thomas, par ordre

**En tête, depuis le 25 septembre :**

- **Décider d'exercer la vérification 7.** Recommandation de Cowork : **oui, et le jour même**, parce que la fenêtre du rappel du 25 au 27 septembre contient des arrivées réelles **des deux marques** (1817 et 1434 au Boudoir, 1490 à L'Entracte), ce qui permet le critère décisif de B5 : deux réservations de marques différentes, une seule exécution, deux expéditeurs composés différents. Tout est détourné vers la fourre-tout par le garde-fou 1.1.0, y compris sans hôte HTTP. Effets : Vik écrit ses propres journaux de tâche dans la base de la préproduction, et les adresses de trois vrais clients apparaissent en `X-Original-To` dans la fourre-tout. Déclenchement par `wp cron event run vikbooking_cron_email_reminder_7`, sur la préproduction seulement, par Code sur décision de Thomas. **La fenêtre avance chaque jour** : l'occasion d'avoir les deux marques n'est pas garantie demain.

**Faits le 19 septembre**, retirés de cette liste : la sortie de la page 845 et des URL à `sid` de NitroPack, qui ne dispense pas de l'exclusion de l'hôte de réservation, G0, et G1.

1. **Recréer la préproduction depuis la production. Fait le 20 septembre**, sous `staging13`. À refaire dès qu'elle aura dérivé : une recette menée sur une copie périmée ne prouve rien de la production.
2. **Après chaque recréation, avant toute autre chose**, neutraliser tout ce que la copie ramène de la production et qui écrit vers l'extérieur. **C'est une étape obligatoire depuis l'incident du 24 septembre**, détaillée au §5 de `constat-integrations-sortantes.md` :
   - **Site Tools → File Manager** : **supprimer** le dossier `wp-content/plugins/vikchannelmanager`, ou le déplacer **hors de `public_html`**. Le désactiver ne suffit pas : Vik Booking détecte Vik Channel Manager **par ses fichiers**, jamais par son statut d'activation, et un greffon désactivé fait planter toute réservation (`constat-recette-vcm-inactif.md` §7 et §8). Ne pas le renommer sur place, ni le déplacer ailleurs sous `public_html`, où ses fichiers PHP resteraient exécutables par une simple requête. Une recréation de la préproduction le ramène de toute façon ;
   - **Plugins** : désactiver **MailPoet** ;
   - **Plugins** : désactiver aussi **WooCommerce Payments** et **PayPal** (`pymntpl-paypal-woocommerce`). La copie ramène WooPayments en **mode réel, sur le compte de production**, et aucune bascule en mode test n'apparaît dans ses réglages sur cette installation, constaté par Thomas le 24 septembre. La recette du moteur n'utilise pas WooCommerce : le désactiver ne coûte rien et ferme la question ;
   - **VikStripe** : clés de test ;
   - **TranslatePress → Settings → Automatic Translation** : désactiver, pour ne pas consommer de crédits DeepL sur la préproduction ;
   - vérifier que `WP_ENVIRONMENT_TYPE` vaut `staging`, que WP Mail SMTP garde « Do not send », et que `LME_MAIL_GUARD_CATCHALL_EMAIL` est posée ;
   - **et avant de réactiver quoi que ce soit, `wp cron event list`** : WP-Cron ne tourne pas sur la préproduction, tout ce qui est en retard partira d'un coup.
3. **Lancer `deployer-moteur.sh` sur la préproduction**, d'abord sans `--appliquer` pour lire ce qu'il changerait, puis avec.
4. **Mener la recette**, les huit vérifications du chantier B.
5. **Poser dans `wp-config.php` la clé Stripe restreinte en lecture et le secret de signature du webhook**, avant le déploiement de B6. La parade est tranchée depuis le 21 septembre, voir le chantier B ; ces deux valeurs sont la seule chose qu'elle attend de toi.
6. **Interroger Stripe sur les 137 sessions non soldées**, et relever leur `payment_status`. Lecture seule, aucun effet de bord. C'est la seule façon de savoir si un client a été débité sans réservation. Clé secrète Stripe, donc personne d'autre. Parades classées au §8 de `constat-reserve-paiement.md`, à décider ensuite.
7. **F1**, une minute.
8. **F3**, avant le 24 septembre, après avoir tranché l'arbitrage des groupements.
9. **F2**.
10. **Lire l'historique des restaurations de Site Tools pour le 15 septembre 2026** : heure et portée, fichiers ou bases. C'est ce qui tranche entre les deux lectures du §4 de `revue-constat-vikstripe-b4b.md`, et cela décide si le souvenir d'un dysfonctionnement du greffon était juste. Lecture seule, quelques clics.
11. **Dire d'où vient l'archive de `.local/wp-vikstripe-new`** : le téléversement du 15 septembre, ou une reprise chez vikwp.com le 18. Le constat le suppose sans l'établir.
12. **Relire et corriger les lignes de `journal-vik.md`** que Cowork et Code y portent désormais, et fournir les deux valeurs qu'eux seuls ne peuvent pas trouver : la portée et l'heure de la restauration du 15 septembre, et le motif de l'écriture du 11 décembre 2025 sur `stripe.php` s'il s'en souvient.
13. **Charger la clé SSH dans l'agent** à chaque redémarrage du Mac, `ssh-add --apple-use-keychain ~/.ssh/icl_ed25519`, et **le porter dans `handoff-acces-mysql.md`**, qui décrit la clé sans mentionner qu'elle porte une phrase de passe. Sans l'agent, les journaux et la base sont hors d'atteinte, et B4b l'a appris à ses dépens.
14. **Trancher** : le périmètre de A6, l'identité d'envoi des repas et des bons cadeaux, et **le critère de recette n°8 de la phase 4**, le libellé de relevé bancaire. Le point d'accroche des rappels sort de cette liste : il est tranché et posé par B5. Ce dernier n'est pas qu'une question comptable : c'est ce que le client lit sur son relevé de carte, donc une question de discrétion pour une expérience Sexcape Room.
15. **Déployer B3** quand la recette d'en-têtes est concluante.

---

## 5. Prompts de lancement

À coller tels quels dans Claude Code, depuis `~/Documents/icl-dev`. Les prompts des tâches déjà exécutées ne sont pas reproduits ici : ils vivent dans l'historique Git et dans les constats qu'ils ont produits. Les prompts de B4, B4b et B5 en sont sortis à la version 2.1.

### B9 — script de déploiement, de vérification et de retour arrière. Sonnet 5, effort moyen

> Lis `CLAUDE.md`, `docs/briefs/constat-deploiement-moteur.md` en entier et le chantier B de `docs/briefs/plan-de-marche.md`. Le constat décrit un déploiement à la main, à faire deux fois, en préproduction puis en production. Rends-le exécutable : **un seul script, lancé sur les deux environnements**, pour que la symétrie soit une propriété du geste et non une intention.
>
> **Il simule par défaut** : sans option explicite, il imprime ce qu'il changerait et n'écrit rien. Il **refuse de continuer** plutôt que de deviner, sur au moins ces préalables : `wp_get_environment_type()` conforme à l'environnement visé, empreintes attendues de ce qui est déjà en place, et en préproduction VikStripe en clés de test, vérifié **par le seul préfixe** `sk_test_` contre `sk_live_`, jamais par la valeur, et sans journaliser ce préfixe ailleurs que sur la sortie standard.
>
> **`functions.php` se fusionne, ne se remplace pas** : ajouter la ligne `require_once` si elle est absente, jamais la dupliquer, et s'arrêter si le fichier cible ne ressemble pas au thème réel, contrôle de taille du chapitre 6 à l'appui. **`style.css` du dépôt ne se déploie jamais.**
>
> Il prend sa propre sauvegarde avant d'écrire, porte un `--rollback` qui la remet, et il est idempotent : deux exécutions de suite donnent le même état et la seconde ne signale rien. Vérifie chaque morceau après l'avoir posé, selon le chapitre 6, et sors en erreur au premier contrôle rouge.
>
> **Tu peux le lancer sur la préproduction. Tu ne le lances jamais en production** : c'est un geste de Thomas. Ne lis aucune clé, n'écris rien en base. Écris `docs/briefs/constat-script-deploiement.md` : ce que le script fait, ce qu'il refuse, et ce qu'il ne couvre pas.

### B6 — parade de la réserve de paiement. Opus 5, effort élevé

**Modèle relevé à la version 2.3.** La tâche touche de l'argent, exige l'idempotence, et son code s'exécute pour les deux marques, `constat-deploiement-moteur.md` §8 l'établit. Une erreur y confirme deux fois une réservation ou en confirme une qui n'a pas été payée.

> **À ne lancer qu'une fois la parade choisie par Thomas.** Lis `CLAUDE.md`, `docs/briefs/constat-reserve-paiement.md` en entier dont le §8, `docs/briefs/constat-incident-1818.md` §5, et `docs/briefs/constat-deploiement-moteur.md` §8. Implémente la parade retenue, dans un mu-plugin, **jamais dans le greffon**.
>
> Si c'est un webhook, il rejoue `notifypayment` par son `sid` et son `ts` plutôt que d'en dupliquer les dix-sept effets, le contrôleur étant idempotent ; il vérifie la signature Stripe, refuse tout appel non signé, et reste inerte si le secret de signature n'est pas défini. Si c'est une réconciliation planifiée, elle retient la session par `metadata.booking_id` et **jamais** par l'option `stripe_order_<id>`, qui est précisément ce qui a échoué sur la 1818, et elle rattrape aussi l'existant.
>
> **La parade est tranchée** : C, D et E du §8, avec le critère de K (`metadata.booking_id`, jamais `stripe_order_<id>`). Ajoute au balayage le relâchement du verrou : annuler la commande et libérer la chambre quand la session Stripe est expirée, ou ouverte et non payée au-delà d'un délai. **Cette libération est une écriture sur les données de Vik** : elle passe par le contrôleur de Vik, jamais par SQL. Corrige au passage le §8 du constat : A et B sont des mesures et non des parades, et G est écartée, allonger `minuteslock` aggravant le blocage d'un client par sa propre session abandonnée.
>
> **Le code s'exécutera pour les deux marques** : dis explicitement, dans ton constat, ce qu'il change au parcours de linstantcle.ch. Journalise et alerte chaque rattrapage : un paiement récupéré en silence est un incident qu'on n'apprend jamais. **Aucune clé n'est lue depuis le dépôt ni écrite nulle part** : elles vivent dans `wp-config.php`, posées par Thomas. Ne déploie rien.

### B7 — identité d'envoi des repas et des bons cadeaux. Sonnet 5, effort moyen

> **À ne lancer qu'une fois l'identité décidée par Thomas.** Lis `CLAUDE.md`, `docs/briefs/constat-phase-3-emails.md` et `mu-plugins/lme-brands/config/brands.php`. Ces deux flux d'envoi ne sont définis nulle part, ni dans le registre ni dans le brief e-mail. Établis d'abord par quel chemin ils partent aujourd'hui, preuves et lignes citées, puis porte l'identité décidée dans le registre et applique-la par le même mécanisme que la phase 3. Ne déploie rien.

### A6 — fiche de saisie tarifaire, lecture seule. Sonnet 5, effort moyen

> **À ne lancer qu'une fois le périmètre confirmé par Thomas**, voir le chantier A : `Fall vacation 2026 (rooms)` existe déjà, `Fall vacation 2027` n'existe ni en villas ni en rooms, et `Carnival vacation 2027` n'a pas de doublet.
>
> Lis `CLAUDE.md`, `docs/briefs/constat-phase-0.md` §Q6, `docs/briefs/revue-tarifs.md` et `docs/briefs/convention-tarifs-annuelle.md`. **Tu n'écris rien en base : le compte MySQL est en lecture seule et c'est voulu.** Ton livrable est une fiche que Thomas suivra à l'écran dans Vik.
>
> Produis `docs/briefs/constat-fiche-saisie-tarifs.md` : pour chaque ligne à créer ou modifier, **les valeurs telles qu'elles doivent être saisies dans l'écran de Vik**, pas telles qu'elles sont stockées. C'est le point difficile : les dates de saison sont stockées en secondes depuis le 1er janvier, les jours de semaine dans deux formats selon la table, et les délimiteurs de `idrooms` diffèrent entre saisons et restrictions. Donne ce que Thomas voit et tape, avec en regard la valeur attendue en base pour que A8 puisse vérifier. Signale tout écart entre ce que le texte annonce et ce que la base contient.

### A8 — vérification après saisie, lecture seule. Sonnet 5, effort moyen

> Lis `CLAUDE.md`, `docs/briefs/constat-fiche-saisie-tarifs.md` et `docs/briefs/journal-vik.md`. Thomas a saisi les lignes dans Vik. Vérifie par requête, en lecture seule, que chacune existe avec les valeurs attendues, que les saisons rejoignent les bonnes chambres par la jointure sur le jeton `-N-`, que les restrictions rejoignent la chambre 7, et qu'aucune ne dépasse un an de portée. Ajoute les preuves à `constat-phase-0.md` §Q6 sans réécrire les lignes closes. Si un écart apparaît, décris-le et arrête-toi : la correction est un geste de Thomas.

### G2 — verrou d'hôte. Sonnet 5, **effort élevé**

**Effort relevé à la 2.14.** Pas pour la difficulté, pour le rayon d'action : une redirection mal conditionnée une heure de trop survit dans le navigateur des visiteurs, et ni une purge ni un correctif ne la rattrapent.

> **À ne lancer qu'après G1**, c'est-à-dire une fois `docs/briefs/brief-verrou-hote-reservation.md` remplacé par la version à jour. Vérifie d'abord que le fichier que tu lis contient bien la décision du 302 pendant la recette et le filtre par `get_queried_object_id()` ; s'il n'en parle pas, arrête-toi, tu lis la mauvaise copie.
>
> Lis `CLAUDE.md`, ce brief en entier et `docs/briefs/constat-perimetre-tunnel.md`. Implémente dans `mu-plugins/lme-brands/` la liste blanche et la redirection vers `https://sexcaperoom.ch/` de tout ce qui n'y figure pas, conditionnées à l'hôte et inertes sur tout autre hôte. Ne déploie rien.

---

## 6. Trois choses que ce plan ne doit pas laisser oublier

**Un client peut être débité sans réservation confirmée.** `constat-reserve-paiement.md` l'établit : le seul code qui confirme une commande s'exécute dans la requête de retour du navigateur, et rien ne rattrape ce retour s'il n'a pas lieu. 137 commandes en attente portent une session Stripe jamais soldée, pour 42 297.85 CHF cumulés depuis février 2024. La plupart sont sans doute des paniers abandonnés, indiscernables depuis la base. **Seule une lecture chez Stripe tranchera.**

**Le rappel avant séjour fuit la marque, aujourd'hui, en production.** Il part de `L'Instant Clé <info@maisonnette-enchantee.ch>` pour toutes les chambres, Sexcape Room comprise, avec logo et titre L'Instant Clé inconditionnels. Un client du Boudoir du Désir le reçoit déjà. B5 corrige l'expéditeur et **n'est pas déployée**, F corrige le contenu : les deux sont nécessaires.

**Un fichier de greffon tiers a été réécrit seul, le 11 décembre 2025.** `stripe.php` porte cette date quand les 359 autres fichiers de VikStripe portent celle de l'installation du 23 octobre 2025. Le fichier en est ressorti fonctionnellement identique à celui de l'éditeur, à une virgule près, donc rien n'est cassé aujourd'hui. Mais personne ne sait ce que cette écriture a changé, la construction d'origine n'existe plus nulle part sur le compte, et la prochaine mise à jour de VikStripe l'effacera sans prévenir.

---

## 7. Le rythme

**Le modèle de Code, décision du 25 septembre.** Opus 5.5 effort moyen par défaut, sur la recommandation d'Anthropic du 22 septembre : Opus 5.5 pour écrire du code, Sonnet ou Haiku pour les recherches, effort bas plutôt qu'un modèle plus petit pour le mécanique. Le prix par jeton est double de celui de Sonnet 5, mais un essai raté coûte plus que l'écart. Les en-têtes des prompts écrits avant cette date indiquent encore Sonnet 5 : **lire Opus 5.5 au même effort.** À mesurer par `/usage` sur une tâche réelle plutôt qu'à croire.

**Consigne à porter dans chaque prompt, décision du 24 septembre.** *Établis les prémisses que tu utilises contre leur source ; ne les reprends pas d'un constat ni d'un brief sans les revérifier.* Le seul échec sérieux de ce chantier, la phase 4 qui cassait tout paiement, vient d'une prémisse fausse reprise d'un constat, pas d'un défaut de modèle — et Cowork a commis la même faute deux fois en deux jours, en Opus. **Le modèle n'est pas la variable ; la vérification des prémisses l'est.** C'est ce que B9c et B9d ont démontré en faisant l'inverse.

Une phase, une session, un commit, une revue. Ne jamais enchaîner deux phases sans revue : c'est en enchaînant qu'on livre une phase 2 bâtie sur une phase 1 fausse.

Entre deux phases, Thomas avance ses gestes. Ils sont courts, mais chacun débloque une recette : les faire tard, c'est découvrir en fin de chantier qu'aucune phase n'est vérifiable.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-09 | Création. Cinq chantiers, séquencement, prompts de lancement. |
| 2.0 | 2026-09-17 | Remise à l'état réel : A1 à A4, B1 à B3, B4a, C1 à C4, D1 et D2 faits. Ajout de B5, B6, B7, C5, D4, E7, du chantier F et du chantier G. Prompts des tâches faites retirés, prompts des tâches ouvertes écrits ou révisés. Ajout du chapitre 4, ce qui revient à Thomas, et du chapitre 6, les deux choses à ne pas oublier. |
| 2.20 | 2026-09-25 | Parcours complet sans Vik Channel Manager. La marque d'expéditeur des confirmations est composée juste et reçue telle quelle : vérification 5 établie de bout en bout. Une conclusion inverse sur le relais Gmail, tirée des copies administrateur, est corrigée ; C6 retiré. B9i fermé : 6c était un faux positif par construction, l'annulation en libre-service est fermée par réglage, la vérification 7 constate sans déclencher. |
| 2.19 | 2026-09-25 | Vik Channel Manager se **retire** de la préproduction au lieu de se désactiver : Vik Booking le détecte par ses fichiers, et désactivé il fait planter toute réservation. Opus 5.5 effort moyen devient le modèle par défaut de Code. |
| 2.18 | 2026-09-24 | Recréation de la préproduction : WooCommerce Payments et PayPal **désactivés** plutôt que basculés en test ; régénération des clés des greffons maison et coupure de la traduction automatique **écartées**, motifs au chantier B. — **Reconstruction** d'une révision perdue au commit `bec2ac9`, restauré en 2.17 par `6156d9f`. **Incident** : Vik Channel Manager actif sur la préproduction a poussé chaque réservation d'essai vers Airbnb, Booking.com et Expedia ; exposition réelle établie par les calendriers partagés, L'Entracte et L'Aparté fermés ; réparé le jour même depuis la production. B9h, B11 et B9j livrés et revus. Première reprise : le paiement de la phase 4 aboutit. Revue de B5. WooCommerce Payments trouvé en mode réel sur la préproduction. Chapitre 4 : recréation de la préproduction réécrite. Ajout de B9i, B11b. |
| 2.17 | 2026-09-24 | Première recette complète en deux passes. La vérification 2 cherche le nom dans toute la page, menu compris : son KO est garanti par construction, et seule `roomdetails` est établie pour de vrai. B11 corrige aussi la mesure. La garde rend un signal ambigu en passe L'Instant Clé, le sens qui protège la marque qui vend : ajout de B9h, avant B11. |
| 2.16 | 2026-09-24 | B11 redevient bloquant pour B10. Vérification complète au navigateur, avec et sans le paramètre : un seul composant Vik par page, aucun formulaire superflu dans le DOM, mais le formulaire **visible** bascule sur la chambre étrangère. Sans B11, le déploiement transformerait un parcours qui aboutit aujourd'hui en impasse à `403`. La 2.15 concluait l'inverse sur la foi du seul bloc descriptif masqué. |
| 2.15 | 2026-09-24 | B9g livré : les deux passes atteignent Stripe Checkout, les vérifications 5 à 7 deviennent possibles. B11 ramené à sa portée réelle, vérifiée au navigateur : le composant Vik charge la chambre étrangère dans un conteneur masqué, seulement par URL fabriquée, et la garde refuse la réservation ; non bloquant pour B10. En-tête de la 2.14 corrigé, qui annonçait encore le réglage `skipbtn` retiré dans son propre journal. |
| 2.14 | 2026-09-24 | Recette automatisée livrée et revue : la garde de réservation fonctionne dans les deux sens. Ajout de B11, un défaut réel de `room-filter.php` qui rend la fiche d'une chambre étrangère. Le blocage des vérifications 5 à 7 attribué d'abord au réglage `skipbtn`, **puis corrigé le jour même** : `skipbtn` est le paramètre « Auto-redirect » de VikStripe, aux options inversées, et le blocage tient au script qui ne suit pas le bouton PAY NOW. Geste retiré du chapitre 4, ajout de B9g. Règle de nettoyage corrigée : une commande `standby` n'est pas annulable par le contrôleur de Vik. G2 passe en effort élevé. Consigne de vérification des prémisses portée au chapitre 7. |
| 2.13 | 2026-09-23 | Vingt-trois prérequis dormants remontés des briefs, dont huit bloquants : `prerequis-dormants-2026-09-23.md`. Ajout de G2b, le fragment `.htaccess`, dont le plan ne portait aucune trace. G2 signale la page 6586 absente de la liste tranchée, G3 reprend la recette en six points du verrou. Deux trous nommés dans la recette. Règle posée : un prérequis se remonte au plan dans la même passe. |
| 2.12 | 2026-09-22 | Réserve de B9e levée : hors requête HTTP, `wp_get_environment_type()` décide seul, et il est établi que le rappel de Vik passe aujourd'hui par la boucle HTTP de WP-Cron, donc aucun rappel n'avait disparu. Interdiction du « Push to Live » de SiteGround posée, qui emporterait l'environnement et l'adresse fourre-tout en production. Correction d'une seconde affirmation non vérifiée de Cowork. |
| 2.11 | 2026-09-22 | B9e livré et revu : quatre défauts corrigés, deux durcissements en place, liste d'hôtes établie par preuve. **Une réserve bloquante** : hors requête HTTP, WP-CLI et cron en ligne de commande, la production n'est pas reconnue et l'envoi est abandonné sans avertissement, le rappel avant séjour en tête. Effet de bord noté : la liste dynamique du script emporte ce greffon au prochain déploiement, sans décision séparée. |
| 2.10 | 2026-09-22 | B9d livré et revu : treize crochets, aucun écart hors celui déjà corrigé, et la règle passe au README pour survivre à la mémoire. Le script est corrigé, redirection TranslatePress suivie et motif `tests/` exclu, ce qui lève la réserve sur B9. Plus rien ne retient le redéploiement et la recette. |
| 2.9 | 2026-09-22 | B9b et B9c livrés et revus. La phase 4 n'avait jamais fonctionné : `payment-brand.php` levait une erreur fatale sur toute tentative de paiement, les deux marques, les deux environnements, par une prémisse fausse de `constat-phase-0.md` §Q5 corrigée à sa source. Les deux correctifs d'hôte visent désormais l'hôte réel. Ajout de B9d, la vérification des signatures de crochets, avant B10. La 2.8, qui n'a pas survécu, affirmait à tort que les 120 tests validaient la forme fausse : ils ne couvraient pas cette fonction du tout. |
| 2.7 | 2026-09-21 | Première recette menée sur `staging13`. La recette passe en deux passes, une par marque, la vérification n°4 n'étant pas vérifiable autrement. Ajout de B9b : la réécriture d'URL de la préproduction pointe vers la production, et une erreur fatale coupe la page de paiement. |
| 2.6 | 2026-09-21 | La recette du moteur gagne un titre de section : ses huit vérifications existaient depuis la 2.2 mais sans en-tête, dans le corps du chantier B, et les renvois parlaient d'un « chapitre B8 » qui n'a jamais existé. Renvois corrigés. |
| 2.5 | 2026-09-21 | B9 livré, revu et acté, avec une réserve : `tests/test-core.php` est suivi par Git donc déployé, et exécutable par HTTP ; exclusion `tests/` obligatoire avant B10. La parade de paiement est tranchée, C, D et E avec le critère de K, ce qui débloque B6 ; G est écartée et remplacée par le relâchement du verrou. Chapitre 4 remis à jour. |
| 2.4 | 2026-09-20 | Le déploiement passe par un script unique lancé sur les deux environnements, tâche B9 ; l'ancien B9 devient B10. Rafraîchissement de `staging13` depuis la production posé en tête du chapitre 4, avec les trois gestes qui ne survivent pas à la copie. |
| 2.3 | 2026-09-20 | B8 livré, revu et acté, avec sa trouvaille : le thème du dépôt n'était pas une copie du serveur et aurait écrasé 7 390 octets de production. Ajout de B9, le déploiement en production comme décision distincte, et de D5, la correction du constat D2. B6 relevé en Opus 5 effort élevé et son prompt durci. Chapitre 4 : le déploiement en préproduction détaillé, et le choix de la parade ajouté, découplé de la lecture Stripe. |
| 2.2 | 2026-09-20 | Ajout de B8, la recette du moteur, absente du plan depuis l'origine : le moteur est écrit et rien n'est déployé, constaté sur l'hôte de réservation. Ajout de G3. G1 et le geste NitroPack passés à « fait », retirés du chapitre 4, qui gagne les deux gestes de déploiement. E3 et E5 dépendent désormais de B8. `journal-vik.md` s'ouvre à Code. |
| 2.1 | 2026-09-19 | Révision après B4b et la revue de son constat. B4, B4b et B5 passés à leur état réel, B5 signalée livrée sans revue. Prémisse de C4 corrigée : il n'existe pas de version plus récente de VikStripe. E4 surveille désormais l'empreinte des fichiers et non le numéro de version. Chantier H ouvert pour le signalement à E4J. Chapitre 4 réordonné, le geste NitroPack en tête. Troisième point au chapitre 6. Bannière de transfert retirée. |
