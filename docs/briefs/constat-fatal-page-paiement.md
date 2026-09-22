# Constat — erreur fatale à l'affichage de la page de paiement

22 septembre 2026. Réponse au chapitre 3 de
`brief-correctif-levier-et-fatal-paiement.md`. Lecture seule sur le serveur
(SSH, `wp-cli`, lecture directe de `debug.log`), rien de déployé, aucune clé
lue, aucune écriture en base.

---

## 1. La trace

Réservation de test le 21 septembre 2026 à 14 h 38 (heure serveur, UTC) :
Le Boudoir du Désir (chambre Vik #4, Sexcape Room), une nuit (21→22
septembre), deux adultes, tarif 253.30 CHF, sur `staging13.linstantcle.ch`.

`wp-content/debug.log` de la préproduction, à `14:38:05 UTC` :

```
[21-Sep-2026 14:38:05 UTC] PHP Fatal error:  Uncaught Error: Cannot use object of type VikBookingStripePayment as array in /home/customer/www/staging13.linstantcle.ch/public_html/wp-content/mu-plugins/lme-brands/includes/payment-brand.php:73
Stack trace:
#0 .../wp-includes/class-wp-hook.php(353): lme_brands_brand_payment_transaction(Object(VikBookingStripePayment))
#1 .../wp-includes/class-wp-hook.php(377): WP_Hook->apply_filters('', Array)
#2 .../wp-includes/plugin.php(523): WP_Hook->do_action(Array)
#3 .../wp-content/plugins/vikbooking/libraries/adapter/payment/payment.php(329): do_action('payment_before_...', Array)
#4 .../wp-content/plugins/vikbooking/site/views/booking/tmpl/default.php(1580): JPayment->showPayment()
#5 .../wp-content/plugins/vikbooking/libraries/adapter/mvc/view.php(204): include(...)
#6 .../wp-content/plugins/vikbooking/libraries/adapter/mvc/view.php(106): JView->loadTemplate(NULL)
#7 .../wp-content/plugins/vikbooking/site/views/booking/view.html.php(457): JView->display(NULL)
#8 .../wp-content/plugins/vikbooking/libraries/adapter/mvc/controller.php(266): VikbookingViewBooking->display()
#9 .../wp-content/plugins/vikbooking/site/controller.php(44): JController->display()
#10 .../wp-content/plugins/vikbooking/libraries/adapter/mvc/controller.php(337): JController->execute('display')
#11 .../wp-content/plugins/vikbooking/libraries/system/body.php(55): VikBookingController->execute('display')
#12 .../wp-content/plugins/vikbooking/vikbooking.php(197): VikBookingBody::process()
#13 .../wp-includes/class-wp-hook.php(353): {closure}('')
#14 .../wp-includes/class-wp-hook.php(377): WP_Hook->apply_filters(NULL, Array)
#15 .../wp-includes/plugin.php(523): WP_Hook->do_action(Array)
#16 .../wp-settings.php(779): do_action('init')
#17 .../wp-config.php(119): require_once(...)
#18 .../wp-load.php(50): require_once(...)
#19 .../wp-blog-header.php(13): require_once(...)
#20 .../index.php(17): require(...)
#21 {main}
  thrown in .../wp-content/mu-plugins/lme-brands/includes/payment-brand.php on line 73
```

Fichier et ligne fautifs : `mu-plugins/lme-brands/includes/payment-brand.php:73`, dans
`lme_brands_brand_payment_transaction()`, première ligne du corps de la fonction :

```php
$payment = isset( $args[0] ) ? $args[0] : null;
```

Le suspect du brief se confirme, mais pas comme prévu : ce n'est pas une
erreur *dans* la logique de résolution de marque, c'est un plantage à la
toute première ligne, avant que `payment-brand.php` n'ait rien lu ni
décidé — avant même le test `isDriver('stripe')` de la ligne suivante.

---

## 2. Ce qui casse, précisément

Le commentaire de tête de `payment-brand.php` (posé lors de la phase 4,
17 septembre 2026) affirme :

> L'appel est `do_action($hook, array(&$this))` : le rappel reçoit un
> tableau dont l'indice 0 est l'objet de paiement, pas l'objet
> directement — piège relevé dans ce même constat [constat-phase-0.md Q5].

Cette lecture de `payment.php:329` (`do_action($this->getHook('payment_before_begin_transaction'), array(&$this));`)
est correcte à la lettre du code de Vik. Mais elle ne tient pas compte
d'une deuxième couche, dans **WordPress lui-même** : `do_action()`
(`wp-includes/plugin.php`) déballe automatiquement ce motif, pour une
raison de compatibilité ascendante documentée dans son propre code source.
Lu directement sur `staging13.linstantcle.ch` :

```php
// wp-includes/plugin.php, dans do_action() :
if ( empty( $arg ) ) {
    $arg[] = '';
} elseif ( is_array( $arg[0] ) && 1 === count( $arg[0] ) && isset( $arg[0][0] ) && is_object( $arg[0][0] ) ) {
    // Backward compatibility for PHP4-style passing of `array( &$this )` as action `$arg`.
    $arg[0] = $arg[0][0];
}
```

Quand un `do_action()` reçoit un tableau à un seul élément qui est un objet
(exactement `array(&$this)`), WordPress le déballe avant d'appeler les
fonctions accrochées : le rappel reçoit **l'objet directement**, jamais le
tableau. La pile le confirme à la ligne 0 :
`lme_brands_brand_payment_transaction(Object(VikBookingStripePayment))` — un
objet, pas un tableau.

`isset( $args[0] )` sur un objet qui n'implémente pas `ArrayAccess` est un
accès de type tableau sur un objet. Sous PHP 8 (le serveur tourne en
**PHP 8.2.33**, vérifié par `php -v`), ceci lève une `Error` fatale — « Cannot
use object of type X as array » — au lieu du comportement silencieux de
PHP 7. C'est exactement le message obtenu.

**Ce constat corrige donc celui de la phase 4** (`constat-phase-0.md` Q5,
repris dans l'en-tête de `payment-brand.php`) : la lecture de la source de
Vik seule ne suffisait pas à prédire le comportement réel du hook, parce
que WordPress réécrit l'argument avant qu'il n'atteigne le greffon. Aucune
lecture ciblée d'un seul fichier ne pouvait le montrer ; il a fallu
l'erreur réelle, sa pile, et une lecture du code source de WordPress
lui-même pour l'établir.

---

## 3. La question qui décide de la portée : production ou préproduction ?

**Cette erreur toucherait la production de façon identique.** Rien dans sa
cause n'a de rapport avec le levier `LME_BRANDS_HOST_OVERRIDE`, la
résolution de marque, ou un état que seule la préproduction produit.

Preuve, par lecture directe :

- Le mécanisme fautif est dans `wp-includes/plugin.php`, un fichier du
  **cœur de WordPress**, pas de `lme-brands`, pas du thème, pas de Vik.
  Vérifié identique sur les deux installations :

  ```
  $ ssh sg-linstantcle "md5sum .../staging13.linstantcle.ch/.../wp-includes/plugin.php \
                             .../linstantcle.ch/.../wp-includes/plugin.php"
  c17fdf89bc51e2f177c2137b1b49100e  staging13.linstantcle.ch/.../plugin.php
  43f29c584b10193f376d5293683283a8  linstantcle.ch/.../plugin.php
  ```

  Les empreintes diffèrent, mais seulement à cause d'un écart de version
  mineure de WordPress lui-même (`7.1` en préproduction contre `7.1.1` en
  production, `wp-includes/version.php`). Le bloc de compatibilité
  ascendante en cause (« Backward compatibility for PHP4-style passing »)
  est **identique, ligne pour ligne**, sur les deux installations — relu
  directement sur chacune, pas supposé.
- `payment.php:329` (Vik Booking) fait le même appel
  `do_action($this->getHook(...), array(&$this))` quel que soit l'hôte : ce
  n'est pas un code conditionné par une marque ou un environnement, c'est
  la méthode `showPayment()` du cœur de l'adaptateur de paiement, commune
  aux deux marques et aux deux environnements.
- `payment-brand.php:73` ne lit ni `$_SERVER['HTTP_HOST']`, ni
  `LME_BRANDS_HOST_OVERRIDE`, ni `wp_get_environment_type()` avant de
  planter : le plantage a lieu avant toute résolution de marque, avant même
  de savoir si la passerelle est Stripe.
- Les lignes de journal `[host_override_used]` qui entourent l'incident
  dans `debug.log` (14 h 35 à 14 h 38) sont un artefact normal de la
  recette — le levier était actif pendant toute la session, comme prévu
  au chapitre 1 du brief — et sont **sans lien causal** avec le plantage :
  aucune d'elles ne précède immédiatement l'erreur de façon particulière,
  et le mécanisme identifié ci-dessus n'y fait aucune référence.

**Conséquence.** N'importe quelle tentative de paiement Stripe, sur
`linstantcle.ch` ou sur `reservation.sexcaperoom.ch`, en production comme
en préproduction, déclenchera la même erreur fatale au même endroit, dès
que `JPayment::showPayment()` s'exécute — c'est-à-dire dès qu'un client
clique sur « Payer ». Plus précisément encore : le plantage de la ligne 73
a lieu **avant** le test `isDriver('stripe')` de la ligne 75, donc il
frapperait de la même façon avec n'importe quelle passerelle de paiement
publiée à l'avenir, pas seulement Stripe — Stripe est simplement la seule
passerelle publiée aujourd'hui (`constat-reserve-paiement.md` §2).

**Ce n'est pas un défaut du levier de préproduction B8. C'est un défaut de
`payment-brand.php` lui-même, qui casse le paiement des deux marques, sur
les deux environnements, dès que ce mu-plugin est actif.** Aucun correctif
n'est apporté ici : le brief demande un constat pour ce sujet, pas un
correctif — voir §5.

---

## 4. Ce que l'échec a laissé derrière

Relu par `wp-cli` sur `staging13.linstantcle.ch`, avec les identifiants du
site lui-même (jamais un accès externe, jamais une clé lue — voir
`handoff-acces-mysql.md` et `constat-script-deploiement.md` §3.2 pour la
raison de ce choix).

### Commande en standby

```
$ wp db query "SELECT id, ts, status, total, roomsnum, custmail, idpayment, paymentlog, payable
               FROM sir_vikbooking_orders WHERE id = 1822"
id    ts          status   total   roomsnum  custmail                idpayment                paymentlog  payable
1822  1790001484  standby  253.30  1         cark.inthony@gmail.com  3=Pay (now or later)       NULL        0.00

$ wp db query "SELECT idorder, idroom, adults, children, t_first_name, t_last_name, room_cost
               FROM sir_vikbooking_ordersrooms WHERE idorder = 1822"
idorder  idroom  adults  children  t_first_name  t_last_name  room_cost
1822     4       2       0         TEST          TEST         253.30
```

La commande **#1822** existe (créée avant l'affichage de la page de
paiement, donc avant le plantage — l'insertion de la commande et le rendu
de la page de paiement sont deux étapes distinctes de Vik), porte la
chambre #4 (Le Boudoir du Désir, Sexcape Room, conforme à l'observation),
et reste au statut **`standby`** : « Pay (now or later) » choisi, jamais
payée (`payable = 0.00`).

Dates de séjour : `checkin` 2026-09-21 15:00 UTC, `checkout` 2026-09-22
11:00 UTC (relu au moment de ce constat : la nuit réservée touche déjà à
sa fin, mais la commande n'a été ni annulée ni supprimée entre-temps).

### Aucune session Stripe créée

`paymentlog` de la commande #1822 est `NULL` : aucune trace d'un aller-retour
avec Stripe. Ce n'est pas une supposition — c'est démontré par le code
lui-même : le plantage a lieu à la ligne 73 de `payment-brand.php`, accroché
sur `payment_before_begin_transaction_vikbooking`, qui se déclenche à la
ligne **329** de `JPayment::showPayment()` — **avant** la ligne 345,
`$this->beginTransaction()`, la méthode qui construit et envoie la requête
de création de session à l'API Stripe (`stripe.php:520` et suivants, greffon
VikStripe). L'exécution PHP s'arrête net à la ligne 73 : aucune ligne
postérieure de `showPayment()`, y compris `beginTransaction()`, ne s'exécute.

**Aucune session Stripe Checkout n'a donc été créée, en clés de test ou
autrement. Rien à annuler côté Stripe.**

### Chambre verrouillée

La commande #1822 existe toujours, dans un statut qui n'est ni annulé ni
expiré (`standby`), pour la chambre #4 sur la nuit du 21 au 22 septembre.
Tant qu'elle reste dans cet état, elle occupe cette chambre pour cette
période aux yeux de Vik Booking (c'est le mécanisme même que
`includes/booking-guard.php` de ce mu-plugin s'appuie sur : une chambre
avec une commande active sur une période donnée n'est plus disponible pour
cette période). La nuit réservée est déjà quasiment passée au moment de ce
constat (22 septembre, 10 h 10 UTC, contre un `checkout` à 11 h 00 UTC le
même jour), ce qui limite l'impact pratique restant, mais la commande de
test elle-même reste ouverte dans Vik.

**Comment la libérer — jamais par SQL, comme le veut la section « Chantier
en cours » de CLAUDE.md ni ce constat.** Thomas ouvre l'écran des
réservations de Vik Booking dans l'administration WordPress de
`staging13.linstantcle.ch` (`wp-admin` → VikBooking → Reservations),
retrouve la commande **#1822** (Le Boudoir du Désir, TEST TEST,
`cark.inthony@gmail.com`), et utilise l'action native de Vik pour
l'annuler ou la supprimer. C'est le contrôleur de Vik qui doit faire ce
geste : lui seul recalcule correctement la disponibilité, les éventuelles
notifications et la cohérence des tables liées (`sir_vikbooking_ordersrooms`
comprise) — une suppression directe en base laisserait ces tables
incohérentes entre elles.

---

## 5. Ce que ce constat ne fait pas

Conformément au brief : **aucun correctif n'est apporté à
`payment-brand.php`** dans cette passe — seul le second sujet du brief (la
réécriture d'URL) porte un correctif, dans `docs/briefs/constat-correctif-url-rewrite.md`.
Ce constat n'a touché ni la base de données (lecture seule via `wp-cli`,
avec les identifiants du site lui-même, jamais un accès externe ni une
clé), ni aucun fichier déployé sur le serveur.

**Ce que ce constat établit, et qui devra guider une décision de Thomas
avant tout déploiement de ce mu-plugin en production** : `payment-brand.php`
casse aujourd'hui le paiement de toute réservation, sur les deux marques,
partout où il est actif — pas seulement la Sexcape Room, pas seulement la
préproduction. Ce n'est pas un défaut mineur à corriger « à l'occasion » ;
c'est bloquant pour tout déploiement de la phase 4 (paiement par marque) en
l'état, staging comme production.

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-22 | Création. Trace établie dans `debug.log`, cause identifiée dans le mécanisme de compatibilité ascendante de `do_action()` (cœur WordPress, vérifié identique sur les deux environnements), portée établie (production touchée à l'identique, sans lien avec le levier B8), état laissé derrière relevé (commande #1822 en standby, aucune session Stripe créée, libération par le contrôleur de Vik décrite). |
