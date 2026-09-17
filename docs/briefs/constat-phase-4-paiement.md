# Constat — phase 4, paiement

Version 1, 17 septembre 2026. Réponse au prompt B4 de `docs/briefs/plan-de-marche.md`.
Documents liés : `constat-phase-0.md` §Q5, `constat-perimetre-tunnel.md` §F,
`constat-reserve-paiement.md`, `sexcape-room-reservation.md` §4.5.

**Ce que ce document établit :** ce que la phase 4 a construit
(`mu-plugins/lme-brands/includes/payment-brand.php`), la portée réelle de la
métadonnée posée sur le paiement Stripe — plus étroite que ce que le brief
demandait —, et pourquoi. **Ce qu'il n'établit pas :** la réserve de paiement
(commande restée en `standby` après un encaissement) — c'est B6, hors
périmètre ici, elle attend une décision de Thomas.

Le greffon VikStripe n'a pas été relu en entier pour cette phase : deux
constats l'avaient déjà lu trois fois. Une seule lecture ciblée a été faite
ici, par `grep` sur `metadata`, `descriptor` et `statement` dans
`.local/wp-vikstripe/stripe.php`, pour trancher une question qu'aucun des
constats précédents n'avait posée : par quel canal une métadonnée par marque
peut-elle atteindre Stripe sans modifier le greffon. Les lignes citées
ci-dessous viennent de cette lecture ciblée, datée du 17 septembre 2026.

---

## 1. Ce qui a été construit

`includes/payment-brand.php`, greffé sur `payment_before_begin_transaction_vikbooking`
(`.local/vikbooking/libraries/adapter/payment/payment.php:329`,
`JPayment::showPayment()`), le levier documenté en Q5 du constat de phase 0.
Ce hook se déclenche avant `beginTransaction()`, donc avant que VikStripe ne
construise la configuration de la session Stripe. Restreint à
`$payment->isDriver('stripe')` : seule passerelle publiée aujourd'hui
(`constat-reserve-paiement.md` §2, `sir_vikbooking_gpayments` id 3), seule
dont le comportement a été vérifié.

Pour chaque paiement Stripe déclenché sur cet écran :

1. Résout la marque de la réservation depuis ses chambres
   (`lme_brands_resolve_brand_for_rooms()`, la même fonction pure que la
   phase 3, désormais partagée via `lme_brands_booking_room_ids()`, déplacée
   de `includes/mail-brand.php` vers `includes/registry.php`). Jamais depuis
   l'hôte de la requête : un paiement peut reprendre une commande créée plus
   tôt, potentiellement visitée depuis un lien qui n'est plus sur le bon
   hôte.
2. Si la marque est indéterminée (chambre inconnue, réservation à marques
   mêlées, aucune chambre lisible) : ne pose rien, journalise en erreur et
   alerte (`payment_brand_undetermined`) — même principe que
   `mail_brand_undetermined` en phase 3, jamais une marque devinée.
3. Si la marque est déterminée : pose une métadonnée de marque (voir
   chapitre 2) et vérifie l'hôte de `return_url`, `error_url` et
   `notify_url`, corrigeant et alertant si l'un des trois ne porte pas déjà
   le bon hôte (voir chapitre 3).

`config/brands.php` : `confirmation_page_id` vaut désormais **845** pour les
deux marques, au lieu du placeholder `0` laissé par la phase 1. Justifié au
chapitre 4.

---

## 2. La métadonnée de marque atterrit sur la session, pas sur le PaymentIntent

Le brief (§4.5) demande : « des métadonnées de marque sur chaque
`PaymentIntent` plus un `statement_descriptor_suffix` par marque ». Ce que le
greffon permet réellement, sans le modifier, est plus étroit.

### Le canal qui existe : `tn_metadata`

```
.local/wp-vikstripe/stripe.php:454
    $transaction_metadata = $this->get('tn_metadata', []);
.local/wp-vikstripe/stripe.php:468-471
    $config['metadata'] = array_merge(
        $config['metadata'] ?? [],
        $transaction_metadata,
    );
```

`tn_metadata` est lu sur l'objet de paiement (`$this->get()`, donc
`$payment->order`) et fusionné dans `$config['metadata']` — la métadonnée de
la **session Stripe Checkout** que `createSession()` construit
(`checkout->sessions->create($config)`, `stripe.php:497`,
`constat-reserve-paiement.md` §1 étape 6). C'est le canal que
`lme_brands_set_payment_metadata()` utilise, avec les clés `lme_brand`,
`lme_room_ids` et `lme_booking_id`.

**Stripe ne copie jamais automatiquement la métadonnée d'une session sur le
PaymentIntent qu'elle crée.** Les deux sont des objets distincts avec leurs
propres métadonnées. Pour qu'une métadonnée atterrisse sur le PaymentIntent,
il faut la poser sur `payment_intent_data.metadata` au moment de la création
de la session — un champ distinct.

### Le seul chemin vers `payment_intent_data.metadata` est un réglage global, pas un réglage par marque

```
.local/wp-vikstripe/stripe.php:416-419
    'payment_intent_data' => [
        'capture_method'  => $capture_method,
        'description'     => __(sprintf("Reservation Number: %s", $this->get('oid')), "vikstripe"),
    ],
.local/wp-vikstripe/stripe.php:456-466
    // set the metadata for the payment intent
    if ($this->getParam('transaction_metadata'))
    {
        $config['payment_intent_data']['metadata'] = [
            'transaction_metadata' => $this->getParam('transaction_metadata')
        ];

        $config['metadata'] = [
            'transaction_metadata' => $this->getParam('transaction_metadata')
        ];
    }
```

`getParam()` lit la configuration de la passerelle
(`sir_vikbooking_gpayments.params`, JSON, `constat-reserve-paiement.md` §0 —
`skipbtn`, `paytype`, `currency`, etc. y vivent déjà) : **une valeur unique
pour toute l'installation**, dans la même ligne que les deux marques
partagent (id 3, « Pay (now or later) »). Même en le renseignant, ce
paramètre ne peut porter qu'une seule chaîne, jamais une valeur qui dépende
de la marque de la réservation en cours — il est lu une fois par
transaction, mais sa valeur ne varie pas d'une transaction à l'autre.

**Conséquence : il n'existe aucun canal, dans ce greffon, pour poser une
métadonnée de marque sur le `payment_intent_data` sans le modifier**, ce que
la règle absolue n°1 interdit. `lme_brands_set_payment_metadata()` pose donc
la métadonnée là où elle peut réellement atterrir — la session — et le
fichier le documente en tête plutôt que de laisser croire que le
PaymentIntent la porte.

### `statement_descriptor_suffix` : aucun canal du tout

```bash
grep -n -i "descriptor\|statement" stripe.php vikbooking/stripe.php
# (aucun résultat)
```

Le mot n'apparaît nulle part dans le greffon. Ni la configuration de session,
ni les appels PaymentIntent directs des paiements différés (`stripe.php:955-980`,
hors du chemin du tunnel — paiement programmé, table `sir_vikbooking_payschedules`
vide, `constat-reserve-paiement.md` §4) ne le lisent. **Il n'y a rien à
brancher : le levier n'existe pas.** Ce n'est pas une question d'endroit où
le poser, contrairement à la métadonnée — c'est une fonctionnalité absente du
greffon.

### Ce que cela veut dire pour le critère de recette n°8

> « Le `PaymentIntent` porte la métadonnée de marque et le libellé attendu. »

Tel qu'écrit, ce critère ne peut pas être satisfait sans modifier le greffon
VikStripe. Ce qui est vrai après cette phase :

- la **session Stripe Checkout** porte la métadonnée de marque (`lme_brand`,
  `lme_room_ids`, `lme_booking_id`) — visible dans le tableau de bord Stripe
  sur l'objet Session, filtrable par l'API sur cet objet ;
- le **PaymentIntent** ne porte aucune métadonnée posée par ce mu-plugin ;
- aucun libellé de relevé bancaire n'est posé, sur aucun des deux objets.

**Décision à prendre par Thomas**, dans l'ordre de préférence :

1. Accepter la métadonnée de session comme suffisante pour la ventilation
   comptable visée par le brief — une session Stripe Checkout est liée 1:1 à
   son PaymentIntent, et les rapports Stripe permettent de filtrer sur les
   deux objets. Le libellé de relevé bancaire reste alors non livré.
2. Poser la métadonnée de marque a posteriori sur le PaymentIntent (et un
   `statement_descriptor_suffix`) via un appel à l'API Stripe, après la
   confirmation de la commande. Cela demande la clé secrète Stripe côté
   serveur, ce qui déplace ce travail dans le même périmètre que B6 (réserve
   de paiement) : hors de cette phase, et à instruire séparément si retenu.
3. Demander à E4J (éditeur de VikStripe) d'ajouter un canal pour
   `payment_intent_data.metadata` et `statement_descriptor_suffix`
   dynamiques par transaction — au-delà de ce que ce dépôt peut décider.

Rien de tout cela n'est tranché ici. Ce constat pose le fait, il ne choisit
pas la parade.

---

## 3. L'URL de retour : un filet, pas une réparation

`payment_before_begin_transaction_vikbooking` est aussi le levier documenté
en Q5 pour l'URL de retour. Établi dans ce même constat : `return_url`,
`error_url` et `notify_url` sont construites par le cœur de Vik sur
`JUri::root()` → `home_url()`, filtrée par `includes/url-rewrite.php`
(phase 1) pendant la **même requête** qui affiche la page de paiement — donc
déjà sur le bon hôte au moment où ce nouveau hook s'exécute, dans le cas
normal.

`lme_brands_correct_payment_urls()` ne suppose pas que c'est vrai : elle
compare l'hôte de chacune des trois valeurs à celui de la marque résolue, et
ne corrige que si les deux diffèrent — auquel cas elle journalise en erreur
et alerte (`payment_url_host_corrected`), parce qu'une correction réelle ici
signalerait un trou dans le filtrage d'hôte de la phase 1, pas un
fonctionnement normal. Dans l'usage attendu, ce code ne s'exécute jamais : il
n'a de valeur que le jour où l'hypothèse ci-dessus s'avère fausse, et c'est
précisément pour ce jour-là qu'il existe (chapitre 6 du brief : aucun échec
silencieux).

`cancel_url` de la session Stripe (`stripe.php:401`,
`$this->get('return_url') . "&payment=canceled"`) est le champ qui bénéficie
le plus directement de cette garantie : c'est le seul des trois URL du cœur
que VikStripe utilise réellement pour construire la session
(`success_url` vaut `notify_url`, et le retour effectif après paiement est
reconstruit par le greffon lui-même sur `home_url()` courant, indépendamment
de `return_url`/`error_url` — établi en Q5 et confirmé sans changement par
`constat-reserve-paiement.md` §0, écart 1).

---

## 4. La page de confirmation

`confirmation_page_id` vaut **845** pour les deux marques dans
`config/brands.php`. Ce n'est pas une estimation : `constat-perimetre-tunnel.md`
§4 (entrée A3) établit que la page 845 est l'unique page WordPress, sur les
quatre hôtes de cette installation, qui porte les vues `booking`,
`precheckin`, `revstay` et la tâche `notifypayment` — détail de réservation,
paiement, retour Stripe, confirmation et page d'erreur y aboutissent tous,
quelle que soit la marque.

Il n'y a donc pas de « page de confirmation par marque » à créer : il y a une
seule page, dont l'apparence varie déjà par hôte depuis la phase D2
(`themes/astra-child/inc/sexcaperoom-tunnel.php`, chargé uniquement quand la
requête est sur l'hôte `sexcaperoom`). Renseigner `confirmation_page_id`
referme le placeholder laissé par la phase 1 et donne au registre une valeur
correcte et vérifiée pour tout code futur qui voudrait s'appuyer dessus (par
exemple le verrou d'hôte du chantier G, qui liste déjà 845 dans son propre
récapitulatif).

Reste ouvert, et hors de cette phase : le critère de recette 7bis (« la même
page côté sexcaperoom.ch et côté tunnel »), qui est D3 dans le plan de
marche, et qui attend qu'une page du tunnel soit visible en conditions
réelles pour être comparée.

---

## 5. Ce qui n'a pas été touché

- La réserve de paiement (`constat-reserve-paiement.md`) : aucune commande
  en `standby` n'est réconciliée par ce fichier, aucun appel à l'API Stripe
  n'est fait. C'est B6, elle attend une décision de Thomas.
- Le greffon VikStripe : aucun fichier modifié, conformément à la règle
  absolue n°1.
- Aucune valeur de configuration Stripe (clé secrète, clé publique) n'a été
  lue ni journalisée : ce fichier ne connaît que ce que l'objet de paiement
  expose par `get()`/`set()`, jamais `sir_vikbooking_gpayments.params` en
  entier.

---

## 6. Vérification manuelle à faire, sur `staging10.linstantcle.ch`

Aucune de ces vérifications n'a pu être faite ici (compte MySQL en lecture
seule, aucun accès Stripe, rien déployé). À faire avant de considérer B4
recettée, avec un mode de paiement Stripe réel en environnement de test :

1. Réserver une chambre Sexcape Room (ex. chambre 4) depuis
   `reservation.sexcaperoom.ch`, jusqu'à l'écran de paiement. Dans le
   tableau de bord Stripe (mode test), ouvrir la **session Checkout**
   correspondante : vérifier que ses métadonnées portent `lme_brand:
   sexcaperoom`, `lme_room_ids`, `lme_booking_id`. Vérifier que le
   PaymentIntent associé, lui, n'en porte aucune — comportement attendu,
   pas une anomalie, voir chapitre 2.
2. Vérifier qu'aucune ligne `[lme-brands] [ERROR] [payment_url_host_corrected]`
   n'apparaît dans `debug.log` pour ce parcours — sa présence signalerait un
   trou dans le filtrage d'hôte de la phase 1, à investiguer avant toute
   autre chose.
3. Répéter côté `linstantcle.ch` avec une chambre de cette marque : mêmes
   vérifications, `lme_brand: linstantcle`.
4. Annuler le paiement (bouton retour Stripe) : vérifier que le client
   revient sur la page 845 de l'hôte de départ, jamais celle de l'autre
   marque — c'est ce que `cancel_url` corrigé au besoin doit garantir.
5. Composer une réservation à marques mêlées dans l'administration de Vik
   (ex. chambres 8 et 9) et lui déclencher un paiement Stripe manuel si
   l'écran le permet : vérifier `[lme-brands] [ERROR] [payment_brand_undetermined]`
   dans `debug.log`, et qu'aucune métadonnée `lme_*` n'apparaît sur la
   session créée.
