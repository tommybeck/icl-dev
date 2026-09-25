# Constat — recette du 24 septembre après la désactivation de Vik Channel Manager

24 septembre 2026. Exécution de `recetter-moteur.sh --appliquer` contre
`staging13.linstantcle.ch`, à l'intention de Cowork pour analyse et pour le
plan de marche. C'est la première recette depuis que Vik Channel Manager a été
désactivé sur la préproduction, à la suite de
`incident-preproduction-vers-plateformes-2026-09-24.md`.

**Tout ce qui suit vient d'une exécution réelle et de lectures en lecture seule**
(`debug.log` de la préproduction, `wp db query` en `SELECT` à colonnes
explicites). Rien n'a été corrigé, rien n'a été déployé, rien n'a été écrit en
base. Les `sid` sont omis, pour la même raison que dans
`constat-reprise-1830-1831.md`. L'adresse du client de la réservation 1833 est
omise aussi : c'est une vraie adresse, qui n'a rien à faire dans le dépôt.

---

## 1. Commande et contrôles de départ

```bash
./recetter-moteur.sh --hote staging13.linstantcle.ch --appliquer
```

Lancée à 15 h 54 UTC. Tous les contrôles de départ sont verts :

- l'hôte n'est pas un hôte de production connu de `lme-mail-guard` ;
- `wp_get_environment_type()` vaut `staging` ;
- Vik Channel Manager est `inactive` ;
- VikStripe est publié en clés de test (`sk_test_`), une passerelle.

Le levier valait `reservation.sexcaperoom.ch` au départ et a été restauré à
cette valeur à la fin.

## 2. Résultat

| # | Sexcape Room | L'Instant Clé |
|---|---|---|
| 1a, 1b | OK | OK |
| 2 | OK, aucune chambre étrangère proposée | OK |
| 3a | OK, chambre étrangère refusée en `403` (arrivée le 24 novembre) | OK |
| 3b | non concluant, la chambre désactivée n'apparaît pas dans les résultats natifs | non concluant |
| 4 | OK, jetons `--srlm-` présents | OK, aucune fuite |
| 5-6 | **KO** : `code HTTP inattendu à la soumission finale : 500` | **KO**, même erreur |
| 8 | KO, attendu tant que G2 n'existe pas | |

Le script rapporte pour les deux passes « la réservation d'essai n'a pas pu être
créée ». **C'est faux**, voir le chapitre 4. Corrigé le 25 septembre, voir le
chapitre 9.

Le code de sortie n'a pas été relevé : la commande passait par `tee` sous zsh,
où `PIPESTATUS` n'existe pas. Le défaut « sortie en `0` malgré des
vérifications au rouge » de B9i reste donc ni confirmé ni infirmé par cette
exécution.

## 3. La cause des 500

`wp-content/debug.log` de la préproduction contient la même erreur fatale, à
chaque soumission finale :

```
PHP Fatal error:  Uncaught Error: Undefined constant "VIKCHANNELMANAGER_LIBRARIES"
  in wp-content/plugins/vikchannelmanager/admin/helpers/src/request/availability.php:445
#0 availability.php(360): VCMRequestAvailability->checkLanguageStatus()
#1 availability.php(102): VCMRequestAvailability->storeTemporaryBusyRecords(Array)
#2 vikchannelmanager/site/helpers/synch.vikbooking.php(1070): VCMRequestAvailability->fetchTemporaryBusyRecords(...)
#3 synch.vikbooking.php(265): SynchVikBooking->getOrderDetails()
#4 vikbooking/site/helpers/vcm.php(165): SynchVikBooking->sendRequest()
#5 vikbooking/site/controller.php(1634): VboVcmInvoker->doSync()
#6 libraries/adapter/mvc/controller.php(337): VikBookingController->saveorder()
...
#12 wp-settings.php(779): do_action('init')
```

Trois occurrences, à 15:50:38, 15:55:04 et 15:55:57 UTC.

**Ce que la trace établit.** Dans `saveorder()`, Vik Booking appelle
`VboVcmInvoker->doSync()`, qui charge directement les fichiers de Vik Channel
Manager alors que WordPress ne charge plus le greffon. La constante
`VIKCHANNELMANAGER_LIBRARIES` est définie par l'amorce du greffon. Désactivé,
le greffon ne la définit plus, et le code chargé à la main plante.

**Ce qu'elle suggérait, et qui est désormais établi (chapitre 7).** Vik Booking
détecte la présence de Vik Channel Manager par ses fichiers, jamais par son
statut d'activation.

**Conséquence.** La parade posée ce matin contre l'incident, désactiver le
greffon, empêche toute réservation de s'achever sur la préproduction. Les
réservations 1830 et 1831 de ce matin passaient parce que le greffon était
encore actif. Le chapitre 4 du plan de marche prescrit cette désactivation
à chaque recréation : en l'état, il rend aussi les vérifications 5, 6 et 7
impossibles.

**En production** le greffon est actif, et ce chemin n'est pas en cause.

## 4. Les réservations ont bien été créées

L'erreur survient **après** l'insertion. Lecture de `sir_vikbooking_orders` et
`sir_vikbooking_ordersrooms` à partir de 1826 :

| id | statut | créée (UTC) | arrivée | chambre | client |
|---|---|---|---|---|---|
| 1832 | standby | 24.09 11:11:14 | 14.12 | 4 | adresse de recette |
| **1833** | standby | 24.09 15:50:38 | **24.09** | 4 | **adresse Gmail réelle** |
| **1834** | standby | 24.09 15:55:04 | 23.12 | 4 | adresse de recette |
| **1835** | standby | 24.09 15:55:57 | 23.12 | 2 | adresse de recette |

- **1834 et 1835 viennent de cette recette.** Le script les croit non créées
  et ne les inscrit pas au registre local, donc `--nettoyer` ne les verra
  jamais. C'est un échec silencieux au sens de la règle absolue n°6 : le
  rapport affirme une absence qui n'est pas vraie.
- **1833 ne vient pas du script.** Elle a été créée cinq minutes avant, avec
  une vraie adresse de client et une arrivée le jour même, et elle a planté au
  même endroit. **Son origine n'est pas établie.** Un essai manuel dans le
  navigateur est l'hypothèse la plus probable, à confirmer par Thomas. Avec
  WP Mail SMTP en « Do not send » et le garde-fou de messagerie en place,
  aucun e-mail ne devrait lui être parvenu. Ce n'est pas vérifié dans le
  journal d'envoi.

Toutes restent en attente de nettoyage dans l'administration de Vik,
avec 1826 à 1829 et 1832.

## 5. Rien n'est parti vers les plateformes

La dernière ligne de `sir_vikchannelmanager_notifications` sur la préproduction
est la **4028, à 11:11:20 UTC**, qui correspond à la réservation 1832, créée
avant la désactivation. Aucune ligne n'a été ajoutée depuis. L'erreur fatale
arrive dans `getOrderDetails()`, pendant la préparation de la requête et avant
son envoi.

## 6. Ce qui restait à décider le 24 septembre

*Les points 1 et 2 sont tranchés aux chapitres 8 et 9. Le texte d'origine est conservé.*

1. **Rétablir la création de réservations sur la préproduction sans rouvrir
   la voie vers les plateformes.** Trois pistes, par ordre de préférence :
   - **Retirer le dossier `wp-content/plugins/vikchannelmanager` de
     `staging13`** (Site Tools, geste de Thomas). Si Vik Booking détecte le
     greffon par ses fichiers, c'est la seule parade qui écarte en même temps
     l'appel sortant et l'erreur fatale. Les dix verrous en attente
     disparaissent avec lui. Il faudrait alors ajouter ce retrait à l'étape
     obligatoire du chapitre 4, à la place de la simple désactivation, ou
     en plus d'elle.
   - Un mu-plugin qui neutralise `VboVcmInvoker` hors production, **à
     condition** qu'un crochet ou un réglage de Vik Booking le permette sans
     toucher ses fichiers. Ce n'est pas établi : il faudrait d'abord lire
     `vikbooking/site/helpers/vcm.php` et les conditions de `controller.php`
     autour de la ligne 1634.
   - Une question à VikWP sur la détection du Channel Manager.
2. **Corriger le script, dans le cadre de B9i.** Après une réponse 500 à la
   soumission finale, il doit chercher la réservation par son `sid` ou par
   l'adresse de recette qu'il a générée, l'inscrire au registre si elle
   existe, et le dire dans le rapport. « Non créée » ne doit s'afficher qu'une
   fois l'absence vérifiée en base.
3. **Nettoyer 1833, 1834 et 1835** dans l'administration de Vik. Thomas
   confirme d'abord l'origine de 1833.

---

## 7. Comment Vik Booking décide que Vik Channel Manager est présent

25 septembre 2026. Lecture de Vik Booking **1.8.15 sur `staging13`**, par SSH, en
lecture seule. La copie de `.local/vikbooking` est en **1.8.14** et n'est plus à
jour. `site/helpers/vcm.php`, `site/controller.php` et
`admin/helpers/src/autoload.php` sont identiques des deux côtés (même empreinte
md5). `defines.php` et `site/helpers/lib.vikbooking.php` diffèrent, et les
passages cités ont été relus sur le serveur.

**Réponse : par les fichiers, et par eux seuls.** Aucune option, aucune
constante posée par Vik Channel Manager, aucun statut d'activation n'est
consulté.

- **`vcm.php` ne décide rien.** `VboVcmInvoker::__construct()` (l. 52-55) charge
  `synch.vikbooking.php` de Vik Channel Manager sans condition, et `doSync()`
  appelle `SynchVikBooking->sendRequest()`. La décision est prise par
  l'appelant.
- **L'appelant de `saveorder()`** (`site/controller.php:1630`) :
  `if (class_exists('VCMRequestAvailability'))`. C'est le seul test.
- **`class_exists()` déclenche l'autochargeur de Vik Booking**
  (`admin/helpers/src/autoload.php`). Pour toute classe préfixée `VCM`, il
  calcule le chemin en remplaçant `vikbooking` par `vikchannelmanager` dans son
  propre dossier (l. 66), puis inclut le fichier **s'il existe** (`is_file`,
  l. 78-81). Le greffon désactivé a toujours ses fichiers : la classe se charge,
  le test est vrai, et `doSync()` part.
- **Les chemins `VCM_SITE_PATH` et `VCM_ADMIN_PATH`** sont posés par Vik Booking
  lui-même (`defines.php:54-55`), déduits de son propre chemin. Ils ne dépendent
  pas du chargement de Vik Channel Manager.
- **La constante qui manque**, `VIKCHANNELMANAGER_LIBRARIES`, n'est posée que
  par l'amorce de Vik Channel Manager. C'est pour cela que le code chargé à la
  main plante.
- **Le même critère partout.** Les deux autres appels de `saveorder()` et de
  `notifypayment()` (`site/controller.php:1250` et `:2542`) testent
  `is_file(VCM_SITE_PATH/helpers/synch.vikbooking.php)`. `vcmAutoUpdate()`
  (`lib.vikbooking.php:1387`), l'impression de canal (`invokeChannelManager()`)
  et une vingtaine d'autres emplacements testent `is_file` ou `file_exists` sur
  `lib.vikchannelmanager.php`.
- **Le réglage `vcmautoupd`** (« mise à jour automatique ») n'intervient que
  dans `vcmAutoUpdate()`, donc dans les chemins de l'administration. Les trois
  appels du frontal ne le consultent pas.

**Conséquence pour la recette.** Tant que le dossier est sur le disque, désactiver
le greffon n'empêche pas l'appel. Cela empêche seulement qu'il aboutisse : la
réservation 1832, à 11:11, était partie avec le greffon actif. Celles de
15:50 à 15:55 ont planté avant l'envoi (chapitre 5). **Le paiement par Stripe,
s'il était allé au bout, aurait repassé par `notifypayment()` (l. 2542) avec le
même critère.**

## 8. La parade : retirer le greffon de la préproduction

**C'est un geste de Thomas, pas de Claude Code.** Supprimer le dossier
`wp-content/plugins/vikchannelmanager` de `staging13`, ou le **déplacer hors de
`wp-content/plugins/`**, par Site Tools. Ne pas le renommer sur place : un
`vikchannelmanager.off` dans `plugins/` reste un greffon que WordPress liste et
qu'un clic réactiverait. Vik Booking ne le trouverait pas, mais Vik Channel
Manager lui-même tournerait avec ses tâches planifiées et la configuration de la
production.

**Preuve que la réservation aboutit alors.** Le dossier absent, l'autochargeur
renvoie `false` pour `VCMRequestAvailability`, donc le test de la ligne 1630 est
faux et `doSync()` n'est pas appelé. Les tests `is_file` des lignes 1250 et 2542
sont faux. `vcmAutoUpdate()` renvoie `-1`. Le chemin de la trace du chapitre 3
n'est plus emprunté.

**Preuve qu'aucune requête ne peut partir vers e4jConnect.**

- Toutes les adresses d'e4jConnect vivent dans Vik Channel Manager. Par exemple
  `executeARequest()` (`synch.vikbooking.php:499-510`), qui poste vers
  `https://e4jconnect.com/channelmanager/?r=a&c=channels` par
  `E4jConnectRequest`. Sans ses fichiers, ce code n'existe plus sur le serveur.
- Vik Booking 1.8.15 ne contient aucune adresse d'e4jConnect qui serve à une
  requête. Toutes celles du code sont des liens commerciaux, vérifié par
  recherche. Ses fonctions qui passent par e4jConnect, l'IA par exemple
  (`admin/controllers/ai.php:31-40`), exigent la classe `VikChannelManager` et
  s'arrêtent sans elle.
- Les deux mu-plugins présents sur `staging13` et absents du dépôt,
  `api-host.php` et `vre-paid-autoconfirm.php`, ne contiennent ni `vcm`, ni
  `synch`, ni `channel`, ni `e4j`, ni `curl`, ni `wp_remote`.

**Les parades par mu-plugin sont écartées**, parce qu'aucune ne se prouve :

- *Prédéfinir `VCM_SITE_PATH` vers un dossier vide.* `defines.php` le permet
  (`defined() or define()`), mais l'autochargeur calcule le chemin des classes
  `VCM*` sans cette constante (l. 66). Le test de la ligne 1630 resterait vrai,
  et le constructeur de `VboVcmInvoker` planterait sur un `require_once`
  introuvable. Il faudrait en plus déclarer une fausse `SynchVikBooking`, qui se
  ferait passer pour une classe tierce. Et les autres classes `VCM*`, appelées
  par l'administration lors du nettoyage des réservations en attente
  (`setForRelease`, `admin/controller.php:3283` et `:7877`,
  `admin/helpers/src/model/reservation.php:1663`), se chargeraient toujours
  depuis le vrai dossier.
- *Définir `VIKCHANNELMANAGER_LIBRARIES`.* Cela ferait disparaître l'erreur
  fatale en laissant le code aller plus loin, jusqu'à l'envoi. Cela rouvre la
  voie au lieu de la fermer.
- *Bloquer le HTTP sortant par WordPress* (`pre_http_request`,
  `WP_HTTP_BLOCK_EXTERNAL`). Vik Channel Manager passe par cURL directement
  (`E4jConnectRequest`, et `curl_init` dans ses contrôleurs), pas par l'API HTTP
  de WordPress. Un tel blocage ne l'arrêterait pas.
- *Le réglage `vcmautoupd`.* Il n'est pas consulté par les appels du frontal
  (chapitre 7). Le changer serait de plus une écriture en base.

**Ce qui reste.** `modules/mod_vikbooking_otareviews/helper.php:274` charge
`lib.vikchannelmanager.php` sans test. Si le module d'avis des plateformes est
placé sur une page de `staging13`, cette page plantera une fois le dossier
retiré. Ce n'est pas une voie vers l'extérieur, et sa présence sur une page
n'est pas vérifiée. Les greffons maison `lme-vik-contacts-api` et `lme-vik-ics`
restent à inventorier, comme le demande l'incident.

**Au plan de marche** (tenu par Cowork) : l'étape obligatoire de la recréation
de la préproduction devient « retirer le dossier `vikchannelmanager` », et non
plus seulement « désactiver ». Le préalable de `recetter-moteur.sh` accepte
déjà l'état `absent` (`vcm_statut_acceptable`). Il n'a pas à changer.

## 9. Correction du script : plus de « non créée » sans lecture en base

`recetter-moteur-vik.sh` et `recetter-moteur.sh`, 25 septembre 2026.

- L'adresse de recette générée à la soumission (`recette+<epoch>@…`, unique à la
  seconde) est conservée. Après un code HTTP inattendu à la soumission finale,
  ou une réponse sans `sid`, le script cherche en base la réservation qui la
  porte, par une nouvelle aide distante `orders-from-email` (`SELECT id, sid,
  ts, status` sur `sir_vikbooking_orders`, en lecture seule).
- Trois issues, chacune dite au rapport :
  - **trouvée** : statut `creee_sur_erreur`. La réservation est inscrite au
    registre, à nettoyer, donc vue par `--nettoyer`. Le rapport dit « insérée
    malgré le code 500 ». En 3a, c'est une fuite de marque, rapportée en KO ;
  - **absente** : « non créée, absence vérifiée en base » ;
  - **relecture impossible**, ou plusieurs lignes : statut `indetermine`. Le
    rapport ne conclut pas et donne l'adresse à chercher dans Vik.
- Un échec avant la soumission finale (aucun tarif, page incomplète) est
  rapporté « soumission finale non envoyée » : aucune insertion n'était
  possible.
- **Vérifié** : sur `staging13`, l'aide renvoie `NROWS=0` pour une adresse
  inexistante, et retrouve la 1834, en `standby`, par son adresse de recette.
  La logique de décision est testée hors ligne sur les quatre cas : aucune
  ligne, une, deux, panne de la lecture. **Pas de recette complète rejouée** :
  elle recréerait une réservation en attente sur une préproduction qui
  plante tant que le dossier est là.
- Le défaut B9i du code de sortie en `0` n'est pas traité ici.
