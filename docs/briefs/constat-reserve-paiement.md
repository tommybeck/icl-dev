# Constat — la réserve de paiement

**Oui : sur cette installation, un client peut être débité par Stripe sans que sa réservation soit
jamais confirmée, parce que le seul code qui confirme une commande s'exécute dans la requête de
retour du navigateur, et que rien — ni greffon, ni cœur, ni tâche planifiée — ne rattrape ce
retour s'il n'a pas lieu.**

Version 1, 15 septembre 2026. Lecture seule : aucun fichier de plugin, aucun réglage Stripe,
aucune ligne de base n'a été modifiée. Aucun code n'a été écrit, rien n'a été déployé.

Réponse au prompt du 15 septembre 2026. Documents liés : `constat-phase-0.md` §Q5,
`constat-perimetre-tunnel.md` §F, `sexcape-room-reservation.md` §4.1 et §4.4,
`handoff-acces-mysql.md`.

**Ce que ce document établit :** l'état d'une commande avant le paiement, le code exact qui la
confirme, la fenêtre pendant laquelle un encaissement peut rester sans contrepartie, et l'ampleur
mesurable du phénomène sur cette installation. **Ce qu'il n'établit pas :** combien de ces
réservations ont réellement été débitées. Cette réponse-là ne vit que chez Stripe, et la lire
demande la clé secrète — voir §9.

---

## Sources et méthode

| Source | Ce qui en a été tiré |
|---|---|
| `.local/wp-vikstripe/` (VikStripe **2.2.4**) | création de session, validation, redirection de retour |
| `.local/vikbooking/` (Vik Booking **1.8.14**) | création de commande, `notifypayment`, crochets de paiement |
| `ssh sg-linstantcle`, arborescence et empreintes SHA-256 | comparaison copie locale / production |
| MySQL `dbvkhvlostfyua`, lecture seule via `ssh sg-linstantcle` | `sir_vikbooking_orders`, `sir_vikbooking_gpayments`, `sir_vikbooking_config`, `sir_vikbooking_cronjobs`, `sir_vikbooking_payschedules`, `sir_options` |
| Fichiers `Stripe/*.tx` du greffon, en local et en production | URL de notification réellement émises, 110 relevés |

Toutes les lignes citées ont été relues une à une dans le fichier, pas de mémoire. Quand la copie
locale et la production diffèrent, le numéro de ligne de **production** est donné et signalé.

---

## 0. Confirmation de version, demandée avant toute lecture

### La version, à sa source

```
.local/wp-vikstripe/vikstripe.php:5    Version:      2.2.4
.local/wp-vikstripe/vikstripe.php:21   define('VIKSTRIPEVERSION', '2.2.4');
```

**C'est bien la 2.2.4.** L'en-tête du greffon et la constante de version concordent.

### La copie restaurée est, à l'octet près, celle qui tourne en production

Le remplacement puis la restauration du 15 septembre 2026 n'ont rien laissé derrière eux.
Empreintes SHA-256 comparées entre `.local/wp-vikstripe/` et
`~/www/linstantcle.ch/public_html/wp-content/plugins/wp-vikstripe/` :

| Fichier | Empreinte (locale = production) |
|---|---|
| `vikstripe.php` | `82801169c4ffb5ec324ffd7527a68dc1be2826f92887e84b1e6482ec8e435776` |
| `stripe.php` | `4022d501ad34bf05c5c8f27025a8d03ed7312dbac0d72092d15c5cf6f3d9437f` |
| `vikbooking/stripe.php` | `abccaeea3ac07db1f862a909df0b1fc6a4a240417a52330363c71c276b570839` |

La production déclare elle aussi `Version: 2.2.4` et `VIKSTRIPEVERSION = '2.2.4'`. **Rien n'a
bougé, la relecture des deux constats reste valable et n'a pas à être refaite.**

### Les lignes citées par les deux constats pointent toujours le bon code

Vérification ligne à ligne, dans l'ordre où les constats les citent.

| Citation | Statut | Ce qui s'y trouve réellement |
|---|---|---|
| `wp-vikstripe/stripe.php:399-401` | **exacte** | `'submit_type'`, `'success_url' => $this->get('notify_url')`, `'cancel_url' => $this->get('return_url') . "&payment=canceled"` |
| `wp-vikstripe/stripe.php:447-450` | **exacte** | `'mode' => 'setup'`, `'currency'`, `'success_url' => notify_url`, `'cancel_url' => return_url` |
| `wp-vikstripe/stripe.php:400` et `:449` (§F) | **exactes** | les deux `success_url` |
| `wp-vikstripe/stripe.php:401` (§F) | **exacte** | le `cancel_url` |
| `wp-vikstripe/stripe.php:667-688` | **exacte** | `complete($res)`, `return_url` si succès, `error_url` sinon, `redirect()` puis `exit` |
| `wp-vikstripe/vikbooking/stripe.php:223-242` | **exacte** | le crochet `payment_on_after_validation_vikbooking`, jusqu'au `}, 10, 2);` de la ligne 242 |
| `vikbooking/libraries/adapter/payment/payment.php:512`, `:524`, `:527` | **exactes** | les deux `do_action_ref_array` puis `$this->complete($res)` |
| `vikbooking/libraries/adapter/payment/payment.php:329` | **exacte** | `do_action($this->getHook('payment_before_begin_transaction'), array(&$this));` |
| `vikbooking/libraries/adapter/payment/payment.php:66`, `:147-150`, `:160` | **exactes** | `$this->order = new JObject($order)`, `get()`, `set()` |
| `vikbooking/libraries/adapter/uri/uri.php:119-125`, `:123-126`, `:140-147` | **exactes** | `base()`, la mémorisation dans `static::$base[$sign]`, `root()` |
| `vikbooking/site/controller.php:2173-2175` | **exacte** | les trois URL du chemin de validation |
| `vikbooking/site/views/booking/tmpl/default.php:928-930`, `:937-942`, `:981-983`, `:1041` | **exactes quant au contenu**, mais **ce n'est pas la branche empruntée** — voir ci-dessous |
| `vikbooking/site/controller.php:2175-2182` (§F) | **exacte**, à une précision près : le routage par `JRoute::_()` est aux lignes `2183-2185`, hors de la plage citée |

### Trois écarts relevés, dont un qui corrige les deux constats

**Écart 1 — le bloc cité dans `booking/tmpl/default.php` n'est pas celui qu'emprunte un premier
paiement. C'est une correction à apporter aux deux constats.**

Les lignes 928-942 citées par Q5, et 930-942 citées par §F, vivent dans un bloc gardé par :

```
.local/vikbooking/site/views/booking/tmpl/default.php:919
    if ($ord['status'] == 'confirmed' && is_array($payment) && VikBooking::multiplePayments() && $ord['total'] > 0 && $payable) {
        // write again the payment form because the order was not fully paid
```

C'est le **second** formulaire de paiement, celui d'un solde restant sur une réservation **déjà
confirmée**. Le premier paiement d'un client, celui du tunnel, passe par un autre bloc :

```
.local/vikbooking/site/views/booking/tmpl/default.php:1254-1255
    // stand-by booking payment rendering
    if (is_array($payment) && $ord['status'] == 'standby') {
.local/vikbooking/site/views/booking/tmpl/default.php:1266-1268
    $return_url = JUri::root() . "index.php?option=com_vikbooking&view=booking&sid=" … "&lang=" . $langtag;
    $error_url  = JUri::root() . "index.php?option=com_vikbooking&view=booking&sid=" … "&lang=" . $langtag;
    $notify_url = JUri::root() . "index.php?option=com_vikbooking&task=notifypayment&sid=" … "&lang=" . $langtag . "&tmpl=component";
.local/vikbooking/site/views/booking/tmpl/default.php:1278-1280
    $return_url = JRoute::_($return_url . "&Itemid={$itemid}", false);
    …
.local/vikbooking/site/views/booking/tmpl/default.php:1578-1580
    $obj = JPaymentDispatcher::getInstance('vikbooking', $payment['file'], $array_order, $payment['params']);
    echo $obj->showPayment();
```

La différence n'est pas cosmétique : le bloc « stand-by » ajoute **`&lang=<xx>`** aux trois URL.
La preuve que c'est bien ce bloc qui sert est dans les fichiers laissés par le greffon lui-même :
les **110** fichiers `Stripe/*.tx` enregistrent l'URL de notification telle qu'elle a été
construite, et **les 110 portent `lang=`** (109 `lang=fr`, 1 `lang=de`), ce que le bloc des lignes
928-942 ne produit jamais.

```
.local/wp-vikstripe/Stripe/1010720199-1658.tx
194.65^^https://linstantcle.ch/fr/your-booking-detail/?task=notifypayment&sid=1010720199&ts=1779346995&lang=fr&tmpl=component
```

**Ce que cela change, et ce que cela ne change pas.** Les conclusions de Q5 et de §F tiennent
toutes : la construction est identique mot pour mot, elle descend sur le même `JUri::root()` donc
sur `home_url()`, elle est routée par le même `JRoute::_()`, et le filtrage `option_home` la
couvre exactement pareil. Seul le numéro de ligne et le paramètre `lang` changent. **Mais le §F
en tire une conséquence de recette qu'il faut corriger : la page 845 doit aussi accepter
`&lang=fr` sur `task=notifypayment`.** Un verrou par identifiant de page l'accepte sans rien
déclarer, comme le §F le recommandait déjà ; un verrou par chaîne exacte, non.

Il existe un troisième bloc, pour la caution payée à part (`default.php:1603` et `:1611`, avec
`&dd=1`), gardé lui aussi par `status == 'confirmed'`. Il n'est pas sur le chemin du tunnel.

**Écart 2 — le journal des versions du greffon s'arrête à la 2.2.3.** `changelog.md` ne va pas
au-delà de « ## 2.2.3 — *Release date - 17 July 2025* », alors que l'en-tête et la constante
disent 2.2.4. L'éditeur n'a pas tenu son journal à jour. Sans effet sur le code, mais cela veut
dire qu'**on ne dispose d'aucune description de ce que la 2.2.4 a changé**.

**Écart 3 — le §F nomme un seul fichier de webhook là où il y en a quatre.** La recherche de
`webhook` remonte `Stripe/lib/Webhook.php`, `Stripe/lib/WebhookEndpoint.php`,
`Stripe/lib/WebhookSignature.php` et `Stripe/lib/Service/WebhookEndpointService.php`. Tous les
quatre sont chargés par l'amorce de la bibliothèque officielle (`Stripe/Stripe.php:308`, `:351`,
`:361-362`) et **aucun n'est jamais appelé par le greffon**. La conclusion du §F est donc juste et
même plus solide qu'écrit : **aucun point de terminaison de webhook Stripe n'existe sur cette
installation.** Vérifié aussi qu'aucune route n'est enregistrée : pas de `register_rest_route`,
pas de `admin_post_*`, pas de `wp_ajax_*` dans tout le greffon. Le seul `add_action('init')` du
greffon (`vikstripe.php:23`) charge les traductions.

### Un écart sur la copie du cœur, sans effet sur les citations

Le greffon Stripe est à jour, mais la copie locale de **Vik Booking l'est moins que la
production** : `.local/vikbooking/vikbooking.php:6` dit `1.8.14`, la production dit `1.8.15`.
Comparaison complète des 1 096 fichiers `.php` présents des deux côtés : **26 diffèrent**, 4
fichiers existent seulement en production. Parmi les fichiers que ce constat cite, deux sont
concernés :

- `site/views/booking/tmpl/default.php` : une seule ligne diffère, la `1084` (arrondi d'une
  commission OTA), remplacée par une autre ligne unique. **Aucun décalage de numérotation** : les
  lignes 919, 1254-1286, 1578-1580 citées ici sont identiques en production.
- `admin/helpers/src/model/reservation.php` : **16 blocs de différences**, dont une insertion de
  44 lignes après la `1059`, et plusieurs à l'intérieur même de `setConfirmed()`. Le décalage
  n'est donc **pas uniforme** et une conversion arithmétique des numéros serait fausse. Les lignes
  citées plus bas dans ce constat le sont en **numérotation de production**, relevées directement
  dans le fichier du serveur. Vérifié que les changements de la 1.8.15 dans `setConfirmed()`
  portent sur l'occupation de plusieurs unités d'une même chambre lors d'une re-confirmation de
  fermeture, et **jamais sur le paiement** : `totpaid`, `paymcount` et `payable` n'apparaissent
  nulle part dans cette méthode, en production comme en local.

`site/controller.php`, `libraries/adapter/payment/payment.php`, `libraries/adapter/uri/uri.php`,
`admin/controller.php` et `admin/helpers/src/model/payschedules.php` sont **identiques** entre la
copie locale et la production : leurs citations valent telles quelles.

`.local/README.md` annonce toujours « version 1.8.14 » ; c'est à corriger par Thomas, `.local/`
étant en lecture seule pour Claude Code.

---

## 1. Le chemin réel, dans l'ordre d'exécution

Ce que fait le tunnel, de la validation du formulaire à l'état final de la commande. Chaque étape
est citée.

| # | Étape | Où | Ce qui se passe |
|---|---|---|---|
| 1 | `task=saveorder` | `site/controller.php:1478-1514` | la commande est **insérée en `standby`** |
| 2 | verrou temporaire | `site/controller.php:1583`, `:1595-1603` | une ligne `#__vikbooking_tmplock` par chambre, expirant à `now + minuteslock` |
| 3 | e-mails « en attente » | `site/controller.php:1615` | client **et** administrateur sont notifiés, réservation non confirmée |
| 4 | redirection | `site/controller.php:1642-1652` | vers `view=booking&sid=…&ts=…`, la page 845 |
| 5 | rendu du paiement | `site/views/booking/tmpl/default.php:1255`, `:1266-1280`, `:1578-1580` | les trois URL sont construites puis remises à la passerelle |
| 6 | session Stripe | `wp-vikstripe/stripe.php:497` | `$stripe->checkout->sessions->create($config)` |
| 7 | trace de session | `wp-vikstripe/stripe.php:505` | `add_option("stripe_order_<oid>", $session->id)` — **la seule trace durable** |
| 8 | départ du client | `wp-vikstripe/stripe.php:540`, `wp-vikstripe/tmpl/success.html.php:1-2` | bouton « Pay Now » pointant `$checkout_session['url']` |
| 9 | **le client paie chez Stripe** | — | **à partir d'ici, l'argent est encaissé** |
| 10 | retour | Stripe renvoie le navigateur sur `success_url`, c'est-à-dire `notify_url` (`stripe.php:400`) | **c'est la seule notification qui existe** |
| 11 | `task=notifypayment` | `site/controller.php:1825` | charge la commande, refuse si déjà confirmée (`:1887-1893`) |
| 12 | validation | `site/controller.php:2217` → `payment.php:403-430` → `stripe.php:577-655` | interroge Stripe avec l'identifiant de session lu dans `wp_options` |
| 13 | **confirmation** | `site/controller.php:2398`, écrite `:2409` | `status = 'confirmed'`, `totpaid`, `paymcount`, `payable` |
| 14 | occupation réelle | `site/controller.php:2374-2382` | insertion dans `#__vikbooking_busy` |
| 15 | libération du verrou | `site/controller.php:2452-2454` | `DELETE FROM #__vikbooking_tmplock` |
| 16 | e-mails de confirmation | `site/controller.php:2486` | `sendBookingEmail(…, ['guest','admin'])` |
| 17 | fin | `site/controller.php:2552-2554` → `payment.php:512` → `wp-vikstripe/vikbooking/stripe.php:223-242` | le greffon redirige vers la vue `booking` et `exit` |

**Le point qui décide de tout : les étapes 11 à 16 vivent entièrement dans la requête HTTP
déclenchée par le navigateur du client à l'étape 10.** Pas avant, pas ailleurs.

---

## 2. Q1 — la commande existe-t-elle déjà quand le client part chez Stripe, et dans quel état ?

**Oui, elle existe. Elle est en `standby`, sans paiement enregistré, et sa chambre n'est retenue
que par un verrou de vingt minutes.**

La branche empruntée est le `else` de la condition de confirmation immédiate :

```
.local/vikbooking/site/controller.php:1051
    if (!$mod_booking && ((!empty($payment) && intval($payment['setconfirmed']) == 1) || !$must_payment || ($usedcoupon && $isdue <= 0))) {
.local/vikbooking/site/controller.php:1478-1483
    } else {
        // booking must have status stand-by and proceed to the payment
        $booking_record = new stdClass;
        $booking_record->custdata = $custdata;
        $booking_record->ts = $nowts;
        $booking_record->status = 'standby';
.local/vikbooking/site/controller.php:1514
        $dbo->insertObject('#__vikbooking_orders', $booking_record, 'id');
```

Le `setconfirmed` de la passerelle décide de la branche. Relevé en base, sur la seule passerelle
publiée :

```
SELECT id, name, file, published, setconfirmed FROM sir_vikbooking_gpayments;
 3 | Pay (now or later)          | stripe        | 1 | 0
 4 | PayPal                      | paypal        | 0 | 0
 5 | Twint, Bank transfer        | bank_transfer | 0 | 1
 6 | Stripe - Keep it for tests  | stripe        | 0 | 0
```

**Une seule passerelle est publiée, c'est Stripe, et son `setconfirmed` vaut `0`.** Toute
réservation du tunnel passe donc par la branche `standby`. La commande est écrite avec `totpaid`
nul, `paymcount` à zéro, `payable` à zéro et `paymentlog` vide.

Aucune ligne n'est écrite dans `#__vikbooking_busy` à ce stade : l'occupation réelle n'arrive qu'à
l'étape 14, dans la requête de retour. La chambre n'est tenue que par `#__vikbooking_tmplock` :

```
.local/vikbooking/site/controller.php:1583
    $lock_until_ts = VikBooking::getMinutesLock(true);
.local/vikbooking/site/controller.php:1595-1603
    $tmp_lock_record->until = $lock_until_ts;
    …
    $dbo->insertObject('#__vikbooking_tmplock', $tmp_lock_record, 'id');
.local/vikbooking/site/helpers/lib.vikbooking.php:3649-3651
    if ($conv) { return (time() + ($minutesLock * 60)); }
```

Relevé en base : `SELECT * FROM sir_vikbooking_config WHERE param='minuteslock'` → **`20`**.
`paytotal` vaut `1`, donc le montant dû est le total, pas un acompte.

**La chambre est donc retenue vingt minutes, puis relâchée, alors que la commande, elle, reste en
`standby` indéfiniment.** Ce n'est pas une hypothèse : un processus la relâche activement, voir §6.

---

## 3. Q2 — quel code fait passer la commande à `confirmed`, et s'exécute-t-il ailleurs que dans la requête de retour ?

**Un seul endroit dans tout le site public, et il ne s'exécute nulle part ailleurs que dans la
requête de retour du navigateur.**

```
.local/vikbooking/site/controller.php:2251
    if ($array_result['verified'] == 1) {
.local/vikbooking/site/controller.php:2396-2398
        $booking_record = new stdClass;
        $booking_record->id = $row['id'];
        $booking_record->status = 'confirmed';
.local/vikbooking/site/controller.php:2409
        if (!$dbo->updateObject('#__vikbooking_orders', $booking_record, 'id')) {
```

Ces lignes sont dans le corps de `public function notifypayment()`
(`site/controller.php:1825`). Balayage complet du plugin pour toute autre écriture de ce statut :

```
grep -rn -- "->status = '" admin/ site/
    admin/helpers/availability.php:2166   $res_obj->status = 'standby';
    site/controller.php:1085              $booking_record->status = 'confirmed';   ← branche setconfirmed=1, inatteignable ici
    site/controller.php:1483              $booking_record->status = 'standby';
    site/controller.php:2398              $booking_record->status = 'confirmed';   ← la seule qui compte

grep -rn "SET \`status\`" admin/ site/
    admin/controller.php:3265             …SET `status`='cancelled'…
    admin/controller.php:7859             …SET `status`='cancelled'…
    admin/controllers/bookings.php:572    …SET `status`='standby'…
    site/controller.php:2833              …SET `status`='cancelled'…
    site/views/booking/view.html.php:182  …SET `status`='cancelled'…
```

Il existe une seconde écriture de `confirmed`, en administration seulement, par le modèle de
réservation (**numérotation de production, 1.8.15**) :

```
vikbooking/admin/helpers/src/model/reservation.php:1776   public function setConfirmed(array $options)
vikbooking/admin/helpers/src/model/reservation.php:1937   ->set($dbo->qn('status') . ' = ' . $dbo->q('confirmed'))
```

Son unique appelant est une action manuelle du back-office :

```
.local/vikbooking/admin/controller.php:3939   public function setordconfirmed()
.local/vikbooking/admin/controller.php:3954   $confirmed = $model->setConfirmed([…]);
```

déclenchée par le bouton **Set as confirmed** de l'écran *Bookings → Edit booking*, affiché
uniquement pour une réservation en `standby` ou annulée
(`admin/views/editorder/tmpl/default.php:1209-1213`). **Ce chemin ne consulte jamais Stripe et
n'écrit jamais `totpaid` :** l'update des lignes `1935-1944` ne touche que `status` et,
éventuellement, `adminnotes` (`:1937` et `:1941`), et les mots `totpaid`, `paymcount` et `payable`
n'apparaissent nulle part dans cette méthode. Une réservation confirmée à la main apparaît donc
comme confirmée et **impayée**.

**Rien d'autre ne confirme une commande.** Ni le greffon Stripe, qui n'écrit jamais dans
`#__vikbooking_orders` : sa seule écriture persistante est l'option WordPress
`stripe_order_<oid>` (`stripe.php:505`, effacée `:371`, `:376`, `:638`). Ni un webhook, puisqu'il
n'y en a pas. Ni une tâche planifiée, voir §4.

**Et `notifypayment` n'est joignable que par le navigateur.** `success_url` est l'URL sur laquelle
Stripe **redirige le navigateur** après paiement ; Stripe ne l'appelle pas de serveur à serveur.
Sans webhook, ce navigateur est le seul messager entre l'encaissement et la base.

---

## 4. Q3 — existe-t-il une tâche planifiée ou un rattrapage qui réconcilierait un encaissement avec une commande restée en attente ?

**Non. Ni dans le greffon, ni dans le cœur, ni dans ce qui est effectivement planifié sur ce
serveur.**

**Dans le greffon :** aucune planification du tout.

```
grep -rn "wp_schedule_event|wp_next_scheduled|wp_schedule_single_event|cron_schedules" .local/wp-vikstripe/
    (aucun résultat)
```

**Dans le cœur**, quatre événements WP-Cron sont posés (`.local/vikbooking/vikbooking.php:662-692`) :

| Événement | Ce qu'il fait | Réconcilie un paiement ? |
|---|---|---|
| `vikbooking_cron_payments_scheduled` | `VBOModelPayschedules::getInstance()->watch()` (`vikbooking.php:609-613`) | **Non.** Lit `#__vikbooking_payschedules` (`payschedules.php:213-230`), la file des **prélèvements programmés à la main** sur une carte enregistrée. Ne consulte jamais `#__vikbooking_orders`, ne consulte jamais Stripe pour une session. **Relevé en base : `sir_vikbooking_payschedules` contient 0 ligne.** Cette tâche ne fait rien du tout ici. |
| `vikbooking_cron_performance_cleaner` | `VBOPerformanceCleaner::runCheck()` | **Non.** Ne supprime que des lignes `#__vikbooking_busy` orphelines (`performance/cleaner.php:360-417`). Ne touche jamais une commande — la commande en `standby` n'est donc jamais effacée, elle reste. |
| `vikbooking_cron_door_access_control` | codes d'accès des serrures | Non |
| `vikbooking_cron_db_optimization` | `OPTIMIZE TABLE` | Non |

**Les tâches configurables du panneau Vik** sont dix classes livrées (`admin/cronjobs/`) :
`precheckin_reminder`, `email_reminder`, `sms_reminder`, `messaging_reminder`,
`task_manager_operators_reminder`, `invoices_generator`, `backup_creator`, `customer_discounts`,
`report_auto_exporter`, `webhook`. Aucune ne consulte une passerelle de paiement. Relevé de ce qui
est réellement installé :

```
SELECT id, cron_name, class_file, published FROM sir_vikbooking_cronjobs;
 1 | E-mail Pre-Checkin        | precheckin_reminder
 5 | Email pre check-in (new)  | email_reminder
 6 | Check-in info (old)       | email_reminder
 7 | Check-in info             | email_reminder
 8 | Check-in info (test)      | email_reminder
```

**Cinq rappels d'e-mail, rien d'autre.**

**Balayage complet des événements WP-Cron enregistrés** (`sir_options`, `option_name='cron'`,
77 créneaux) : aucun hook du greffon Stripe. Les seuls hooks Vik sont
`vikbooking_cron_db_optimization`, `vikbooking_cron_door_access_control`,
`vikbooking_cron_email_reminder_{3,4,7}`, `vikbooking_cron_payments_scheduled`,
`vikbooking_cron_performance_cleaner`, `vikbooking_cron_precheckin_reminder_2`, et côté channel
manager `vikchannelmanager_cron_chat_async_processor`,
`vikchannelmanager_cron_messaging_autoresponder`, `vikchannelmanager_cron_pending_locks`,
`vikchannelmanager_cron_schedules_retry`.

Les deux derniers ont été relus : `schedules_retry` rejoue des transmissions échouées vers les
OTA ; `pending_locks` fait **l'inverse d'un rattrapage**, voir §6.

**Le seul rattrapage qui existe est humain :** le bouton *Set as confirmed*, qui exige de savoir
qu'il y a quelque chose à rattraper. Or rien ne le signale : le §6 montre que l'administrateur
reçoit un e-mail au moment de la **création** de la réservation en attente
(`site/controller.php:1615`), et plus rien ensuite. Une réservation payée dont le retour n'a pas
eu lieu ne produit **aucune** alerte, aucune entrée de journal, aucune ligne dans `paymentlog`.

---

## 5. Les trois scénarios du prompt, un par un

Dans les trois cas, le client a payé : l'étape 9 est franchie, l'argent est chez Stripe.

| Scénario | Ce qui se passe | État final de la commande |
|---|---|---|
| **Onglet fermé après le paiement** | Stripe a répondu par une redirection vers `success_url`, le navigateur ne la suit pas. `notifypayment` n'est jamais appelé. | **`standby`, `totpaid` nul, `paymentlog` vide.** Verrou de chambre expiré au bout de 20 minutes. Aucune alerte. |
| **Réseau coupé** | Identique. La requête vers `notify_url` n'atteint pas le serveur. | Identique. |
| **Retour par le bouton précédent** | Le navigateur revient sur la page 845, qui est toujours la page de paiement d'une commande en `standby` (`default.php:1255`). Le formulaire est re-rendu. | **`standby`**, et **un second paiement est à un clic** — voir §6. |

Une précision sur le troisième : le réglage **Auto-redirect** de la passerelle vaut `1`, ce qui,
dans ce greffon, signifie **« No »** (`stripe.php:233-241` : l'option `1 => No`, `0 => Yes`) ;
la redirection automatique n'est donc **pas** posée (`stripe.php:549` : `if (!$this->getParam('skipbtn'))`).
Le client doit cliquer « PAY NOW ». C'est un clic, pas un automatisme — mais c'est un clic que la
situation invite à faire, puisque la page lui redemande de payer une réservation qu'il vient de
payer.

---

## 6. Deux aggravations relevées en chemin, et une fausse sécurité

### a. La chambre est relâchée, activement, pendant que la commande attend

`vikchannelmanager_cron_pending_locks` tourne toutes les cinq minutes
(`.local/vikchannelmanager/vikchannelmanager.php:398-402`, `:489-491`) :

```
.local/vikchannelmanager/admin/helpers/src/request/availability.php:163-168
    $q = $dbo->getQuery(true)
        ->delete($dbo->qn('#__vikbooking_tmplock'))
        ->where($dbo->qn('until') . ' <= ' . time());
.local/vikchannelmanager/admin/helpers/src/request/availability.php:189-207
    if (!in_array($pending['status'], ['standby', 'cancelled'])) { … continue; }
    // reservation is still pending payment … free up the room(s)
    $vcm = new SynchVikBooking($pending['vbo_order_id']);
    $result = $vcm->setSkipCheckAutoSync()->setFromCancellation([…])->sendRequest();
```

Vingt minutes après la création, le verrou tombe et la disponibilité est **rendue aux canaux**.
Un client qui a payé et dont le retour n'a pas abouti voit donc sa chambre remise à la vente,
sans que personne ne l'ait décidé.

### b. Revenir sur la page de paiement efface la trace de la session déjà payée

`validateTransaction()` ne connaît la session Stripe que par l'option WordPress :

```
.local/wp-vikstripe/stripe.php:581
    $session_id = get_option("stripe_order_{$this->get('oid')}");
```

Or, si le client revient sur la page 845 et redemande à payer, `createSession()` commence par
tester si la session existante est réutilisable :

```
.local/wp-vikstripe/stripe.php:356-377
    $session = get_option("stripe_order_{$this->get('oid')}");
    if ($session) {
        try {
            $checkout_session = $stripe->checkout->sessions->retrieve($session);
            if (($checkout_session->amount_total / … == $this->get('total_to_pay')) && !empty($checkout_session['url'])) {
                return $checkout_session;
            } else {
                delete_option("stripe_order_{$this->get('oid')}");
            }
        } catch (Exception $e) {
            delete_option("stripe_order_{$this->get('oid')}");
        }
    }
```

Le test porte sur `$checkout_session['url']`. La bibliothèque officielle embarquée le documente
elle-même :

```
.local/wp-vikstripe/Stripe/lib/Checkout/Session.php:77
 * @property null|string $url The URL to the Checkout Session. … This value is only present when the session is active.
```

**Une session déjà payée n'est plus active, donc son `url` est vide, donc l'option est effacée et
une session neuve est créée** (`stripe.php:497`, `:505`). Deux conséquences enchaînées : le client
peut payer une seconde fois, et **l'identifiant de la session réellement payée disparaît de
`wp_options`** — c'est-à-dire la seule trace, côté site, qui permettrait de la retrouver.

### c. Un mot sur le filet de sécurité du greffon, qui n'en est pas un

Le greffon écrit un transient, et un fichier `Stripe/<sid>-<oid>.tx` en repli
(`vikbooking/stripe.php:81-108`), censés conserver l'identifiant de session. Ils ne le conservent
pas : le greffon ne pose **jamais** `session_id` sur l'objet de paiement — sa seule écriture est
`add_option` — si bien que `$payment->get('session_id')` est vide à l'écriture. Vérifié sur les
110 fichiers : **le champ du milieu est vide dans les 110**. Et cela n'a aucune conséquence,
puisque `validateTransaction()` lit l'option, pas le transient. Ce dispositif est inerte.

Il reste utile pour une autre raison : ces fichiers sont datés et horodatent une **seconde**
présentation du formulaire de paiement pour la même commande (`set_transient` rend `false` quand
la valeur écrite est identique à celle déjà en place, ce qui déclenche l'écriture du fichier).
Ils marquent donc les clients qui sont **revenus** sur la page de paiement. Voir §7.

Note pour les phases suivantes : ces fichiers sont écrits **à l'intérieur du dossier du greffon**.
Une mise à jour de VikStripe les effacera.

---

## 7. Ce que la base montre

Aucune de ces mesures ne prouve qu'un client a été débité — cela, seul Stripe le sait. Elles
donnent l'ampleur de la fenêtre.

**Les commandes, tous états confondus :**

| Statut | Nombre | Première | Dernière |
|---|---|---|---|
| `confirmed` | 1 137 | 2023-08-17 | 2026-09-15 |
| `cancelled` | 295 | 2023-08-29 | 2026-09-15 |
| `standby` | **326** | 2023-09-02 | 2026-09-14 |

**Les traces de session Stripe non effacées** — `sir_options`, `option_name LIKE 'stripe_order_%'` :
**209 lignes**, dont, par statut de la commande correspondante : 137 `standby`, 49 `confirmed`,
18 `cancelled`, 5 sans commande.

**Les 137 en `standby` avec une session Stripe jamais soldée** portent un total cumulé de
**42 297.85 CHF**, de 1.00 à 696.00, entre le 2024-02-11 et le 2026-09-05. Et un fait qui tranche :

```
SELECT CASE WHEN o.paymentlog IS NULL OR o.paymentlog='' THEN 'vide' ELSE 'rempli' END, COUNT(*)
  → vide : 137
```

**Les 137 ont un `paymentlog` vide.** Or `notifypayment` écrit toujours ce journal, en succès
(`site/controller.php:2403-2404`) comme en échec (`:2561-2565`). **Un `paymentlog` vide prouve que
`notifypayment` n'a jamais tourné pour ces commandes** : ce ne sont pas des validations refusées,
ce sont des retours qui n'ont pas eu lieu. La très grande majorité sont sans doute des paniers
abandonnés — un client qui renonce devant l'écran Stripe laisse exactement la même trace. Les
deux cas sont indiscernables depuis la base.

**Le sous-ensemble le plus chargé de sens** : les commandes qui ont, en plus, un fichier `.tx`,
c'est-à-dire pour lesquelles la page de paiement a été présentée au moins deux fois. Sur les 110
fichiers : **53 `standby`** (total 16 986.30 CHF, du 2025-10-25 au 2026-09-05), 48 `confirmed`,
8 `cancelled`, 1 sans commande. **Les 53 portent toutes une option `stripe_order_*` vivante.**

**Un cas concret, relevé la semaine dernière** (adresse e-mail réduite aux huit premiers
caractères de son empreinte SHA-256, aucune donnée nominative dans ce dépôt) :

| id | statut | créée le | total | payé | e-mail | arrivée |
|---|---|---|---|---|---|---|
| 1794 | `standby` | 2026-09-03 14:13 | 194.65 | — | `7a857517` | 2026-09-07 15:00 |
| 1796 | `standby` | 2026-09-05 21:48 | 194.65 | — | `7a857517` | 2026-09-07 15:00 |
| 1797 | `standby` | 2026-09-05 22:20 | 194.65 | — | `7a857517` | 2026-09-07 15:00 |
| 1798 | **`confirmed`** | 2026-09-06 07:13 | 194.65 | **194.65** | `7a857517` | 2026-09-07 15:00 |

**La même personne a recommencé quatre fois en trois jours avant qu'une réservation aboutisse.**
Les trois premières ont chacune une session Stripe encore ouverte dans `wp_options` et un fichier
`.tx`. Que l'une d'elles ait été débitée ou non, la forme est exactement celle que ce constat
décrit : le client ne voit pas de confirmation, donc il recommence.

---

## 8. Les parades possibles, sans en choisir une

**La décision appartient à Thomas.** Elles ne s'excluent pas ; les deux premières sont des
vérifications, les suivantes des changements.

**A. Constater d'abord, décider ensuite.** Interroger Stripe sur les 137 sessions non soldées et
relever, pour chacune, `payment_status`. C'est la seule façon de savoir combien de clients ont
été débités sans réservation. Lecture seule, aucun effet de bord. **À faire avant de choisir
quoi que ce soit d'autre** : si la réponse est zéro, l'urgence tombe et il reste un défaut de
conception à traiter à froid.

**B. Rapprocher les deux comptes.** Comparer le journal des paiements Stripe sur la période avec
la somme des `totpaid` des commandes confirmées. Un écart chiffre le problème sans rien lire du
code.

**C. Poser un webhook Stripe.** C'est la parade que l'architecture réclame : `checkout.session.completed`
arrive de serveur à serveur, sans navigateur. Coût : c'est du code à écrire et à maintenir, dans
un mu-plugin — jamais dans le greffon. Il faudrait rejouer ce que fait `notifypayment` sans le
dupliquer, ce qui n'est pas trivial : ce contrôleur fait dix-sept choses, dont l'occupation des
chambres et l'envoi des e-mails. Point d'entrée possible : appeler `notifypayment` avec le `sid`
et le `ts` de la commande, puisqu'il est idempotent (`site/controller.php:1887-1893` refuse par
`409` une commande déjà confirmée).

**D. Poser une réconciliation planifiée.** Une tâche qui balaye périodiquement les commandes en
`standby` portant une option `stripe_order_*`, interroge Stripe, et pour chaque session payée
déclenche la même validation. Moins immédiat qu'un webhook, mais plus simple à écrire, sans
point d'entrée public à sécuriser, et il rattrape aussi les cas passés. Même exigence : mu-plugin,
jamais le greffon.

**E. Alerter, à défaut de corriger.** Le plus petit changement utile : un e-mail à
l'administration dès qu'une commande reste en `standby` plus de N minutes avec une session Stripe
ouverte. Ne répare rien, mais supprime le silence — et c'est le silence qui transforme un incident
technique en client débité sans nouvelles. Conforme à la règle « aucun échec silencieux » du
`CLAUDE.md`.

**F. Fermer la porte au double paiement.** Empêcher que le retour sur la page 845 efface la trace
de la session déjà payée (§6.b), en interrogeant `payment_status` avant de décider qu'une session
n'est pas réutilisable. À faire par-dessus le greffon, par un crochet, jamais en le modifiant.

**G. Allonger `minuteslock`.** Vingt minutes, c'est court pour un paiement par carte avec
authentification forte. Ne corrige pas la cause, mais réduit la fenêtre pendant laquelle la
chambre part alors que le client est encore chez Stripe. Réglage natif de Vik, à changer par
Thomas dans *Global Configuration*, et à consigner dans `journal-vik.md`.

**Une remarque sur le tri.** A et B ne coûtent rien et ne risquent rien. E est petit et rend le
problème visible immédiatement. C et D se recouvrent largement : C est la bonne réponse
d'architecture, D est la bonne réponse d'exploitation, et D seul rattrape l'existant. F est
indépendant des autres et traite un risque distinct — le double débit — qui n'a rien à voir avec
le retour manquant.

---

## 9. Ce que ce constat n'établit pas

1. **Combien de clients ont réellement été débités sans réservation confirmée.** Le site ne le
   sait pas : il n'enregistre rien entre le départ vers Stripe et le retour. Seule une lecture de
   l'API Stripe le dirait, et elle demande la clé secrète, que ce constat n'a ni lue ni cherchée.
   Les 137 commandes recensées sont la **borne haute** : la plupart sont vraisemblablement des
   abandons.
2. **Si l'incident s'est déjà produit dans les faits.** Le cas des commandes 1794/1796/1797/1798
   en a la forme, pas la preuve.
3. **Ce que la version 2.2.4 a changé par rapport à la 2.2.3**, le journal du greffon s'arrêtant à
   la 2.2.3.
4. **Le comportement sous NitroPack.** La page 845 porte des paramètres de requête et une
   redirection ; le cache n'a pas été testé sur ce chemin. La règle 4 du `CLAUDE.md` s'applique
   avant de conclure à un bug observé en frontal.
5. **Le cas de la caution payée séparément** (`&dd=1`, `default.php:1603-1611`), hors périmètre du
   tunnel Sexcape Room.

---

## Commandes et requêtes exécutées

Toutes en lecture seule, le 15 septembre 2026.

```bash
# Version et intégrité du greffon
shasum -a 256 .local/wp-vikstripe/{vikstripe.php,stripe.php,vikbooking/stripe.php}
ssh sg-linstantcle "cd ~/www/linstantcle.ch/public_html/wp-content/plugins/wp-vikstripe && sha256sum vikstripe.php stripe.php vikbooking/stripe.php"

# Écart entre la copie locale du cœur et la production
ssh sg-linstantcle "cd ~/www/linstantcle.ch/public_html/wp-content/plugins/vikbooking && find . -name '*.php' -type f -print0 | xargs -0 sha256sum"

# Fichiers de transaction du greffon
ls .local/wp-vikstripe/Stripe/*.tx | wc -l
ssh sg-linstantcle "ls ~/www/…/wp-vikstripe/Stripe/*.tx | wc -l"
```

```sql
SELECT id, name, file, published, setconfirmed, charge, ch_disc, val_pcent FROM sir_vikbooking_gpayments ORDER BY id;
SELECT * FROM sir_vikbooking_config WHERE param IN ('minuteslock','paytotal','accpercent','typedeposit','multiplepayments');
SELECT JSON_VALID(params), JSON_UNQUOTE(JSON_EXTRACT(params,'$.skipbtn')), JSON_UNQUOTE(JSON_EXTRACT(params,'$.paytype')),
       JSON_UNQUOTE(JSON_EXTRACT(params,'$.use_decimals')), JSON_UNQUOTE(JSON_EXTRACT(params,'$.currency')),
       JSON_UNQUOTE(JSON_EXTRACT(params,'$.transaction_type')) FROM sir_vikbooking_gpayments WHERE id=3;

SELECT status, COUNT(*), MIN(FROM_UNIXTIME(ts)), MAX(FROM_UNIXTIME(ts)) FROM sir_vikbooking_orders GROUP BY status;
SELECT COUNT(*) FROM sir_options WHERE option_name LIKE 'stripe_order_%';
SELECT o.status, COUNT(*) FROM sir_options t
  LEFT JOIN sir_vikbooking_orders o ON o.id = CAST(SUBSTRING(t.option_name,14) AS UNSIGNED)
  WHERE t.option_name LIKE 'stripe_order_%' GROUP BY o.status;
SELECT CASE WHEN o.paymentlog IS NULL OR o.paymentlog='' THEN 'vide' ELSE 'rempli' END, COUNT(*),
       MIN(FROM_UNIXTIME(o.ts)), MAX(FROM_UNIXTIME(o.ts)), SUM(o.totpaid)
  FROM sir_options t JOIN sir_vikbooking_orders o ON o.id = CAST(SUBSTRING(t.option_name,14) AS UNSIGNED)
  WHERE t.option_name LIKE 'stripe_order_%' AND o.status='standby' GROUP BY 1;

SELECT * FROM sir_vikbooking_cronjobs;
SELECT COUNT(*) FROM sir_vikbooking_payschedules;
SELECT option_value FROM sir_options WHERE option_name='cron';   -- inventaire des 77 créneaux

SELECT id, status, FROM_UNIXTIME(ts), ROUND(total,2), totpaid, LEFT(SHA2(LOWER(TRIM(custmail)),256),8), FROM_UNIXTIME(checkin)
  FROM sir_vikbooking_orders WHERE checkin = UNIX_TIMESTAMP('2026-09-07 15:00:00') ORDER BY ts;
```

Aucun `INSERT`, `UPDATE`, `DELETE` ni `ALTER` n'a été exécuté : le compte MySQL est en `SELECT`
seul, et c'est une décision, pas une limitation subie.
