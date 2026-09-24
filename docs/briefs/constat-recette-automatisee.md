# Constat — recette automatisée du moteur

23 septembre 2026. Réponse au prompt de lancement de
`docs/briefs/brief-recette-automatisee.md` chapitre 6. Lu en entier avant
d'écrire une ligne : ce brief, `docs/briefs/constat-script-deploiement.md`,
`docs/briefs/constat-mail-guard.md` et le chapitre « La recette du moteur »
de `docs/briefs/plan-de-marche.md`.

**Ce constat documente un script exécuté pour de vrai contre la
préproduction, pas un script lu et jugé plausible.** Chaque affirmation
ci-dessous — ce qui fonctionne, ce qui ne fonctionne pas, ce qui reste
incertain — vient d'une exécution réelle contre `staging13.linstantcle.ch`
le 23 septembre 2026, jamais d'une lecture de code seule. Deux défauts réels
du premier jet ont été trouvés et corrigés en cours de route précisément
parce que le script a été exécuté avant d'être jugé fini ; ils sont
documentés au chapitre 4, avec la méthode qui les a révélés.

**Rien n'a été déployé en production. Aucune ligne n'a été écrite en base
par SQL. Aucune clé n'a été lue** — les seules lectures de configuration
Stripe sont le préfixe `LEFT(secretkey, 8)` (déjà la méthode de
`deployer-moteur.sh`) et la clé nommée `skipbtn`, un booléen non secret,
jamais la clé secrète elle-même ni le reste du blob `params`.

---

## 1. La transcription d'enveloppe — `mu-plugins/lme-mail-guard/`

Chapitre 2 du brief. Trois fichiers modifiés :

- `includes/core.php` — deux fonctions pures ajoutées : `lme_mail_guard_find_header_value()`
  (cherche un en-tête par nom, insensible à la casse, même garde que
  `lme_mail_guard_strip_cc_bcc()` contre une valeur d'en-tête qui contient
  littéralement `from:`) et `lme_mail_guard_format_envelope_line()`, qui
  compose une ligne JSON (`json_encode`, jamais `wp_json_encode` : ce fichier
  reste sans dépendance WordPress, testable par `php` seul) portant `ts`,
  `to`, `from`, `sender`, `reply_to`, `subject`, `host` — jamais `message` ni
  `attachments`, qui ne sont même pas des paramètres de cette fonction.
- `includes/guard.php` — `lme_mail_guard_maybe_transcribe_envelope()`, appelée
  par `lme_mail_guard_filter_wp_mail()` avec la valeur `$should_redirect`
  **déjà calculée** par ce même appelant pour décider du détournement :
  jamais un second test qui pourrait diverger, la même variable booléenne
  gouverne les deux gestes. Écrit dans un fichier dédié,
  `wp-content/lme-mail-guard-envelopes.log` (pas `debug.log` : ce que ce
  fichier porte est une donnée personnelle, l'adresse réelle du destinataire,
  et il doit pouvoir être identifié et purgé d'un seul geste avec la
  préproduction). Un échec d'écriture journalise une erreur
  (`envelope_transcript_write_failed`) plutôt que de disparaître en silence.
- `lme-mail-guard.php` — docblock mise à jour.

**Tests.** `tests/test-core.php` : 23 tests ajoutés (47 → 70, 0 échec),
couvrant `lme_mail_guard_find_header_value()`, `lme_mail_guard_format_envelope_line()`
(y compris l'absence de fuite du Bcc d'origine, l'aplatissement d'un retour à
la ligne injecté dans l'objet, et l'absence de tout champ quand l'hôte ou les
en-têtes manquent) et une section nommée explicitement « l'activation de la
transcription réutilise `lme_mail_guard_should_redirect()`, pas un second
test », qui rejoue les cas déjà couverts plus haut pour qu'ils survivent même
si cette suite est un jour réorganisée.

```
$ php mu-plugins/lme-mail-guard/tests/test-core.php
70 tests, 0 échec(s).
```

**Déployé et vérifié sur `staging13.linstantcle.ch`** par `deployer-moteur.sh`
(chapitre 6 de ce script, redirections suivies, aucune erreur fatale PHP) :
seuls les trois fichiers ci-dessus étaient à mettre à jour, le reste du
manifeste était déjà à jour. Le fichier `lme-mail-guard-envelopes.log`
n'existe pas encore sur ce serveur — normal, aucun e-mail détourné n'a
transité par ce chemin avant les essais du chapitre 3 ci-dessous, et il n'est
créé qu'à la première écriture.

---

## 2. `recetter-moteur.sh` et `recetter-moteur-vik.sh`

Deux fichiers à la racine du dépôt, aux conventions de `deployer-moteur.sh` :
préalables refusants, sortie en erreur au premier contrôle rouge, rapport
final. Le second porte le parcours réel de réservation VikBooking (recherche
→ devis → coordonnées → soumission → annulation), séparé du premier pour
qu'un lecteur qui veut comprendre la politique du script (préalables, levier,
séquencement) n'ait pas à traverser la mécanique HTTP de VikBooking, et
inversement.

### 2.1 — Préalables, refusants

- **`wp_get_environment_type() == 'staging'`**, vérifié par empreinte
  hexadécimale (`bin2hex`), jamais la chaîne affichée — même précaution que
  `deployer-moteur.sh` chapitre 3.1 contre la traduction automatique de
  TranslatePress, qui intercepte jusqu'à la sortie de `wp eval`.
- **L'hôte visé n'est pas un hôte de production** — jamais une seconde liste
  tenue à la main : le script lit directement
  `lme_mail_guard_default_production_hosts()` depuis
  `mu-plugins/lme-mail-guard/includes/core.php` par un `php -r` local, la
  même liste que le greffon applique réellement en production. Une liste qui
  divergerait de celle-ci serait elle-même un défaut, pas une fonctionnalité
  de ce script.
- **VikStripe publié en clés de test**, `LEFT(secretkey, 8) = 'sk_test_'`,
  posé par la requête SQL elle-même — jamais la clé, jamais son chargement en
  mémoire côté script. Même geste que `deployer-moteur.sh`.
- **`skipbtn` de la passerelle Stripe publiée**, relu par sa seule clé nommée
  (booléen, non secret) — chapitre 4.3 explique pourquoi ce préalable existe
  et ce qu'il change.

### 2.2 — Les deux passes, un seul levier possédé de bout en bout

Le script capture la valeur d'entrée de `LME_BRANDS_HOST_OVERRIDE`
(`capture_override_original()`) avant toute écriture, pose
`reservation.sexcaperoom.ch` pour la première passe puis `linstantcle.ch`
pour la seconde, et restaure la valeur d'entrée **dans un `trap … EXIT`** :
échec, `mourir()`, `Ctrl-C` ou fin normale y mènent tous. Contrairement à
`deployer-moteur.sh`, dont le `--forcer-override` protège deux invocations
séparées de s'écraser silencieusement, `recetter-moteur.sh` possède le levier
pour la durée de son exécution : passer d'une marque à l'autre est le geste
que ce script existe pour faire, jamais un essai d'un tiers qu'il écraserait
— aucune option `--forcer-override` n'existe donc ici (retirée après le
premier essai réel : elle refusait la propre bascule interne du script,
révélé au premier lancement, chapitre 4.1).

### 2.3 — Couverture des huit vérifications

| # | Méthode réelle | Résultat le 23 septembre 2026 |
|---|---|---|
| 1 | `wp eval` direct sur `lme_brands_current_request_brand_key()` (empreinte hexadécimale) sous le levier, **et** sur `lme_brands_resolve_brand_by_host_cached()` avec un hôte inconnu, sans requête HTTP — plus robuste que `deployer-moteur.sh`, qui documentait ce cas comme non automatisable faute d'un moyen de forger un en-tête `Host` contre un certificat qui ne le couvre pas | **OK, entièrement automatisé, les deux passes** |
| 2 | Requête HTTP réelle sur les quatre vues (`roomdetails`, `availability`, `roomslist`, `search`), deux lectures espacées de deux secondes avec un paramètre anti-cache différent à chaque fois, assertion d'absence du nom de la chambre étrangère (sans son apostrophe : elle est rendue tantôt en clair, tantôt en entité HTML `&#8217;` selon la vue) | **KO reproductible, les deux passes — un vrai défaut trouvé, chapitre 3.1** |
| 3 | Parcours réel recherche → devis → coordonnées → soumission (`recetter-moteur-vik.sh`) pour une chambre étrangère active, assertion de refus `403` avec le titre exact de `lme_brands_reject_booking_attempt()` — jamais confondu avec un refus natif de Vik, qui redirige avec un message plutôt que d'émettre un `wp_die()`. Pour la chambre désactivée, même parcours, mais non concluant : elle n'apparaît dans aucun résultat de recherche natif de Vik (voir chapitre 3.2) | **3a : OK, refus confirmé, les deux passes. 3b : non concluant, documenté, pas un échec tu** |
| 4 | Identique à `deployer-moteur.sh` chapitre 6 : `curl -L`, jetons `--srlm-` cherchés dans la réponse finale | **OK, les deux passes** |
| 5 | Transcription d'enveloppe relue par SSH (`lme-mail-guard-envelopes.log`) après une réservation réelle confirmée | **Non concluant sur cette préproduction, chapitre 3.3 : aucune réservation n'atteint jamais 'confirmed'** |
| 6 | Première moitié (ce que le code envoie à Stripe) : absence de `payment_brand_undetermined` / `payment_object_unexpected` / `payment_booking_id_unreadable` dans `debug.log` pendant le test — **OK, les deux passes**. Seconde moitié (redirection Stripe Checkout, hôte de retour) : **non atteinte**, chapitre 3.3 |
| 7 | Identifiant de la tâche planifiée `email_reminder` relu par `wp db query` (`sir_vikbooking_cronjobs`, colonnes `id`/`class_file`, non secret), hook calculé (`vikbooking_cron_email_reminder_<id>`), déclenché par `wp cron event run` | **Mécanisme écrit et prêt, jamais exercé de bout en bout — dépend d'une réservation confirmée, chapitre 3.3** |
| 8 | `curl` sur un chemin hors liste blanche, `302` attendu vers `sexcaperoom.ch` | **KO annoncé, comme demandé : G2 n'existe pas encore (plan-de-marche.md, chantier G)** |

---

## 3. Ce que l'exécution réelle a trouvé

### 3.1 — Un vrai défaut de `room-filter.php` (vérification 2), reproductible dans les deux sens

> **Cause établie et corrigée le 24 septembre 2026**, `constat-correctif-room-filter.md` : Vik rend la vue pendant `init`, avant le `template_redirect` où `room-filter.php` retirait le paramètre. L'hypothèse ci-dessous, « un autre chemin de lecture », était fausse : le chemin est bien `$app->input` → `$_REQUEST` ; c'est le moment qui l'était. La vérification 2 porte désormais sur les identifiants de chambre du conteneur Vik, plus sur le nom dans la page.

Sous le levier Sexcape Room, `https://staging13.linstantcle.ch/en/le-boudoir-du-desir/?view=roomdetails&roomid=2&tmpl=component`
rend intégralement la fiche de **L'Aparté** (chambre 2, marque L'Instant Clé)
— nom, catégorie, description — dans le bloc `vbo-room-details-wrap` de
VikBooking, pas seulement dans un menu de navigation. Sous le levier
L'Instant Clé, l'inverse se produit avec Le Boudoir du Désir sur la page
`l-aparte`. **Reproduit sur cinq requêtes consécutives, dans les deux
directions, avec un paramètre anti-cache différent à chaque fois** — ce
n'est pas un artefact de cache NitroPack ou SiteGround (l'en-tête
`cache-control: no-store, no-cache, must-revalidate` est présent sur la
réponse finale, et le résultat ne change jamais d'une requête à l'autre).

**Ce qui est établi, pas supposé :**

- `mu-plugins/lme-brands/includes/room-filter.php`,
  `lme_brands_strip_foreign_single_room()`, **s'exécute bel et bien** :
  `debug.log` porte la ligne `[lme-brands] [WARNING] [foreign_room_param_stripped]`
  pour au moins une partie des requêtes de test, avec le bon `room_id` et la
  bonne marque résolue. Le greffon ne se tait pas, il agit.
- La chaîne de références PHP qui devrait rendre cet `unset()` visible à
  VikBooking a été relue directement sur le serveur, jusqu'à
  `libraries/adapter/input/input.php` : `JInput::__construct()` pose
  `$this->data = &$_REQUEST` (par référence, ligne ~101) pour l'instance par
  défaut, et l'instance `get`/`post` est construite par
  `new JInput($GLOBALS['_GET'], …)` (donc `&$_GET` par référence également,
  ligne ~148) — `JInput::get()` relit `$this->data[$name]` à chaque appel,
  sans mise en cache. Sur cette seule lecture, le mécanisme **devrait**
  refléter l'`unset()` de `room-filter.php`.
- Malgré cela, le contenu rendu ne le reflète pas.

**Ce que ce constat ne fait pas : trancher la cause exacte.** L'hypothèse la
plus probable, non vérifiée par manque de temps dans cette session, est que
le rendu de la vue `roomdetails` ne relit pas `roomid` par `$app->input` mais
par un autre chemin (un attribut de requête WordPress déjà capturé
ailleurs, une variable de requête dupliquée par un autre greffon, ou une
lecture directe de `$_SERVER['QUERY_STRING']`) — mais l'affirmer sans
l'avoir lu serait exactement la faute que ce dépôt corrige déjà deux fois
dans son histoire (constat-mail-guard.md, `payment-brand.php`). **Ce défaut
mérite son propre brief de correctif**, sur le modèle de
`brief-correctif-levier-et-fatal-paiement.md`, avec une trace d'exécution en
direct (un `error_log()` temporaire dans `room-filter.php`, comparé à la même
requête) plutôt qu'une nouvelle lecture statique.

**Portée.** Le brief de `room-filter.php` le dit lui-même dès sa première
ligne : « Ce n'est pas une garde opposable […] la seule garantie réelle est
`includes/booking-guard.php` ». La vérification 3 confirme que cette garde
réelle, elle, fonctionne (chapitre suivant) : ce défaut est un défaut de
présentation (un visiteur curieux voit une fiche qu'il ne devrait pas voir),
pas une brèche qui permettrait de réserver une chambre étrangère.

### 3.2 — La garde de réservation (vérification 3) fonctionne, une fois deux défauts du script lui-même corrigés

Deux défauts du **script**, pas de `lme-brands`, ont dû être corrigés avant
d'obtenir un résultat net :

1. **`saveorder()` exige `vbf6`/`vbf7`/`vbf8`** (adresse, code postal,
   localité — `sir_vikbooking_custfields.required = 1`), absents du premier
   jet. Sans eux, `saveorder()` refuse via `showSelectVb('VBINSUFDATA')` et
   retombe en `200` sur la vue de recherche par défaut — un signal que le
   script rapportait à tort comme « ambigu » plutôt que comme une soumission
   incomplète. **Correction du 24 septembre** : ce refus n'est pas muet.
   `showSelectVb()` imprime son motif dans un `<p class="err">`
   (`site/helpers/error_form.php:716-717`), que le premier jet ne lisait pas ;
   voir 3.2 bis. Établi
   par lecture directe de `site/controller.php` (boucle sur
   `sir_vikbooking_custfields`) après un premier essai réel qui échouait de
   cette façon précise.
2. **`idorder` n'est porté par aucun champ fiable** de la page de
   confirmation — la première version du script le cherchait dans un champ
   caché qui n'existe pas, laissant le registre local avec un identifiant
   vide. Corrigé par une relecture `wp db query "SELECT id FROM
   sir_vikbooking_orders WHERE sid = …"` (structurel, non secret, même
   discipline que le préfixe Stripe).

Une fois ces deux défauts corrigés, la vérification 3a est **nette et
reproductible dans les deux sens** — **sans `--appliquer`** ; avec, voir
3.2 bis : une tentative de réservation de la
chambre étrangère active (chambre 2 sous le levier Sexcape Room, chambre 4
sous le levier L'Instant Clé), menée par le vrai parcours HTTP jusqu'à
`task=saveorder`, est refusée par un `403` portant le titre exact de
`lme_brands_reject_booking_attempt()` — jamais un refus natif de Vik, qui
redirige avec un message plutôt que d'émettre un `wp_die()`.

**3b, la chambre désactivée, reste non concluant, et c'est documenté plutôt
que forcé.** Les chambres de test 5 et 6 (`avail = 0`) n'apparaissent dans
aucun résultat de recherche natif de Vik : le parcours du script s'arrête
avant `oconfirm` faute de tarif proposé (`priceid1` absent), un comportement
natif de Vik et non un défaut de la garde. Contourner cela demanderait de
construire une soumission `saveorder` de toutes pièces avec un tarif
emprunté à une autre chambre — une expérience non tentée ici, faute de
certitude sur ce que Vik validerait avant d'atteindre notre propre garde, et
qui aurait mérité sa propre investigation plutôt qu'un raccourci non
vérifié. La couverture de ce cas précis reste celle des tests unitaires de
`lme-brands` (`lme_brands_room_is_available()`), déjà verts.

### 3.2 bis — Le « 200 sur `/fr/reserver/` » de la passe L'Instant Clé : la garde n'a jamais été atteinte, le script se heurtait à sa propre réservation

24 septembre 2026, tâche B9h de `plan-de-marche.md`. Lors des exécutions
avec `--appliquer` du 24 septembre, la vérification 3a de la passe
L'Instant Clé (chambre 4, Le Boudoir du Désir, sous le levier
`linstantcle.ch`) rendait un `200` sur `/fr/reserver/`, sans refus et sans
`sid`, là où la passe Sexcape Room rendait un `403` net. **Cause établie :
un défaut du script, pas de la garde.** Vik refusait la soumission avant
que le crochet de la garde ne soit déclenché, parce que la chambre 4 était
tenue par le verrou temporaire de la réservation d'essai que le script
venait lui-même de créer, à la même date, dans la passe précédente.

**Le mécanisme, lu dans le code de Vik** (`.local/vikbooking`, Vik Booking
1.8.14) :

- chaque réservation créée en `standby` pose un verrou dans
  `sir_vikbooking_tmplock` (`site/controller.php:1583-1603`), valable
  `minuteslock` minutes (`lib.vikbooking.php:3637`) — **20** sur staging13,
  lu par sa seule clé ;
- `saveorder()` interroge ce verrou par `VikBooking::roomNotLocked()`
  (`controller.php:903`) et, s'il tient, appelle
  `showSelectVb('VBROOMBOOKEDBYOTHER')` puis `return` (`:909-910`) ;
- le crochet de la garde, `onBeforeCreateBookingRecord`, n'est déclenché
  qu'aux lignes **1114** et **1512**, donc jamais atteint ;
- la recherche et le devis (`search`, `showprc`) ne consultent pas ce
  verrou : le script obtenait un tarif, allait jusqu'à `saveorder`, et
  lisait le `200` de la vue d'erreur comme un « signal ambigu ».

**Pourquoi seulement dans ce sens.** Dans `recetter-moteur.sh`, chaque passe
enchaîne les vérifications 1 à 4, puis 5-6. La passe Sexcape Room, la
première, réserve pour de vrai sa chambre active, **la 4**, à J+60 (5-6).
La passe L'Instant Clé, moins d'une minute plus tard, tente en 3a sa chambre
étrangère, **la 4 aussi**, à **la même date J+60**, dans les 20 minutes du
verrou. La passe Sexcape Room, elle, tente la chambre 2 avant qu'aucune
réservation n'existe : elle ne pouvait pas heurter ce verrou. Sans
`--appliquer`, aucune réservation n'est créée, et la 3a est nette dans les
deux sens : d'où le constat du 23 septembre (3.2), exact pour ce mode seul.

**Preuves, par le journal de staging13.** Toutes les tentatives sur la
chambre 4 en passe L'Instant Clé du 22 et du 23 septembre, sans
`--appliquer`, portent la ligne `foreign_room_booking_attempt` de la garde.
Les exécutions avec `--appliquer` du 24 septembre (09:36 et 10:34 UTC) n'en
portent que pour la chambre 2 : **aucune tentative sur la chambre 4 n'a
atteint la garde ce jour-là**, et chacune suit de quelques secondes la
création de #1828 puis de #1830, chambre 4, arrivée le 23 novembre. La
première exécution avec `--appliquer`, le 23 septembre à 20:04, montre la
même absence juste après #1826.

**Preuve par rejeu, le 24 septembre à 11:10 UTC.** Quatre tentatives menées
par les fonctions mêmes de `recetter-moteur-vik.sh`, préalables au vert
(dont `environment: staging`), levier restauré à sa valeur d'entrée :

| Tentative | Levier | Chambre | Arrivée | Résultat | Journal `lme-brands` |
|---|---|---|---|---|---|
| A | `linstantcle.ch` | 4 | 7 déc. | **`403`**, « Réservation refusée » | `foreign_room_booking_attempt` |
| B | `reservation.sexcaperoom.ch` | 4 | 14 déc. | **créée**, #1832, `standby` | — |
| C | `linstantcle.ch` | 4 | 14 déc. | **`200`** sur `/fr/reserver/`, `<p class="err">` : « Désolé, la chambre a déjà été réservée. Veuillez effectuer une nouvelle réservation. » | **aucune ligne** de la garde |
| D | `linstantcle.ch` | 4 | 16 déc. | **`403`**, « Réservation refusée » | `foreign_room_booking_attempt` |

C reproduit exactement le signal du plan de marche, et son motif est écrit
en toutes lettres dans la page. A et D, même levier, même chambre, dates
sans verrou : la garde refuse. **La garde fonctionne dans les deux sens.**
Les hypothèses du plan de marche sont écartées l'une et l'autre : ce n'est
ni une soumission incomplète (`VBINSUFDATA`), ni une garde qui ne se
déclenche pas — c'est un refus natif de Vik, antérieur à la garde,
provoqué par le script.

**Correctif du script** (`recetter-moteur-vik.sh`, aucun fichier déployé
touché) :

1. **Les dates ne se partagent plus.** La 3a vise J+60, la réservation
   réelle des vérifications 5-6 vise J+90 (`JOURS_VERIF3`,
   `JOURS_RESERVATION_REELLE`). Une exécution ne peut plus se heurter à
   elle-même.
2. **Le refus natif de Vik est lu, jamais pris pour un signal ambigu.**
   `vik_creer_reservation()` relève le `<p class="err">` de la page et rend
   le statut `refus_vik`, avec le motif, distinct de `refused_403` (la
   garde) et de `ambigu` (rien d'identifié).
3. **La 3a passe au jour suivant** quand Vik refuse avant la garde
   (`refus_vik`, ou aucun tarif proposé), sur sept dates au plus
   (`JOURS_VERIF3_ESSAIS`), et rapporte la date qui a conclu. Nécessaire
   au-delà du point 1 : les réservations d'essai `confirmed` des exécutions
   précédentes occupent durablement leurs dates, puisque le script ne sait
   pas les annuler (chapitre 5). Au bout des sept dates, la 3a est rapportée
   **non concluante**, jamais « OK ».

**Vérifié pour de vrai après correctif**, exécution sans `--appliquer` à
11:13 UTC : le 23 novembre (J+60) est occupé dans les deux passes, par
#1830 (chambre 4) et #1831 (chambre 2), toutes deux `confirmed` ; la 3a
passe au 24 novembre et rend un `403` net dans les deux passes, chacun
journalisé par la garde (11:13:49 pour la chambre 2, 11:14:37 pour la
chambre 4). L'extraction du motif a été vérifiée sur la page enregistrée du
rejeu C, et donne une chaîne vide sur les pages de A et de B. **Non exercé
après correctif : une exécution complète avec `--appliquer`**, qui aurait
créé deux réservations d'essai de plus, que le script ne sait pas annuler.

**Une prémisse corrigée au passage.** #1830 et #1831 sont `confirmed` dans
`sir_vikbooking_orders` : le paiement de test a abouti. Le `403` de leur
annulation (`constat-reprise-1830-1831.md`, 3.5) ne vient donc pas d'un
statut `standby`, contrairement à la règle du chapitre 5, qui reste vraie
pour #1826 à #1829. Cause non établie ici.

**Laissé sur staging13 par ce rejeu :** #1832, chambre 4, 14 décembre,
`standby`, au nom `RECETTE AUTOMATISEE - NE PAS TRAITER`, inscrite au
registre local en `standby_sans_paiement`.

### 3.3 — Aucune réservation de test n'atteint `checkout.stripe.com` sur cette préproduction : le script ne suit pas le lien du bouton PAY NOW

**Correction du 24 septembre : ce chapitre attribuait le blocage à `skipbtn`, ce qui était faux.** Lu dans `wp-vikstripe/stripe.php:233`, `skipbtn` est le paramètre VikStripe **« Auto-redirect »**, aux options **inversées** (`1 => No`, `0 => Yes`) : il choisit seulement si le client est amené vers Stripe Checkout par un script JavaScript (`stripe.php:549`) ou doit cliquer le bouton **PAY NOW** — un lien qui pointe, dans les deux cas, vers la même session Stripe réelle (`tmpl/success.html.php`, attribut `href`). Il ne contourne jamais Stripe.

Établi par lecture directe, non secrète, de `sir_vikbooking_gpayments` :

```
id  name                          published  skipbtn
3   Pay (now or later)            1          1
6   Stripe - Keep it for tests    0          1
```

Les **deux** passerelles Stripe de cette installation, y compris la seule
publiée (id 3), portent `skipbtn = 1` : Auto-redirect à No, le client voit le
bouton **PAY NOW** plutôt que d'y être mené sans un clic. Deux réservations
réelles ont été créées pendant cette session (chambre 4 sous Sexcape Room,
sid `1373638763` ; chambre 2 sous L'Instant Clé, sid `43003379`) : les deux
sont restées en `standby`, sur la page qui porte ce bouton — un parcours en
`curl` n'exécute aucun JavaScript et ne clique aucun lien de lui-même, ce
script ne l'a donc jamais suivi. Aucune des deux n'a atteint
`checkout.stripe.com`, faute de paiement, pas faute d'y avoir droit. Aucun
e-mail n'a été envoyé à cette étape — la transcription d'enveloppe du
chapitre 1 n'a rien à montrer, pas parce qu'elle est en défaut, mais parce
qu'aucun `wp_mail()` ne s'est déclenché pour une commande `standby` sans
paiement.

**Conséquence en cascade, établie et non supposée :** les vérifications 5, 6
(seconde moitié) et 7 ne pouvaient pas se conclure sur cette préproduction
tant que le script ne relevait pas ce lien dans le HTML pour le rendre à
l'humain — un défaut du script, corrigé le 24 septembre (chapitre 4.3 du
`plan-de-marche.md`), jamais un réglage à changer dans l'administration de
Vik.

**Effet secondaire, à connaître avant de relancer `--appliquer` :** une
réservation qui reste en `standby` **ne peut pas être annulée par
`--nettoyer`**. `task=docancelbooking` (`site/controller.php`) exige
`status = 'confirmed'` dans sa requête de validation ; une commande `standby`
y répond `403`, testé pour de vrai sur les deux réservations ci-dessus. Ce
n'est pas une limite de ce script, c'est une propriété du contrôleur de Vik.

**État laissé sur `staging13.linstantcle.ch` à la fin de cette session :**
deux réservations de test, `standby`, jamais annulées :

| id | sid | chambre | marque | client |
|---|---|---|---|---|
| 1826 | 1373638763 | 4 | sexcaperoom | RECETTE AUTOMATISEE - NE PAS TRAITER |
| 1827 | 43003379 | 2 | linstantcle | RECETTE AUTOMATISEE - NE PAS TRAITER |

Reconnaissables au premier coup d'œil dans l'administration de Vik par ce nom
et par leur adresse (`@recette-automatisee.icl-dev.invalid`). **Elles restent
à la charge de Thomas** : annulation manuelle dans l'administration de Vik,
ou reprise automatique par le balayage natif des commandes `standby`
abandonnées le jour où la parade de paiement D (`plan-de-marche.md`) est en
place. Ni l'une ni l'autre ne porte de paiement réel — `totpaid` vaut `NULL`
sur les deux.

**Confirmation du 24 septembre, script corrigé, rejoué pour de vrai contre le
même hôte :** les deux passes ont cette fois relevé un vrai lien
`checkout.stripe.com` dans la page portant le bouton PAY NOW — jamais atteint
avant cette correction. Deux nouvelles réservations, `standby`, en attente du
paiement de test au navigateur (chapitre 5) :

| id | sid | chambre | marque | client |
|---|---|---|---|---|
| 1828 | 1381619067 | 4 | sexcaperoom | RECETTE AUTOMATISEE - NE PAS TRAITER |
| 1829 | 177871985 | 2 | linstantcle | RECETTE AUTOMATISEE - NE PAS TRAITER |

S'ajoutent à 1826 et 1827 ci-dessus, toujours non annulées. `--reprise 1828`
et `--reprise 1829` continueront une fois la carte de test
4242 4242 4242 4242 saisie au navigateur sur le lien imprimé par le script.

---

## 4. Ce que le script refuse, et pourquoi

| Préalable | Refuse si |
|---|---|
| `wp_get_environment_type()` | différent de `staging` (empreinte hexadécimale) |
| Hôte visé | figure dans `lme_mail_guard_default_production_hosts()` |
| VikStripe | préfixe de clé secrète différent de `sk_test_`, ou passerelle publiée ambiguë (zéro ou plusieurs) |
| Connexion SSH / wp-cli | indisponible |

Une fois les préalables au vert, **rien n'est simulé par défaut pour les
vérifications 1, 2, 3a, 4 et 8** : elles ne créent jamais rien (3a s'attend à
un refus, 3b aux résultats natifs de Vik) et tournent toujours. Les
vérifications 5, 6 et 7, qui créent de vraies réservations, exigent
`--appliquer` — c'est la « simulation par défaut » du brief, transposée à un
script dont la moitié des vérifications n'écrit jamais rien.

**4.1 — Un défaut trouvé et corrigé en cours de route, sur le levier
lui-même.** Le premier jet reprenait le `--forcer-override` de
`deployer-moteur.sh` : au deuxième passage, le script refusait de poser son
propre levier, puisqu'il l'avait déjà posé lui-même à la passe précédente.
Retiré : ce script possède le levier pour la durée de son exécution (chapitre
2.2), la protection de `deployer-moteur.sh` n'a pas d'objet ici.

**4.2 — Un défaut trouvé et corrigé, sur une substitution de commande.**
`vik_post_avec_statut()` posait `VIK_LAST_CODE`/`VIK_LAST_URL` depuis
l'intérieur d'une fonction dont la sortie était capturée par
`$(…)` — un sous-shell, qui perd toute affectation à sa fermeture. Le premier
essai réel a échoué avec `VIK_LAST_CODE: unbound variable`. Corrigé :
`vik_curl_post_brut()` ne fait plus qu'imprimer corps et marqueurs sur sa
sortie standard ; c'est l'appelant, dans son propre contexte, jamais
lui-même capturé, qui les en extrait.

**4.3 — Le réglage `skipbtn`, ajouté aux préalables après l'avoir trouvé en
marchant.** Voir chapitre 3.3. Ce n'était pas prévisible avant d'avoir tenté
une vraie réservation.

---

## 5. `--nettoyer` et `--reprise`

`--nettoyer` parcourt le registre local
(`~/.icl-dev-recette/<hôte>/reservations.tsv`) et annule chaque réservation
non encore `nettoyee` par `task=docancelbooking`, **jamais par SQL** —
fonctionne y compris après un échec du reste du script, puisque c'est un
mode d'exécution séparé qui ne dépend d'aucun autre préalable que la
connexion SSH. Testé pour de vrai contre les deux réservations `standby` du
chapitre 3.3 : rapporte honnêtement l'échec (`403`, statut probable
`standby`) plutôt qu'un succès fictif.

`--reprise IDORDER` reprend une réservation laissée en attente à la
redirection Stripe (chapitre 4 du brief) : relit la page de confirmation,
la transcription d'enveloppe (vérification 5), déclenche le rappel avant
séjour (vérification 7), puis annule. **Non exercé de bout en bout dans
cette session** : aucune réservation n'a atteint Stripe Checkout (chapitre
3.3), donc aucune n'a de carte de test à saisir. Le mécanisme est écrit et
partage son code d'annulation avec `--nettoyer` ; il reste à exercer le jour
où `skipbtn` est corrigé ou qu'un tiers gpayid publié le permet.

Refuse de rendre une URL de paiement si VikStripe n'est pas en clés de test
— vérifié par le seul préfixe, comme demandé — avant même de tenter une
réservation (préalable, chapitre 2.1).

---

## 6. Ce que ce constat ne fait pas, et ce qui reste à l'humain

- **Ne corrige pas** le défaut de `room-filter.php` trouvé au chapitre 3.1 :
  mérite son propre brief, avec une trace d'exécution en direct.
- **Ne change pas** `skipbtn` dans l'administration de Vik : geste de Thomas,
  jamais de ce script (chapitre 3.3).
- **N'annule pas** les deux réservations de test laissées en `standby`
  (chapitre 3.3) : hors d'atteinte de `task=docancelbooking` tant qu'elles
  ne sont pas `confirmed`.
- **Ne conclut rien seul.** Comme demandé : ce script constate, la revue de
  ce qu'il rend reste un travail de Cowork.
- **Ne dispense pas** de la recette en production : les vérifications 4, 5
  et 8 s'y rejouent à la main, sur une vraie réservation, au moment de B10 —
  inchangé depuis le brief.
- **Le cache reste le premier suspect** pour toute vérification future qui
  donnerait un résultat inconstant entre deux lectures : la vérification 2
  du script relit deux fois avec un paramètre anti-cache différent
  précisément pour cette raison (règle absolue n°4 de `CLAUDE.md`), et
  rapporte « inconstant » plutôt que de trancher au hasard si les deux
  lectures divergent — cas non rencontré cette fois (les deux lectures
  concordaient systématiquement), mais le garde-fou reste en place.

---

## 7. Fichiers écrits ou modifiés

```
recetter-moteur.sh                                script principal, racine du dépôt
recetter-moteur-vik.sh                             parcours VikBooking réel, sourcé par le premier
mu-plugins/lme-mail-guard/includes/core.php        + transcription d'enveloppe (fonctions pures)
mu-plugins/lme-mail-guard/includes/guard.php       + activation de la transcription
mu-plugins/lme-mail-guard/lme-mail-guard.php       docblock mise à jour
mu-plugins/lme-mail-guard/tests/test-core.php      + 23 tests
```

Déployé sur `staging13.linstantcle.ch` par `deployer-moteur.sh` (les trois
fichiers de `lme-mail-guard` ci-dessus) ; `recetter-moteur.sh` et
`recetter-moteur-vik.sh` ne sont pas des fichiers déployés — ils tournent
depuis le poste de travail, contre le serveur, par SSH et HTTPS.

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.2 | 2026-09-24 | Chapitre 3.1, tâche B11 : cause établie par trace d'exécution et corrigée, vérification 2 refaite sur les identifiants de chambre du conteneur Vik. Renvoi vers `constat-correctif-room-filter.md`. |
| 1.1 | 2026-09-24 | Chapitre 3.2 bis, tâche B9h : le « 200 sur `/fr/reserver/` » de la passe L'Instant Clé venait du verrou temporaire de Vik (`VBROOMBOOKEDBYOTHER`), posé par la réservation d'essai de la passe Sexcape Room sur la même chambre 4 et à la même date. Il est antérieur à la garde, qui fonctionne dans les deux sens, prouvé par rejeu. Script corrigé : dates séparées, refus natif de Vik lu et distingué, 3a qui passe au jour suivant. 3.2 corrigé : le refus de `showSelectVb()` n'est pas muet. #1830 et #1831 sont `confirmed`. Une réservation d'essai de plus, #1832. |
| 1.0 | 2026-09-23 | Création. Transcription d'enveloppe écrite et déployée sur `staging13.linstantcle.ch` (70 tests, 0 échec). `recetter-moteur.sh`/`recetter-moteur-vik.sh` écrits puis exécutés pour de vrai à plusieurs reprises contre cette préproduction, corrigeant en cours de route quatre défauts (deux dans le script, chapitre 4 ; un dans la configuration attendue de la recette, chapitre 3.3 ; un défaut de méthode de test sur la vérification 2, l'encodage d'apostrophe). Un vrai défaut de `mu-plugins/lme-brands/includes/room-filter.php` trouvé et documenté (chapitre 3.1), non corrigé ici. Deux réservations de test laissées en `standby` sur `staging13.linstantcle.ch`, non annulables par ce script tant que `skipbtn` n'est pas changé (chapitre 3.3). |
