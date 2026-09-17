# Constat — incident 1818, la session payée n'est pas celle que le site a retenue

**La cause première n'est pas le retour manquant : le retour a eu lieu. C'est que la page de
paiement crée une session Stripe neuve à **chaque** rendu, et que la page a été rendue trois fois
en trente-six secondes. Le client a payé la session du premier rendu ; le site, lui, n'avait plus
en mémoire que celle du dernier.**

Version 1, 17 septembre 2026. Lecture seule : aucun fichier de plugin, aucun réglage Stripe,
aucune ligne de base n'a été modifiée. Aucun code n'a été écrit, rien n'a été déployé.

Réponse au prompt du 17 septembre 2026. Documents liés : `constat-reserve-paiement.md` (dont ce
constat traite la cause que le §8 ne couvre pas), `constat-phase-0.md` §Q5,
`constat-perimetre-tunnel.md` §F, `handoff-acces-mysql.md`.

**Ce que ce document établit :** pourquoi plusieurs sessions Checkout naissent pour une même
réservation en quelques secondes, comment l'option `stripe_order_<id>` est écrite puis écrasée,
pourquoi c'est la dernière session créée qui l'emporte, et combien de commandes se trouvent
aujourd'hui dans l'état exact de la 1818. **Ce qu'il n'établit pas :** l'horodatage des deux
sessions que la base ne connaît pas (`cs_live_a14dzf…` et `cs_live_a1KXmw…`), qui ne vit que chez
Stripe — voir §5.

---

## Sources et méthode

| Source | Ce qui en a été tiré |
|---|---|
| `.local/wp-vikstripe/` (VikStripe **2.2.4**) | `createSession()`, `validateTransaction()`, les quatre points d'écriture de l'option |
| `.local/vikbooking/` (Vik Booking 1.8.14, lignes vérifiées identiques en production) | calcul de `total_to_pay`, rendu du formulaire, branche d'échec de `notifypayment` |
| MySQL `dbvkhvlostfyua`, lecture seule via `ssh sg-linstantcle` | `sir_vikbooking_orders`, `sir_vikbooking_ordersrooms`, `sir_vikbooking_gpayments`, `sir_vikbooking_iva`, `sir_options` |
| `~/www/linstantcle.ch/logs/linstantcle.ch-2026-09-1*.gz`, journaux d'accès | qui rend la page de paiement, combien de fois, et avec quel agent |
| Horodatage du fichier `Stripe/1651018164-1818.tx` en production | le troisième rendu, à la seconde |

Le greffon VikStripe n'a pas été relu en entier, conformément à la consigne : seules les méthodes
`createSession()` et `validateTransaction()` ont été rouvertes, et les constats existants ont servi
pour tout le reste. **`mu-plugins/lme-brands/` n'est pas déployé en production** — relevé du
17 septembre 2026, `wp-content/mu-plugins/` ne contient que `api-host.php` et
`vre-paid-autoconfirm.php` (ce dernier ne concerne que VikRestaurants). L'incident s'est donc
produit sur la pile native, sans notre code.

---

## 0. L'incident, dans l'ordre des faits

Tout est en UTC, comme la base.

| Heure | Fait | Preuve |
|---|---|---|
| 10:50:18 | commande **1818** insérée en `standby`, total 2.53 CHF, `sid=1651018164`, `ts=1789642218` | `sir_vikbooking_orders.ts` = 1789642218 |
| ~10:50:2x | **rendu n°1** de la page 845 par le navigateur du client → session **`cs_live_a14dzf…`**, rangée dans `stripe_order_1818` | déduit : c'est la session que le client a payée, donc celle dont le bouton était sur la page qu'il a reçue |
| 10:50:24 | **rendu n°2**, par l'agent d'optimisation NitroPack → session **`cs_live_a1Ohez…`**, qui **remplace** la précédente dans l'option | `[created] => 1789642224` dans le `paymentlog` de 1818 |
| entre 10:50:24 et 10:50:50 | **le client paie `cs_live_a14dzf…`**, `pi_3UGctsD3eyoG9jO92NPATgaf`, 2.53 CHF | relevé Stripe fourni par Thomas |
| 10:50:50 | `task=notifypayment` s'exécute, lit l'option, y trouve **`cs_live_a1Ohez…`**, l'interroge : `payment_status => unpaid`, `status => open`, `payment_intent` vide → **refus** | `paymentlog` de 1818, en-tête `2026-09-17T10:50:50+00:00` |
| 10:50:50 | courriel « paiement non vérifié » à l'administration, portant l'identifiant `cs_live_a1Ohez…` ; `paymentlog` écrit | `site/controller.php:2554-2567` |
| 10:50:54 | le greffon renvoie le navigateur sur la vue `booking`, **rendu n°3** → session **`cs_live_a1KXmw…`**, qui remplace à son tour l'option | fichier `Stripe/1651018164-1818.tx`, mtime `2026-09-17 10:50:54.269 +0000` |
| depuis | commande `standby`, `totpaid` **NULL**, `paymcount` 0, `payable` 0.00, option `stripe_order_1818` = `cs_live_a1KXmw…` | `sir_options.option_id` 2288994 |

Les trois identifiants du prompt sont donc chacun à leur place : **`a14dzf` est le premier rendu
et le seul payé, `a1Ohez` le deuxième et celui que le courriel d'erreur signale, `a1KXmw` le
troisième et le seul que le site a gardé.** Aucun des trois n'est le bon au bon moment.

---

## 1. Pourquoi plusieurs sessions sont créées pour une même réservation

Trois pièces, dans cet ordre : une session naît à chaque rendu ; le garde-fou censé l'éviter ne
peut pas fonctionner sur cette commande ; et la page a été rendue trois fois.

### 1.a Une session Stripe naît à chaque rendu de la page de paiement, pas au clic du client

Le formulaire de paiement d'une commande en attente est construit par le bloc `standby` de la vue
`booking` :

```
.local/vikbooking/site/views/booking/tmpl/default.php:1254-1255
    // stand-by booking payment rendering
    if (is_array($payment) && $ord['status'] == 'standby') {
.local/vikbooking/site/views/booking/tmpl/default.php:1578-1580
    $obj = JPaymentDispatcher::getInstance('vikbooking', $payment['file'], $array_order, $payment['params']);
    // remember to echo the payment
    echo $obj->showPayment();
```

`showPayment()` appelle `beginTransaction()` **dans le fil de la requête**, pas plus tard :

```
.local/vikbooking/libraries/adapter/payment/payment.php:342-345
    // start buffer
    ob_start();
    // children payments will start the transaction
    $this->beginTransaction();
```

et la première chose que fait `beginTransaction()` côté Stripe est de fabriquer la session :

```
.local/wp-vikstripe/stripe.php:526-530
    // create a Stripe client
    $stripe = new \Stripe\StripeClient($this->getParam('secretkey') ?? '');
    // create a Stripe session
    $checkout_session = $this->createSession($stripe, $customer);
```

**Conséquence : toute requête HTTP qui rend la page 845 pour une commande en `standby` dialogue
avec Stripe et peut y créer une session, que le client clique ou non, qu'il soit devant l'écran ou
non.** Le bouton « PAY NOW » ne crée rien, il ne fait que pointer l'URL de la session déjà créée
(`stripe.php:540`, `tmpl/success.html.php:1-2`).

### 1.b Le seul garde-fou, le test de réutilisation, ne peut pas réussir sur cette commande

`createSession()` commence par tenter de réutiliser la session déjà rangée :

```
.local/wp-vikstripe/stripe.php:356-377
    $session = get_option("stripe_order_{$this->get('oid')}");
    if ($session)
    {
        try
        {
            $checkout_session = $stripe->checkout->sessions->retrieve($session);
            // check if the session is valid
            if (($checkout_session->amount_total / ($this->getParam('use_decimals', 1) ? 100 : 1) == $this->get('total_to_pay')) && !empty($checkout_session['url']) )
            {
                return $checkout_session;
            }
            else
            {
                delete_option("stripe_order_{$this->get('oid')}");
            }
        }
        catch (Exception $e)
        {
            delete_option("stripe_order_{$this->get('oid')}");
        }
    }
```

Deux conditions, jointes par un `&&`. La seconde — `url` non vide — est celle qu'établit déjà le
§6.b du constat de réserve : une session payée n'est plus active, donc son `url` est vide, donc
l'option est effacée. **La première, ici, est celle qui compte : le montant de la session doit être
strictement égal à `total_to_pay`.** Et sur la commande 1818, elle est fausse dès le départ.

**Le montant remis à Stripe est arrondi, celui qu'on lui compare ne l'est pas.** À la création :

```
.local/wp-vikstripe/stripe.php:387-392
    $amount_to_pay = round($this->get('total_to_pay'));
    if ($this->getParam('use_decimals', 1))
    {
        $amount_to_pay = round($this->get('total_to_pay'), 2) * 100;
    }
```

Au test de réutilisation, on compare `amount_total / 100` à `total_to_pay` **non arrondi**.
L'arrondi n'est appliqué qu'à l'aller, jamais au retour. Tant que `total_to_pay` tient sur deux
décimales, les deux valeurs coïncident et la session est réutilisée. Dès qu'il en porte une
troisième, elles ne coïncident plus **jamais**, et chaque rendu efface l'option et crée une
session neuve.

**Or `total_to_pay` n'est pas le total rangé en base : il est recalculé à chaque rendu, en
virgule flottante, à partir des tarifs et du coupon.**

```
.local/vikbooking/site/views/booking/tmpl/default.php:105-107
    $calctar = VikBooking::sayCostPlusIva($display_rate, $tars[$num]['idprice']);
    $tars[$num]['calctar'] = $calctar;
    $isdue += $calctar;
.local/vikbooking/site/views/booking/tmpl/default.php:214-217
    if (strlen((string)$ord['coupon']) > 0) {
        $usedcoupon = true;
        $expcoupon = explode(";", $ord['coupon']);
        $isdue = $isdue - $expcoupon[1];
.local/vikbooking/site/views/booking/tmpl/default.php:1310
    $array_order['total_to_pay'] = $isdue;
```

Relevé en base pour la 1818, et l'arithmétique se referme exactement :

| Grandeur | Valeur | Source |
|---|---|---|
| coût de la chambre | `253.30` | `sir_vikbooking_ordersrooms.room_cost`, ligne 1917 |
| TVA | aucune ajoutée (`aliq` 7.700 non appliquée, `tot_taxes` = 0.00) | `sir_vikbooking_iva`, `sir_vikbooking_orders` |
| remise du coupon (99 %) | `250.767` | `sir_vikbooking_orders.coupon`, deuxième champ |
| **`total_to_pay` recalculé** | `253.30 − 250.767 =` **`2.533`** | confirmé par le fichier `1651018164-1818.tx`, qui l'enregistre tel quel : `2.533^^https://…` |
| total rangé en base | `2.53` | colonne `total`, de type `decimal(12,2)` : la troisième décimale est perdue à l'écriture |
| montant de la session Stripe | `253` | `round(2.533, 2) * 100`, et `amount_total => 253` dans le `paymentlog` |

Le test devient `253 / 100 == 2.533`, c'est-à-dire `2.53 == 2.533` : **faux**. Il l'est au premier
rendu comme au centième. **Sur cette commande, la réutilisation de session est structurellement
impossible : chaque rendu de la page de paiement efface l'option et crée une session Stripe de
plus.**

La cause n'est donc pas le coupon en soi : c'est que le montant dû, recalculé à chaque rendu, peut
porter plus de deux décimales, alors que le montant envoyé à Stripe est arrondi. Un coupon en
pourcentage est le moyen le plus simple d'y arriver, mais toute remise, tout supplément
proportionnel ou toute taxe en pourcentage produit la même chose.

### 1.c Qui rend la page plus d'une fois : NitroPack, puis l'échec lui-même

**Premier rendu supplémentaire, systématique : l'agent d'optimisation de NitroPack rend la page
de paiement personnalisée du client, quelques secondes après lui.** Journal d'accès, séquence
complète d'une réservation du 15 septembre, tous les champs conservés sauf l'adresse IP du client
et sa fin d'entête :

```
85.7.187.47    [15/Sep/2026:20:25:03] "GET  /fr/les-details-de-votre-reservation/?sid=1501574577&ts=1789503899 HTTP/2.0" 200 43310
               referer /fr/villas-privees/l-entracte/?task=oconfirm — "… (iPhone; CPU iPhone OS 18_7 …) Safari/604.1"
85.7.187.47    [15/Sep/2026:20:25:03] "POST /fr/les-details-de-votre-reservation/?sid=1501574577&ts=1789503899 HTTP/2.0" 200 15
34.147.235.168 [15/Sep/2026:20:25:05] "GET  /fr/les-details-de-votre-reservation/?sid=1501574577&ts=1789503899&ignorenitro=1d58212923d5ffc3a2f112af8bb39370 HTTP/1.1" 200 42620
               referer "-" — "… Chrome/131.0.0.0 Mobile Safari/537.36 Nitro-Optimizer-Agent"
```

Trois enseignements, tous lisibles sur ces trois lignes :

1. **la troisième requête est un rendu complet** — 42 620 octets, à comparer aux 43 310 du client.
   Elle traverse WordPress, exécute le shortcode, donc exécute `showPayment()`, donc appelle
   `createSession()` ;
2. **elle porte le `sid` et le `ts` du client**. NitroPack ne fabrique pas cette URL : il la
   reprend telle quelle de la page qui vient d'être servie en `MISS`, pour en construire la
   version optimisée. La page de paiement d'une réservation nominative est traitée comme une page
   publique ordinaire ;
3. **elle arrive deux secondes après**, sans référent, depuis une adresse Google Cloud, sous
   l'agent `Nitro-Optimizer-Agent`. La deuxième ligne, le `POST` de 15 octets, n'est pas un rendu :
   c'est un appel asynchrone de la page, trop court pour contenir un formulaire de paiement.

Ce n'est pas un cas isolé. Sur les cinq jours de journaux disponibles, du 12 au 16 septembre 2026,
**neuf rendus de la page de paiement par l'agent NitroPack sur une URL portant un `sid`
personnel** ont été relevés, un par réservation :

```
12/Sep 15:07:15  sid=1546570194      15/Sep 10:08:08  sid=579718074
12/Sep 17:11:48  sid=1078789833      15/Sep 10:19:32  sid=713055265
15/Sep 03:57:40  sid=2033980266      15/Sep 20:25:05  sid=1501574577
15/Sep 09:21:46  sid=1489060346      16/Sep 13:51:50  sid=768859083
15/Sep 10:00:19  sid=1531728432
```

Pour la 1818, le journal d'accès du 17 septembre n'est pas encore disponible — la rotation a lieu
à 04:30 et l'incident est de 10:50 — mais l'horodatage de la session `a1Ohez`, `created` à
10:50:24, soit **six secondes après la création de la commande**, tombe exactement dans la fenêtre
observée pour l'agent NitroPack sur les neuf autres cas.

**Second rendu supplémentaire, celui-là déclenché par l'échec lui-même.** Quand la validation
échoue, le cœur prévient l'administration, écrit le journal, puis appelle `afterValidation(0)` :

```
.local/vikbooking/site/controller.php:2554-2567
    } else {
        if (empty($array_result['skip_email'])) {
            …
            $vbo_app->sendMail($adsendermail, $adsendermail, $recipient_mail, $adsendermail, JText::_('VBPAYMENTNOTVER'), JText::_('VBSERVRESP') . ":\n\n" . $array_result['log'], false);
        }
        if (!empty($array_result['log'])) {
            $q = "UPDATE `#__vikbooking_orders` SET `paymentlog`=".$dbo->quote($newpaymentlog)." WHERE `id`='" . $row['id'] . "';";
            …
        }
        if (method_exists($obj, 'afterValidation')) {
            $obj->afterValidation(0);
        }
    }
```

`afterValidation()` déclenche le crochet du greffon, qui **ne teste pas `$res`** et renvoie le
navigateur sur la vue `booking` dans tous les cas (`payment.php:512` →
`wp-vikstripe/vikbooking/stripe.php:223-242`, établi en Q5 et au §F). La commande étant toujours en
`standby`, cette page **re-rend le formulaire de paiement** — donc crée une session de plus. C'est
le rendu de 10:50:54, daté par le fichier `.tx`, et c'est lui qui a rangé `cs_live_a1KXmw…` dans
l'option.

**L'échec de validation produit donc mécaniquement une nouvelle session Stripe, quatre secondes
après. Le site se met lui-même hors d'état de retrouver la bonne.**

### 1.d Verdict : c'est un défaut du greffon, et la règle n°1 interdit d'y toucher

La cause première est à `stripe.php:365` : **un test d'égalité entre un montant arrondi à deux
décimales et un montant qui ne l'est pas.** Deux lignes suffiraient à le corriger — comparer des
centimes entiers des deux côtés, `(int) $checkout_session->amount_total == (int) round($this->get('total_to_pay') * 100)`.
Ces deux lignes sont dans `wp-content/plugins/wp-vikstripe/stripe.php`, un plugin tiers. **La règle
absolue n°1 du `CLAUDE.md` l'interdit, et une mise à jour de VikStripe effacerait la correction.
La parade devra donc passer ailleurs — voir §5.**

Le second facteur, lui, est entièrement hors du greffon : **NitroPack rend une page qu'il ne
devrait jamais rendre.** Celui-là se traite par un réglage, sans écrire une ligne de code.

---

## 2. Comment `stripe_order_<id>` est écrit, écrasé, et pourquoi la dernière session gagne

### 2.a Les quatre points, et leurs quatre lignes

L'option `stripe_order_<id>` est la seule trace durable, côté site, d'une session Stripe. Quatre
lignes de tout le greffon la manipulent :

| Ligne | Ce qu'elle fait | Quand |
|---|---|---|
| `wp-vikstripe/stripe.php:505` | `add_option("stripe_order_{$this->get('oid')}", $checkout_session->id);` | juste après la création d'une session |
| `wp-vikstripe/stripe.php:371` | `delete_option(…)` | la session rangée existe mais son montant ne correspond pas, ou son `url` est vide |
| `wp-vikstripe/stripe.php:376` | `delete_option(…)` | la session rangée est introuvable chez Stripe (exception) |
| `wp-vikstripe/stripe.php:638` | `delete_option(…)` | après une validation réussie, session `complete` |

**Il n'y a pas de `update_option`.** L'écriture se fait toujours par `add_option`, qui ne remplace
jamais une valeur existante. Cela n'empêche pourtant pas l'écrasement, parce que la suppression et
l'ajout sont enchaînés dans la même méthode : `createSession()` **efface d'abord, crée ensuite,
range enfin**. Le chemin complet d'un rendu qui ne peut pas réutiliser est donc
`get_option` → `retrieve` → `delete_option` (`:371`) → `sessions->create` (`:497`) →
`add_option` (`:505`), et l'`add_option` réussit puisque l'option vient d'être supprimée.

### 2.b Pourquoi c'est la dernière session créée qui gagne

**Parce que l'option n'est pas un registre, c'est une variable à une seule case, écrite par le
dernier qui passe.** Rien, dans `createSession()`, ne regarde si la session qu'on s'apprête à
effacer a été payée : le test de la ligne 365 ne porte que sur le montant et sur la présence d'une
`url`. Une session payée échoue à ces deux conditions — son montant n'est pas comparable ici, et
son `url` est vide parce qu'elle n'est plus active (`Stripe/lib/Checkout/Session.php:77`). Elle est
donc effacée comme n'importe quelle session périmée, sans distinction.

Sur la 1818 l'enchaînement est vérifiable dans les données :

- à **10:50:50**, `validateTransaction()` lit l'option et y trouve `cs_live_a1Ohez…` — c'est écrit
  noir sur blanc en tête du `paymentlog`, `Session ID: cs_live_a1Ohez…` ;
- **aujourd'hui**, l'option contient `cs_live_a1KXmw…`, un troisième identifiant. Elle a donc bien
  été effacée puis réécrite **après** 10:50:50, et le seul rendu qui ait eu lieu après, c'est celui
  de 10:50:54 daté par le fichier `.tx` ;
- l'option **existe toujours**, ce qui prouve au passage que le `delete_option` de la ligne 638
  n'a jamais été atteint : la validation n'a jamais vu de session `complete`.

Et la session payée, `cs_live_a14dzf…`, a été poussée hors de la case dès 10:50:24, **avant même
que le client ne paie**. Ce n'est pas le paiement qui l'a effacée : c'est le rendu de NitroPack.
Le client a payé une session dont le site avait déjà perdu la trace.

### 2.c `notifypayment` n'a aucun autre moyen de retrouver la bonne session

**Non. Sur cette installation, l'option est le seul chemin, et il n'existe aucun repli.**

`validateTransaction()` lit l'option et rien d'autre :

```
.local/wp-vikstripe/stripe.php:580-587
    // retrieve the ID of session to be validated
    $session_id = get_option("stripe_order_{$this->get('oid')}");
    try
    {
        // create a Stripe client
        $stripe = new \Stripe\StripeClient($this->getParam('secretkey'));
        // retrieve the session from the Stripe API
        $session = $stripe->checkout->sessions->retrieve($session_id);
```

Trois replis existent en apparence, aucun ne fonctionne :

1. **Le crochet `payment_before_validate_transaction_vikbooking`** (`wp-vikstripe/vikbooking/stripe.php:113-219`)
   s'exécute bien avant la validation, relit le transient puis, à défaut, le fichier `.tx`, et pose
   `$payment->set('session_id', …)`. Mais `validateTransaction()` **ne lit jamais
   `$payment->get('session_id')`** : il relit l'option. Et de toute façon la valeur posée serait
   vide : le §6.c du constat de réserve établit que le greffon n'écrit jamais `session_id` sur
   l'objet de paiement, et le fichier de la 1818 le confirme — `2.533^^https://…`, champ du milieu
   vide, comme les 110 autres. **Ce dispositif est inerte.**
2. **`client_reference_id`**, le champ que Stripe prévoit précisément pour rattacher une session à
   une commande, **n'est jamais renseigné** : recherche de `client_reference_id` dans
   `stripe.php` et `vikbooking/stripe.php` — aucune occurrence, et le `paymentlog` de la 1818 le
   montre vide (`[client_reference_id] =>`).
3. **Aucun webhook**, donc aucune notification de serveur à serveur qui porterait l'identifiant de
   la session payée : établi au §F du constat de périmètre, reconfirmé au §0 du constat de réserve.

**Un seul fil subsiste, et il est du côté de Stripe, pas du site.** Chaque session créée par cette
installation porte le numéro de commande en métadonnée, posée par le cœur de Vik et non par le
greffon :

```
.local/vikbooking/site/views/booking/tmpl/default.php:1553-1557
    $array_order['tn_metadata'] = [
        'booking_id'     => $ord['id'],
        'source'         => (($ord['channel'] ?? '') ?: 'Website'),
        'ota_booking_id' => (($ord['idorderota'] ?? '') ?: ''),
    ];
```

reprise telle quelle dans la configuration de la session (`wp-vikstripe/stripe.php:454`, `:468-471`)
et lisible dans le `paymentlog` de la 1818 : `[metadata] => ( [booking_id] => 1818 … )`.

**Les trois sessions de la 1818 portent donc toutes `booking_id = 1818` chez Stripe.** C'est le
seul moyen existant de retrouver la bonne : interroger Stripe pour les sessions d'une commande et
retenir celle dont `payment_status` vaut `paid`. Ce moyen ne demande aucune modification du
greffon — il ne demande qu'un appel en lecture à l'API Stripe, avec la clé secrète. Il est la base
des parades C et D du §5.

---

## 3. Combien de commandes sont dans l'état de la 1818

**Deux, en trois ans d'exploitation. Total 374.73 CHF. Du 21 mars 2025 au 17 septembre 2026.**

Le critère est celui du prompt : statut `standby` **et** `paymentlog` non vide, c'est-à-dire une
commande restée en attente alors que `notifypayment` a bel et bien tourné et a refusé. C'est
l'inverse exact des 137 du §7 du constat de réserve, qui sont `standby` avec un `paymentlog`
**vide**, donc des retours qui n'ont jamais eu lieu.

| id | créée le | total | `totpaid` | empreinte du courriel | option `stripe_order_*` | taille du `paymentlog` |
|---|---|---|---|---|---|---|
| **859** | 2025-03-21 10:21:55 | 372.20 | NULL | `7a2bd97e` | absente | 278 |
| **1818** | 2026-09-17 10:50:18 | 2.53 | NULL | `256ca89d` | présente | 5 447 |

**Les deux ne sont pas le même incident.** Le journal de la 859 vient d'une génération antérieure
du greffon : il montre une itération sur les **événements** Stripe, revenue vide —
`Starting Events iteration!`, `Stripe\Collection Object … [url] => /v1/events`, puis
`37220 - cs_live_a1fbHGy…`. Or ni `Starting Events iteration`, ni `/v1/events`, ni `->events->`
n'existent nulle part dans la 2.2.4 (recherche sur `stripe.php` et `vikbooking/stripe.php`, aucune
occurrence). La 859 a donc échoué pour une autre raison, sur un autre code, et son option a
disparu depuis. **La 1818 est le premier cas connu du mécanisme décrit ici.**

Pour situer l'un par rapport à l'autre, relevé du 17 septembre 2026 sur les commandes `standby`
portant une option `stripe_order_*` vivante :

| `paymentlog` | nombre | total | première | dernière |
|---|---|---|---|---|
| vide | **137** | 42 297.85 | 2024-02-11 13:58 | 2026-09-05 22:20 |
| rempli | **1** | 2.53 | 2026-09-17 10:50 | 2026-09-17 10:50 |

Les 137 du constat de réserve sont donc inchangés, et la 1818 s'ajoute à côté d'eux, pas dedans.
Les totaux généraux, pour mémoire : 1 137 `confirmed`, 327 `standby`, 297 `cancelled` — soit une
commande en attente et deux annulées de plus qu'au 15 septembre.

**Une remarque sur ce chiffre de deux.** Il est bas, et il ne dit pas que le défaut est rare. Il
dit que **la conjonction est rare** : il faut que le montant dû porte une troisième décimale, que
la page soit rendue plus d'une fois, et que le client paie quand même. Les deux premières
conditions, elles, sont réunies en permanence : NitroPack rend la page de paiement de chaque
réservation, et il suffit d'un coupon en pourcentage pour la troisième décimale. **Ce qui est rare
ici, c'est le coupon — pas le double rendu.**

---

## 4. Ce que ce constat n'établit pas

1. **L'heure de création des sessions `cs_live_a14dzf…` et `cs_live_a1KXmw…`.** Le site n'en a
   gardé aucune trace : l'une a été effacée de `wp_options` à 10:50:24, l'autre y est encore mais
   sans horodatage. Seul Stripe les date, et les lire demande la clé secrète, que ce constat n'a
   ni lue ni cherchée. L'ordre retenu au §0 est déduit, pas mesuré : il est le seul compatible
   avec les trois faits horodatés dont on dispose.
2. **Le journal d'accès du 17 septembre.** La rotation a lieu à 04:30 et l'incident est de 10:50 ;
   la journée en cours n'est pas lisible depuis SSH. La démonstration du rendu NitroPack repose
   donc sur neuf occurrences des cinq jours précédents et sur la concordance d'horodatage, pas sur
   la ligne de journal de l'incident lui-même. **Cette ligne sera lisible à partir du
   18 septembre 04:30**, dans `linstantcle.ch-2026-09-18.gz` : c'est la vérification à faire pour
   clore ce point.
3. **Ce que fait NitroPack de la page qu'il a rendue.** Qu'il crée une session est établi ; qu'il
   serve ensuite cette page — donc le bouton d'une session qui n'est plus la bonne — à un autre
   visiteur n'a pas été testé. La règle 4 du `CLAUDE.md` s'applique avant toute conclusion sur ce
   point.
4. **Si d'autres réservations payées ont été perdues sans laisser de `paymentlog`.** Elles
   tomberaient alors dans les 137 du constat de réserve, indiscernables d'un panier abandonné.
5. **Le nombre exact de sessions Checkout ouvertes pour la 1818.** Trois sont connues par le
   prompt ; rien n'exclut qu'un rendu supplémentaire en ait créé une quatrième après 10:50:54.

---

## 5. Les parades possibles pour ce défaut-ci, sans en choisir une

**La décision appartient à Thomas.** Ces parades traitent un défaut distinct de celui du §8 du
constat de réserve : là, le retour n'avait pas lieu ; ici, il a lieu et se trompe de session. Les
parades C, D et E de ce constat-là — webhook, réconciliation planifiée, alerte — restent valables
et se recouvrent en partie avec ce qui suit, mais **aucune d'elles ne suffit seule** : un webhook
`checkout.session.completed` aurait corrigé la 1818, parce qu'il porte l'identifiant de la session
payée et n'a pas besoin de l'option ; une réconciliation qui balaierait les commandes `standby` en
interrogeant `stripe_order_<id>` **aurait échoué exactement comme `notifypayment`**, puisqu'elle
aurait interrogé `cs_live_a1KXmw…`.

**H. Sortir la page de paiement de NitroPack.** C'est la parade la plus directe, la moins chère,
et la seule qui traite le déclencheur plutôt que le symptôme. Exclure la page 845 — et toute URL
portant un paramètre `sid` — de l'optimisation NitroPack, de sorte que l'agent
`Nitro-Optimizer-Agent` cesse de rendre la page de paiement d'un client. Réglage du tableau de bord
NitroPack, aucune ligne de code, aucun fichier de plugin touché. Elle supprime le deuxième rendu ;
elle ne supprime pas le troisième, celui que l'échec déclenche. À consigner dans un journal, au
même titre qu'un changement de réglage Vik.

**I. Empêcher un rendu de détruire la trace d'une session payée.** C'est la parade F du constat de
réserve, vue d'ici : se greffer sur `payment_before_begin_transaction_vikbooking`
(`payment.php:329`, déjà utilisé par `lme-brands/includes/payment-brand.php:67`), lire l'option,
interroger Stripe sur le `payment_status` de la session qui y est rangée, et interrompre le rendu
du formulaire si elle est payée. Coût : un appel à l'API Stripe à chaque rendu de la page de
paiement, donc la clé secrète dans notre code. Limite sérieuse : le crochet ne peut qu'agir
**avant** `createSession()` ; il ne peut pas empêcher `createSession()` d'effacer l'option une fois
lancé. Il faudrait donc qu'il court-circuite le rendu entièrement, ce qui reste à établir.

**J. Tenir notre propre registre des sessions, à côté de celui du greffon.** Le même crochet, mais
en écriture seulement : à chaque rendu, consigner dans une table ou une option à nous le couple
`<commande, session>` sans jamais rien effacer. Le greffon garde sa case unique, nous gardons
l'historique. Aucune modification du greffon, aucun appel à Stripe en frontal, et la trace survit
au troisième rendu comme au dixième. Elle ne répare rien toute seule : elle rend la réparation
possible, par K ou par la parade D du constat de réserve.

**K. Réconcilier par la métadonnée, pas par l'option.** Puisque chaque session porte
`metadata.booking_id`, une réconciliation peut demander à Stripe **toutes** les sessions d'une
commande et retenir celle dont `payment_status` vaut `paid`, sans jamais consulter
`stripe_order_<id>`. C'est le seul chemin qui aurait retrouvé `cs_live_a14dzf…` le 17 septembre à
10:50:50. Elle relève de la parade D du constat de réserve, mais **avec ce changement de critère**,
sans lequel D aurait échoué ici. Mu-plugin, jamais le greffon.

**L. Ne pas produire de montant à trois décimales.** Traiter la cause par les données plutôt que
par le code : exiger que toute remise, tout supplément et toute taxe en pourcentage tombe sur deux
décimales. Sur la 1818, une remise de 250.77 au lieu de 250.767 aurait suffi à tout éviter. Ne
corrige pas le défaut, le contourne — et repose sur une discipline humaine qui, par nature, finira
par céder. À considérer comme une mesure d'attente, pas comme une parade.

**M. Alerter sur la divergence.** Le plus petit changement utile, dans l'esprit de la règle
« aucun échec silencieux » : quand `notifypayment` refuse une validation, le courriel envoyé
(`controller.php:2557`) porte déjà l'identifiant de session — il a porté `cs_live_a1Ohez…` le
17 septembre. Personne n'a pu savoir que ce n'était pas le bon. Un message qui dirait aussi
combien de sessions existent pour cette commande chez Stripe, et laquelle est payée, transformerait
ce courriel en diagnostic. Ne répare rien, rend le défaut lisible le jour où il se produit.

**Une remarque sur le tri.** H est indépendante de tout le reste, se fait en quelques minutes et
supprime la moitié du problème ; c'est la seule qui ne demande ni code ni clé. K est la parade de
fond, et elle corrige au passage la parade D du constat de réserve, qui aurait échoué sur ce cas.
J est le socle de K si l'on ne veut pas dépendre de Stripe pour l'inventaire. I et M sont des
garde-fous, l'un préventif et coûteux, l'autre correctif et bon marché. L n'est qu'un répit.
**Aucune d'elles ne demande de modifier VikStripe, et c'est le point : la ligne fautive restera en
place, et toutes ces parades vivent avec.**

---

## Commandes et requêtes exécutées

Toutes en lecture seule, le 17 septembre 2026.

```bash
# État des mu-plugins réellement déployés
ssh sg-linstantcle "ls -la ~/www/linstantcle.ch/public_html/wp-content/mu-plugins/"

# Le troisième rendu, daté à la milliseconde
ssh sg-linstantcle "ls -la --time-style=full-iso ~/www/…/wp-vikstripe/Stripe/1651018164-1818.tx"
ssh sg-linstantcle "cat ~/www/…/wp-vikstripe/Stripe/1651018164-1818.tx"

# Les rendus de la page de paiement par l'agent NitroPack, sur cinq jours
ssh sg-linstantcle "zcat ~/www/linstantcle.ch/logs/linstantcle.ch-2026-09-1*.gz \
  | grep 'ignorenitro' | grep -E 'sid=[0-9]+'"
ssh sg-linstantcle "zcat ~/www/linstantcle.ch/logs/linstantcle.ch-2026-09-16.gz \
  | grep 'sid=1501574577'"
```

```sql
SELECT id, status, FROM_UNIXTIME(ts), total, totpaid, paymcount, payable, idpayment,
       LEFT(SHA2(LOWER(TRIM(custmail)),256),8), lang, sid
  FROM sir_vikbooking_orders WHERE id=1818;
SELECT id, days, total, coupon, refund, tot_taxes, tot_fees, tot_city_taxes
  FROM sir_vikbooking_orders WHERE id=1818;
SELECT paymentlog FROM sir_vikbooking_orders WHERE id IN (1818, 859);
SELECT * FROM sir_vikbooking_ordersrooms WHERE idorder=1818;
SELECT * FROM sir_vikbooking_iva;
SELECT id, name, file, published, setconfirmed, charge, ch_disc, val_pcent
  FROM sir_vikbooking_gpayments ORDER BY id;
SHOW COLUMNS FROM sir_vikbooking_orders LIKE 'total';

SELECT option_id, option_name, option_value, autoload
  FROM sir_options WHERE option_name = 'stripe_order_1818';

-- §3, le décompte demandé
SELECT COUNT(*), ROUND(SUM(total),2), MIN(FROM_UNIXTIME(ts)), MAX(FROM_UNIXTIME(ts))
  FROM sir_vikbooking_orders
 WHERE status='standby' AND paymentlog IS NOT NULL AND paymentlog <> '';
SELECT id, FROM_UNIXTIME(ts), total, totpaid, LEFT(SHA2(LOWER(TRIM(custmail)),256),8),
       (SELECT COUNT(*) FROM sir_options t WHERE t.option_name=CONCAT('stripe_order_',o.id)),
       CHAR_LENGTH(paymentlog)
  FROM sir_vikbooking_orders o
 WHERE status='standby' AND paymentlog IS NOT NULL AND paymentlog <> '' ORDER BY ts;

-- rappel des 137 du §7 du constat de réserve, revérifiés
SELECT CASE WHEN o.paymentlog IS NULL OR o.paymentlog='' THEN 'vide' ELSE 'rempli' END,
       COUNT(*), ROUND(SUM(o.total),2), MIN(FROM_UNIXTIME(o.ts)), MAX(FROM_UNIXTIME(o.ts))
  FROM sir_options t JOIN sir_vikbooking_orders o
    ON o.id = CAST(SUBSTRING(t.option_name,14) AS UNSIGNED)
 WHERE t.option_name LIKE 'stripe_order_%' AND o.status='standby' GROUP BY 1;
SELECT status, COUNT(*) FROM sir_vikbooking_orders GROUP BY status;
```

Aucun `INSERT`, `UPDATE`, `DELETE` ni `ALTER` n'a été exécuté : le compte MySQL est en `SELECT`
seul, et c'est une décision, pas une limitation subie. Aucun réglage Stripe, aucun réglage
NitroPack et aucun fichier de plugin n'a été touché.
