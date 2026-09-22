# Plan de marche — réservation Sexcape Room

**Version 2.7, 21 septembre 2026.** La version 2 du 17 septembre remplaçait la version 1 du 9, devenue fausse sur la moitié de ses lignes. La 2.7 pose la recette en deux passes, une par marque, et acte les deux correctifs remontés par la première recette.

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
| **Modèle** | Sonnet 5 par défaut, Opus 5 sur les tâches marquées | Opus 5 |

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
| B4 | Paiement, retour, page de confirmation | Sonnet 5, moyen | **fait** le 17 septembre, commit `6a82070`, revue faite. Critère de recette n°8 laissé ouvert, décision de Thomas |
| B4b | Examiner la nouvelle version de VikStripe avant d'écrire à E4J | Opus 5, élevé | **fait** le 18 septembre, commit `6fe7ef0`. C'est la même 2.2.4, le défaut est intact, aucun webhook. Revue du 19 : conclusion tenue, mais « elle n'a jamais tourné » n'est pas démontré |
| B5 | Marquer les rappels avant séjour, par `vikbooking_before_send_mail` | Opus 5, élevé | **livrée** le 17 septembre, commit `c22f5b7`, `constat-b5-rappels.md`, rien de déployé. **Revue Cowork non faite, et elle passe avant B6** |
| B6 | Parade de la réserve de paiement | **Opus 5, élevé** | **lançable** : la parade est tranchée le 21 septembre, voir ci-dessous. Passe après la revue de B5 |
| B7 | Identité d'envoi des repas et des bons cadeaux | Sonnet 5, moyen | ouvert, attend une décision de Thomas |
| B8 | Levier de préproduction et inventaire de déploiement | Sonnet 5, moyen | **fait** le 20 septembre, commit `fade08b`, `constat-deploiement-moteur.md`, revue faite. 120 tests au vert |
| B9 | Script de déploiement, de vérification et de retour arrière | Sonnet 5, moyen | **fait** le 21 septembre, commits `289339e` et `125e65b`, `constat-script-deploiement.md`, revue faite. Une réserve avant la production, ci-dessous |
| B9b | Deux correctifs après la première recette : la réécriture d'URL en préproduction, et l'erreur fatale de la page de paiement | Sonnet 5, moyen | **nouveau, et bloquant pour la recette**, `brief-correctif-levier-et-fatal-paiement.md` |
| B10 | Déployer en production, après la recette | Thomas, décision séparée | ouvert. **Prérequis : l'exclusion `tests/` du script**, voir ci-dessous |

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

### Réserve sur B9, à lever avant la production, pas avant la préproduction

Le script tire sa liste de `git ls-files`, jamais d'une énumération figée : c'est la bonne décision, et elle respecte la leçon du 15 septembre, un fichier présent n'est pas un fichier suivi. Elle a une conséquence que le constat ne relève pas : **`mu-plugins/lme-brands/tests/test-core.php` est suivi par Git, donc il part sur le serveur.**

WordPress ne le charge pas, les mu-plugins n'étant chargés qu'à la racine de `mu-plugins/`. Mais le fichier reste **exécutable par une simple requête HTTP** sur `wp-content/mu-plugins/lme-brands/tests/test-core.php`, et il ne porte aucune garde `ABSPATH`, volontairement, puisqu'il doit tourner hors de WordPress. Il n'expose ni secret ni écriture, seulement des fonctions pures et, en cas d'échec, des chemins de fichiers.

**Correctif : exclure le motif `tests/` sous les racines déployées.** C'est un motif et non une énumération, donc il ne grossit pas avec le temps, et l'argument de B9 contre les listes figées ne s'y applique pas. Sans urgence pour la préproduction, **obligatoire avant B10**.

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
| G2 | Implémenter la liste blanche et la redirection conditionnées à l'hôte | Code, Sonnet 5, moyen | **lançable** |
| G3 | Déployer G2 dans la même fenêtre que le moteur | Code puis Thomas | attend G2 et B8 |

**Vérification d'entrée de G2, conservée.** Le brief du dépôt doit contenir la décision du 302 pendant la recette et le filtre par `get_queried_object_id()`. S'il ne les porte pas, c'est l'ancienne copie et il faut s'arrêter.

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

maintenant ─┬─ déploiement staging ─ recette ─ exclusion tests/ ─ B10 ─ D3 ─┐ le test
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

**Faits le 19 septembre**, retirés de cette liste : la sortie de la page 845 et des URL à `sid` de NitroPack, et G1.

1. **Recréer la préproduction depuis la production. Fait le 20 septembre**, sous `staging13`. À refaire dès qu'elle aura dérivé : une recette menée sur une copie périmée ne prouve rien de la production.
2. **Après chaque recréation**, trois gestes qui ne survivent pas à la copie, dans cet ordre : vérifier que `WP_ENVIRONMENT_TYPE` vaut bien `staging` ; **basculer VikStripe sur les clés de test**, la copie ramenant les clés de production avec la base ; reposer la ligne `LME_BRANDS_HOST_OVERRIDE` du chapitre 5 du constat.
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

### G2 — verrou d'hôte. Sonnet 5, effort moyen

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

Une phase, une session, un commit, une revue. Ne jamais enchaîner deux phases sans revue : c'est en enchaînant qu'on livre une phase 2 bâtie sur une phase 1 fausse.

Entre deux phases, Thomas avance ses gestes. Ils sont courts, mais chacun débloque une recette : les faire tard, c'est découvrir en fin de chantier qu'aucune phase n'est vérifiable.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-09 | Création. Cinq chantiers, séquencement, prompts de lancement. |
| 2.0 | 2026-09-17 | Remise à l'état réel : A1 à A4, B1 à B3, B4a, C1 à C4, D1 et D2 faits. Ajout de B5, B6, B7, C5, D4, E7, du chantier F et du chantier G. Prompts des tâches faites retirés, prompts des tâches ouvertes écrits ou révisés. Ajout du chapitre 4, ce qui revient à Thomas, et du chapitre 6, les deux choses à ne pas oublier. |
| 2.7 | 2026-09-21 | Première recette menée sur `staging13`. La recette passe en deux passes, une par marque, la vérification n°4 n'étant pas vérifiable autrement. Ajout de B9b : la réécriture d'URL de la préproduction pointe vers la production, et une erreur fatale coupe la page de paiement. |
| 2.6 | 2026-09-21 | La recette du moteur gagne un titre de section : ses huit vérifications existaient depuis la 2.2 mais sans en-tête, dans le corps du chantier B, et les renvois parlaient d'un « chapitre B8 » qui n'a jamais existé. Renvois corrigés. |
| 2.5 | 2026-09-21 | B9 livré, revu et acté, avec une réserve : `tests/test-core.php` est suivi par Git donc déployé, et exécutable par HTTP ; exclusion `tests/` obligatoire avant B10. La parade de paiement est tranchée, C, D et E avec le critère de K, ce qui débloque B6 ; G est écartée et remplacée par le relâchement du verrou. Chapitre 4 remis à jour. |
| 2.4 | 2026-09-20 | Le déploiement passe par un script unique lancé sur les deux environnements, tâche B9 ; l'ancien B9 devient B10. Rafraîchissement de `staging13` depuis la production posé en tête du chapitre 4, avec les trois gestes qui ne survivent pas à la copie. |
| 2.3 | 2026-09-20 | B8 livré, revu et acté, avec sa trouvaille : le thème du dépôt n'était pas une copie du serveur et aurait écrasé 7 390 octets de production. Ajout de B9, le déploiement en production comme décision distincte, et de D5, la correction du constat D2. B6 relevé en Opus 5 effort élevé et son prompt durci. Chapitre 4 : le déploiement en préproduction détaillé, et le choix de la parade ajouté, découplé de la lecture Stripe. |
| 2.2 | 2026-09-20 | Ajout de B8, la recette du moteur, absente du plan depuis l'origine : le moteur est écrit et rien n'est déployé, constaté sur l'hôte de réservation. Ajout de G3. G1 et le geste NitroPack passés à « fait », retirés du chapitre 4, qui gagne les deux gestes de déploiement. E3 et E5 dépendent désormais de B8. `journal-vik.md` s'ouvre à Code. |
| 2.1 | 2026-09-19 | Révision après B4b et la revue de son constat. B4, B4b et B5 passés à leur état réel, B5 signalée livrée sans revue. Prémisse de C4 corrigée : il n'existe pas de version plus récente de VikStripe. E4 surveille désormais l'empreinte des fichiers et non le numéro de version. Chantier H ouvert pour le signalement à E4J. Chapitre 4 réordonné, le geste NitroPack en tête. Troisième point au chapitre 6. Bannière de transfert retirée. |
