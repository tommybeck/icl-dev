# Constat, phase 0

Réponses aux six questions du chapitre 7 du brief `sexcape-room-reservation.md`.
Règle : chaque réponse cite la preuve qui l'établit. Une question sans preuve se déclare non tranchée, jamais déduite.

**Source lue pour Q2, Q4, Q5 et Q6 :** copie SFTP de Vik Booking **1.8.14** (`vikbooking.php:6`), déposée dans `.local/vikbooking/` et `.local/vikchannelmanager/`. Le `.local/README.md` annonçait `.local/vik-source/` : le dossier porte en fait le nom du plugin. **Source lue pour la réserve Stripe de Q5 :** copie SFTP du greffon VikStripe **2.2.4** (`vikstripe.php:5`), déposée dans `.local/wp-vikstripe/`. Lecture seule, rien n'a été modifié. Tous les chemins ci-dessous sont relatifs à `.local/`, toutes les lignes ont été relues une à une.

---

## Q1. Hébergement, les deux domaines peuvent-ils partager une installation ?

**Tranchée, 9 septembre 2026, par Thomas dans SiteGround Site Tools.**

linstantcle.ch et sexcaperoom.ch sont sur le **même compte SiteGround**, mais déclarés comme **deux sites distincts** : racines séparées dans le système de fichiers, installations WordPress séparées.

Conséquence pour l'architecture : le palier retenu tient. `reservation.sexcaperoom.ch` s'ajoute en **domaine garé** sur le site linstantcle.ch, et est donc servi par l'installation qui porte Vik. Même compte veut dire aucune démarche inter-comptes, ni pour le domaine ni pour le certificat.

**Complément du 9 septembre 2026, Thomas.** Le DNS de sexcaperoom.ch est chez **name.com**, et **aucun enregistrement générique `*` n'existe**. Sans joker, `reservation.sexcaperoom.ch` ne résout que si un enregistrement est créé explicitement : aucun conflit possible avec le site sexcaperoom.ch. **Q1 est close.**

Mise en oeuvre, dans l'ordre, la première étape conditionnant les suivantes :

1. name.com, créer un enregistrement `A` pour `reservation` pointant sur l'adresse IP du serveur **`gfram1004.siteground.biz`**, qui porte linstantcle.ch et Vik. Les deux sites vivent sur des machines distinctes, sexcaperoom.ch étant sur `gvam1277.siteground.biz` : viser la mauvaise IP ne produit pas de panne mais sert silencieusement le mauvais site.
2. SiteGround, sur le site **linstantcle.ch** et non sur celui de sexcaperoom.ch, ajouter `reservation.sexcaperoom.ch` en domaine garé.
3. Émettre le certificat Let's Encrypt, ce qui exige que le nom résolve déjà : compter la propagation DNS.

---

## Q2. Vik envoie-t-il ses e-mails par `wp_mail`, et quels points d'accroche expose-t-il ?

**Tranchée. Oui, tout part par `wp_mail`, et le point d'accroche nécessaire existe.**

### Le chemin d'envoi

Un seul point de sortie, sans SMTP propre :

```
libraries/adapter/mail/mail.php:201
    return wp_mail($to, $this->subject, $this->body, $headers, $this->attachments);
```

La chaîne complète, du métier jusqu'à l'envoi :

| Étape | Fichier et ligne |
|---|---|
| `VikBooking::sendBookingEmail($bid, $for, …)` | `vikbooking/site/helpers/lib.vikbooking.php:6069` |
| `…getMailer()->send($mail)` | `vikbooking/site/helpers/lib.vikbooking.php:6505` |
| `VBOPlatformOrgWordpressMailer::send()` | `vikbooking/admin/helpers/src/platform/org/wordpress/mailer.php:28` |
| `VBOMailServicePhpmailer::send()` | `vikbooking/admin/helpers/src/mail/service/phpmailer.php:83` |
| `JFactory::getMailer()` retourne `new JMail` | `vikbooking/libraries/adapter/factory/factory.php:196` |
| `wp_mail(...)` | `vikbooking/libraries/adapter/mail/mail.php:201` |

Le nom de classe `VBOMailServicePhpmailer` induit en erreur : sous WordPress il n'instancie pas PHPMailer mais le shim `JMail`, qui délègue à `wp_mail`. Vik ne configure aucun SMTP pour les e-mails de réservation — `useSmtp()` (`mail.php:416`) n'est appelé que par le pilote de facturation électronique italien (`vikbooking/admin/helpers/einvoicing/drivers/agenzia_entrate.php:2666`). **La délivrabilité des e-mails de réservation est donc entièrement celle de `wp_mail` au niveau du site**, ce qui confirme le besoin d'un service d'envoi transactionnel authentifié décrit au §4.4 du brief. Il n'y a rien à contourner dans Vik pour cela.

Il existe bien un `mail()` brut dans l'arbre (`vikbooking/site/class/email_message.php:403`), mais aucun fichier vivant ne référence cette classe : hors chemin.

### Le point d'accroche, et ce qu'il donne

```
vikbooking/site/helpers/lib.vikbooking.php:6502
    VBOFactory::getPlatform()->getDispatcher()->trigger('onBeforeSendBookingMailVikBooking', [$who, $booking, $mail]);
vikbooking/site/helpers/lib.vikbooking.php:6505
    $result = VBOFactory::getPlatform()->getMailer()->send($mail) || $result;
```

Le nom Joomla est traduit en nom WordPress par `getHook()`, `vikbooking/admin/helpers/src/platform/org/wordpress/dispatcher.php:73-89` : on retire `on`, on retire `vikbooking`, on le remet en tête, on insère un `_` à chaque bosse de casse, on passe en minuscules. `onBeforeSendBookingMailVikBooking` devient donc :

**`vikbooking_before_send_booking_mail`**, avec les arguments `[$who, $booking, $mail]`, émis par `do_action_ref_array` (`dispatcher.php:32`).

Second point d'accroche, plus bas et sans contexte métier : `onBeforeSendMail` → **`vikbooking_before_send_mail`**, `vikbooking/admin/helpers/src/platform/org/wordpress/mailer.php:52`, argument `[$mail]` seul. Il vaut pour tous les e-mails de Vik, y compris devis et messagerie.

### La marque est résoluble au moment de l'envoi

Oui, et c'est le point qui conditionnait tout le §4.4 du brief.

```
vikbooking/site/helpers/lib.vikbooking.php:6077-6079
    $q = "SELECT * FROM `#__vikbooking_orders` WHERE `id`=" . (int)$bid . ";";
    $dbo->setQuery($q);
    $booking = $dbo->loadAssoc();
```

```
vikbooking/site/helpers/lib.vikbooking.php:6102-6104
    $q = "SELECT `or`.*,`r`.`id` AS `r_reference_id`,`r`.`name`,… FROM `#__vikbooking_ordersrooms` AS `or`,`#__vikbooking_rooms` AS `r` WHERE `or`.`idorder`=" . $booking['id'] . " AND `or`.`idroom`=`r`.`id` ORDER BY `or`.`id` ASC;";
    $dbo->setQuery($q);
    $ordersrooms = $dbo->loadAssocList();
```

`$booking['id']` est passé au hook. **Les identifiants de chambre ne le sont pas** : `$rooms` existe dans la portée de la fonction mais ne figure pas dans les arguments de `trigger` à la ligne 6502. Notre greffon devra donc relire `sir_vikbooking_ordersrooms` par `idorder` pour obtenir `idroom`, puis résoudre la marque par le registre. C'est une requête, pas un obstacle — et cela reste conforme au garde-fou « déduire la marque de l'identifiant de chambre ».

### L'expéditeur, et comment le remplacer

Adresse et nom actuels :

```
vikbooking/site/helpers/lib.vikbooking.php:6477
    $adsendermail = VBOFactory::getConfig()->get('senderemail');
vikbooking/site/helpers/lib.vikbooking.php:6480-6484
    $mail = new VBOMailWrapper([
        'sender'      => [$adsendermail, $ftitle],
        …
        'reply'       => !empty($force_replyto) ? $force_replyto : $adsendermail,
```

`$ftitle` vient de `getFrontTitle()` (`lib.vikbooking.php:6138`), lu dans `sir_vikbooking_texts` où `param='fronttitle'`. Le paramètre `senderemail` se règle en administration (`vikbooking/admin/views/config/tmpl/default_one.php:185`, enregistré `vikbooking/admin/controller.php:8336`). Ce sont des **valeurs uniques pour toute l'installation** : elles ne peuvent pas porter deux marques.

L'objet passé au hook est mutable et expose un mutateur :

```
vikbooking/admin/helpers/src/mail/wrapper.php:166
    public function setSender($address, $name = null)
```

Le commentaire de Vik le dit explicitement à `lib.vikbooking.php:6498` : « *VBOMailWrapper is the $mail object and its setter methods can modify the mail data.* »

Piège à retenir, `wrapper.php:182` : `'name' => $address != $name ? $name : null` — passer la même chaîne en adresse et en nom fait disparaître le nom.

### Correction à apporter au brief, §4.4

Le brief prévoit de « désactiver les e-mails clients de Vik pour les réservations directes et envoyer les nôtres ». **Aucun réglage natif ne permet cette désactivation.** Le seul paramètre voisin est `sendemailwhen`, et il n'offre que deux valeurs :

```
vikbooking/admin/views/config/tmpl/default_four.php:39
    <select name="sendemailwhen"><option value="1">…confirmées et en attente…</option><option value="2">…confirmées seulement…</option></select>
vikbooking/admin/controller.php:8538
    $psendemailwhen = $psendemailwhen > 1 ? 2 : 1;
```

Il ne fait que couper l'e-mail des réservations **en attente** (`lib.vikbooking.php:6415`). Une réservation confirmée déclenche toujours l'e-mail client. Et le hook `vikbooking_before_send_booking_mail` est un `do_action` : il ne retourne rien, donc **il ne peut pas annuler un envoi**.

La voie praticable est meilleure que celle du brief : **ne rien désactiver, et réécrire le message en vol** sur `vikbooking_before_send_booking_mail` — expéditeur, adresse de réponse, objet et contenu — via les mutateurs de `VBOMailWrapper`. Un seul chemin d'envoi au lieu de deux, donc pas de risque de double e-mail, et les pièces jointes iCal de Vik sont conservées. Le périmètre strict du brief reste tenable : on ne réécrit que si `$who` vaut `guest` et que la réservation n'est pas d'origine OTA (`$booking['channel']`).

Si un jour il faut vraiment supprimer un envoi plutôt que le réécrire, le levier est le filtre `wp_mail` du cœur WordPress, hors de Vik.

### Les émetteurs d'e-mails clients, pour mémoire

| Fichier et ligne | Destinataires | Contexte |
|---|---|---|
| `vikbooking/site/controller.php:1241` | guest, admin | réservation directe **confirmée** |
| `vikbooking/site/controller.php:1615` | guest, admin | réservation directe en attente de paiement |
| `vikbooking/site/controller.php:2486` | guest, admin | paiement reçu et validé |
| `vikbooking/site/controller.php:2972` | guest | annulation |
| `vikbooking/site/controller.php:1454` | guest, admin | réservation modifiée |
| `vikbooking/admin/controller.php:3917` | guest | renvoi manuel depuis l'administration |
| `vikchannelmanager/site/controller.php:1672` | guest | réservation OTA importée |

Un seul filtre permet de supprimer l'envoi sur le chemin du paiement : `onPaymentReceivedShouldSendNotifications` → **`vikbooking_payment_received_should_send_notifications`**, `vikbooking/site/controller.php:2479`. Il coupe l'e-mail client **et** l'e-mail administrateur **et** le SMS d'un bloc : trop grossier pour notre usage, mais utile à connaître.

**Il n'existe aucun hook dédié à l'annulation** ni de hook `onAfterCreateBookingRecord` côté frontal. Les signaux disponibles sur le cycle de vie sont `vikbooking_before_create_booking_record` (`site/controller.php:1114`, avant l'insertion, sans identifiant) et l'historique `vikbooking_after_save_booking_history` (`vikbooking/site/helpers/history.php:695`), dont l'enregistrement porte `idorder` (`history.php:657`).

---

## Q3. Les liaisons de disponibilité reflètent-elles les groupes attendus ?

**Tranchée, 9 septembre 2026.** Requête exécutée dans phpMyAdmin :

```sql
SELECT * FROM dbvkhvlostfyua.sir_vikbooking_calendars_xref;
```

| id | mainroom | childroom |
|---|---|---|
| 8 | 3 | 2 |
| 31 | 4 | 2 |
| 32 | 2 | 4 |
| 37 | 1 | 10 |
| 38 | 1 | 7 |
| 43 | 7 | 10 |
| 44 | 7 | 1 |
| 47 | 10 | 1 |
| 48 | 10 | 7 |

**Villa Entracte, complète et symétrique.** Les six paires orientées entre 1, 7 et 10 sont présentes : 1→7, 7→1, 1→10, 10→1, 7→10, 10→7.

**Villa Aparté, complète et symétrique.** 2→4 et 4→2.

**Chambres 8 et 9, aucune liaison**, conformément à leur indépendance. Chambres de test 5 et 6, aucune liaison non plus.

**Conclusion : le risque de double vente d'un même espace n'existe pas aujourd'hui.**

**Anomalie relevée, ligne id 8 : `mainroom = 3`.** La chambre 3 n'existe pas parmi les neuf chambres de `sir_vikbooking_rooms` (1, 2, 4, 5, 6, 7, 8, 9, 10). Ligne orpheline, asymétrique de surcroît puisque 2→3 est absente. Vestige d'une chambre supprimée. À traiter hors de ce chantier : d'abord confirmer qu'aucune réservation ne référence la chambre 3, puis supprimer la ligne et consigner le geste dans `journal-vik.md`.

---

## Q4. Vik sait-il filtrer nativement les chambres offertes ?

**Tranchée. Partiellement, et jamais de façon opposable — mais le bon point d'accroche existe.**

### Ce qui existe nativement

Vik n'enregistre qu'**un seul shortcode** :

```
vikbooking/vikbooking.php:228
    add_shortcode('vikbooking', function($atts, $content = null)
```

Ses attributs ne sont pas codés en dur : ils sont lus dans le fichier XML de la vue visée (`vikbooking.php:243-252`), puis injectés dans la requête (`vikbooking.php:271-273`). D'où, pour ce qui nous occupe :

| Vue | Attribut de filtrage | Déclaration |
|---|---|---|
| `vikbooking` (formulaire de recherche) | `category_id`, catégorie **unique** | `vikbooking/site/views/vikbooking/tmpl/default.xml:16` |
| `roomslist` (catalogue) | `category_id`, catégorie **unique** | `vikbooking/site/views/roomslist/tmpl/default.xml:16` |
| `availability` (calendrier) | `room_ids`, **liste d'identifiants**, multiple | `vikbooking/site/views/availability/tmpl/default.xml:15` |
| `roomdetails` | `roomid`, une seule chambre | `vikbooking/site/views/roomdetails/tmpl/default.xml:16` |

Les catégories existent bien (`vikbooking/sql/install.mysql.utf8.sql:114-119`), mais **sans table de liaison** : l'appartenance est une chaîne dénormalisée sur la chambre, `sir_vikbooking_rooms`.`idcat`, terminée par des points-virgules (`sql/install.mysql.utf8.sql:87`, écrite `vikbooking/admin/controller.php:1183-1186`).

Il existe enfin un interrupteur global par chambre, `avail` (`sql/install.mysql.utf8.sql:91`), appliqué sur toutes les requêtes frontales. C'est du tout ou rien à l'échelle du site : inutilisable pour montrer une chambre sur un hôte et la cacher sur l'autre.

### Les deux limites, décisives

**1. La recherche réservable ne sait pas filtrer par liste d'identifiants.** La requête qui construit les résultats n'a ni clause de catégorie ni clause d'identifiant :

```
vikbooking/site/views/search/view.html.php:458
    $q = "SELECT `p`.*,… FROM `#__vikbooking_dispcost` AS `p`, `#__vikbooking_rooms` AS `r`, `#__vikbooking_prices` AS `rp` WHERE `p`.`days`=…" AND `p`.`idroom`=`r`.`id` AND `p`.`idprice`=`rp`.`id` AND `r`.`avail`='1' AND (…) ORDER BY …
```

Le filtrage par catégorie se fait **après coup, en PHP**, en retirant des lignes du tableau (`view.html.php:549-554` pour une catégorie, `:562-572` pour plusieurs). Le terme `room_ids` n'apparaît nulle part dans cette vue — vérifié : aucune occurrence.

**2. Un attribut de shortcode est un défaut, pas une contrainte.** L'injection passe par `def()` :

```
vikbooking/libraries/adapter/input/input.php:259-265
    public function def($name, $value)
    {
        if (!$this->exists($name))
        {
            $this->set($name, $value);
        }
    }
```

`def()` ne pose la valeur **que si la requête ne la contient pas déjà**. Un paramètre GET ou POST du même nom écrase donc l'attribut du shortcode. Autrement dit : **une URL fabriquée à la main contourne n'importe quel filtrage par shortcode.**

C'est la démonstration, dans le code, de ce que le §4.3 du brief posait par prudence. La couche 2, la garde sur le chemin de création de réservation, n'est pas une ceinture de sécurité : c'est le seul mécanisme réellement opposable. Le critère de recette n° 3 est fondé.

### Le point d'accroche propre

Vik expose un filtre conçu exactement pour retirer une chambre des résultats de recherche :

```
vikbooking/site/views/search/view.html.php:590-595
    $customFiltering = VBOFactory::getPlatform()->getDispatcher()->filter('onApplySearchResultsFiltering', [$tt[0], $resultFilters]);
    if (in_array(false, $customFiltering, true)) {
        // listing was requested to be unset
        unset($arrtar[$kk]);
        continue;
    }
```

`filter()` est un vrai `apply_filters_ref_array` (`dispatcher.php:44-50`). Après traduction du nom, le hook WordPress est :

**`vikbooking_apply_search_results_filtering`**

Signature : `($valeur, array $room, array $resultFilters)` — `filter()` insère `null` en tête des arguments (`dispatcher.php:48`). Retourner **exactement `false`** (comparaison stricte) retire l'annonce. `$room` est la ligne jointe `dispcost` + `rooms`, donc `idroom` et `r_reference_id` sont disponibles pour interroger le registre.

C'est le mécanisme prévu pour la couche 1 de la phase 2, sans toucher un fichier de Vik.

**Réserve à porter au brief :** ce filtre ne couvre **que la vue `search`**. Aucun hook n'existe dans `roomslist`, `availability`, `roomdetails` ni `searchsuggestions` — vérifié, `getDispatcher()` n'y apparaît pas. Si l'un de ces écrans est publié sur l'hôte Sexcape Room, il faudra le traiter autrement : shortcode paramétré par marque pour la présentation, et la garde de la couche 2 pour l'étanchéité réelle.

---

## Q5. L'URL de retour de paiement vient-elle de la requête ou de l'option `siteurl` ?

**Tranchée pour le cœur de Vik : ni l'une ni l'autre — elle vient de `home_url()`, donc de l'option `home`. Avec une réserve sérieuse sur Stripe, exposée plus bas.**

### La construction

```
vikbooking/site/views/booking/tmpl/default.php:928-930
    $return_url = JUri::root() . "index.php?option=com_vikbooking&view=booking&sid=" … ;
    $error_url  = JUri::root() . "index.php?option=com_vikbooking&view=booking&sid=" … ;
    $notify_url = JUri::root() . "index.php?option=com_vikbooking&task=notifypayment&sid=" … "&tmpl=component";
```

Puis passage par le routeur de permaliens (`default.php:937-942`), rangement dans la commande (`default.php:981-983`) et remise à la passerelle (`default.php:1041`). Même construction sur le chemin de validation, `vikbooking/site/controller.php:2173-2175`.

### La primitive

```
vikbooking/libraries/adapter/uri/uri.php:140-147
    public static function root($pathonly = false, $path = null)
    { … $uri = static::base($pathonly);
vikbooking/libraries/adapter/uri/uri.php:119-125
    public static function base($pathonly = false)
    { … static::$base[$sign] = new static(rtrim(home_url('', $pathonly ? 'relative' : null), '/') . '/');
```

`JUri::root()` ne contient **aucune donnée de requête**. `JRoute::_()` aboutit soit à `get_permalink()` (`vikbooking/libraries/adapter/application/route.php:210` et `:214`), soit en repli à `JUri::root()` (`route.php:69`). `$_SERVER['HTTP_HOST']` n'apparaît sur aucun chemin de paiement, de réservation ou d'e-mail : les seules occurrences dans le plugin sont le tableau de bord d'administration (`vikbooking/admin/views/dashboard/view.html.php:40`) et la vérification de licence (`vikbooking/admin/helpers/vikbooking.php:587`, `:1234`).

Les passerelles ne construisent aucune URL, elles lisent celle qu'on leur a donnée :

```
vikbooking/libraries/adapter/payment/payment.php:66
    $this->order  = new JObject($order);
vikbooking/libraries/adapter/payment/payment.php:147-150
    public function get($key, $def = null)
    {
        return $this->order->get($key, $def);
    }
```

Les quatre passerelles livrées suivent ce contrat, par exemple `vikbooking/admin/payments/paypal.php:121` : `$uri = JUri::getInstance($this->get('return_url'));`

**Donc filtrer `option_home` atteint bien l'URL de retour.** C'est la réponse à la question posée.

### Trois réserves, dont une lourde

**1. La passerelle Stripe n'est pas dans cette copie.** `admin/payments/` ne contient que `bank_transfer.php`, `offline_credit_card.php`, `paypal.php` et `paypal_checkout.php`. Une recherche de `stripe` sur tout l'arbre ne remonte que des classes CSS `table-striped`, un champ d'identité pour le pré-enregistrement (`admin/helpers/src/checkin/paxfield/type/stripeidentity.php`) et une ligne de journal des versions (`changelog.md:215`). Stripe est un **greffon distinct**, chargé à l'exécution par le hook documenté `load_payment_gateway_vikbooking` (`vikbooking/libraries/adapter/payment/dispatcher.php:83`, décrit dans `vikbooking/libraries/hooks.md:123-141`).

Le cœur lui remet `return_url`, `error_url` et `notify_url` à la construction de la commande. **Tranché le 11 septembre 2026, greffon relu dans `.local/wp-vikstripe/` : sur le chemin qui compte, il reconstruit l'URL au lieu d'honorer celle du cœur.** Trois pièces, dans l'ordre réel d'exécution :

**a. La session Stripe Checkout ne pointe pas `success_url` vers `return_url`.** Vérifié sur les deux branches (capture/autorisation et hors-session) :

```
.local/wp-vikstripe/stripe.php:399-401
    'submit_type' => …,
    'success_url' => $this->get('notify_url'),
    'cancel_url'  => $this->get('return_url') . "&payment=canceled",
.local/wp-vikstripe/stripe.php:447-450
    'mode'        => 'setup',
    'currency'    => …,
    'success_url' => $this->get('notify_url'),
    'cancel_url'  => $this->get('return_url'),
```

`success_url` vaut `notify_url`, pas `return_url`. Seul `cancel_url` honore `return_url` tel quel.

**b. `complete()`, la méthode qui utiliserait `return_url` en cas de succès et `error_url` en cas d'échec, n'est jamais atteinte pour VikBooking.**

```
.local/wp-vikstripe/stripe.php:667-688
    protected function complete($res)
    {
        …
        if ($res) { $url = $this->get('return_url'); … }
        else      { $url = $this->get('error_url'); … }
        JFactory::getApplication()->redirect($url);
        exit;
    }
```

Le cœur (`afterValidation()`) l'appelle en dernier, après deux actions :

```
.local/vikbooking/libraries/adapter/payment/payment.php:512
    do_action_ref_array($this->getHook('payment_on_after_validation'), array(&$this, $res));
.local/vikbooking/libraries/adapter/payment/payment.php:524
    do_action_ref_array($this->getDriverHook('payment_on_after_validation'), array(&$this, $res));
.local/vikbooking/libraries/adapter/payment/payment.php:527
    $this->complete($res);
```

**c. VikBooking se greffe justement sur la première de ces deux actions et redirige avant que `complete()` ne s'exécute :**

```
.local/wp-vikstripe/vikbooking/stripe.php:223-242
    add_action('payment_on_after_validation_vikbooking', function(&$payment, $res)
    {
        if (!$payment->isDriver('stripe')) { return; }
        $url = 'index.php?option=com_vikbooking&view=booking&sid=' . $payment->get('sid') . '&ts=' . $payment->get('ts');
        $model  = JModel::getInstance('vikbooking', 'shortcodes', 'admin');
        $itemid = $model->best(array('booking'));
        if ($itemid) { $url = JRoute::_($url . '&Itemid=' . $itemid, false); }
        JFactory::getApplication()->redirect($url);
        exit;
    }, 10, 2);
```

Le commentaire du greffon le dit lui-même : « VikBooking doesn't have a return_url to use within the afterValidation method. Use this hook to construct it ». `$payment->get('return_url')` n'est **jamais lu** ici. `$res` — succès ou échec — n'est **jamais testé** non plus : le client est toujours renvoyé vers la vue `booking` du cœur pour ce `sid`/`ts`, qui affiche elle-même l'état de la réservation. Le `exit` de ce hook empêche `complete()` de s'exécuter : `return_url` et `error_url`, tels que le cœur les a remis, ne servent à rien sur ce chemin.

**Verdict : le greffon reconstruit, il n'honore pas — sauf pour `cancel_url`, seul point où `return_url` est repris tel quel.**

**Conséquence pour la phase 4.** La reconstruction passe par `JRoute::_()`, qui redescend vers `JUri::root()` puis `home_url()` (établi en Q5) : elle reste donc couverte par le même filtrage `option_home` que le reste du tunnel, à condition que ce filtre s'applique à la requête qui déclenche ce hook — ce qui est le cas, puisque c'est le navigateur du client qui atterrit là après Stripe, sur l'hôte que `notify_url` portait déjà à la construction de la commande. Le levier de repli `payment_before_begin_transaction_vikbooking` (documenté en Q5) n'a donc pas d'utilité sur ce chemin précis, puisqu'il agit sur `return_url`, une valeur que ce chemin ignore ; il resterait pertinent uniquement pour `cancel_url`.

**Second point pour la phase 4 : ce n'est pas une page de confirmation par marque qui est servie, mais la vue `booking` native de Vik pour ce `sid`/`ts`.** Le registre du §4.2 du brief prévoit un identifiant de page de confirmation par marque ; ce hook redirige toujours vers la même vue, quelle que soit la marque, sans jamais consulter le registre. Deux voies possibles pour la phase 4, à trancher alors et pas ici : habiller cette vue par hôte (chantier D), ou se greffer sur le même événement `payment_on_after_validation_vikbooking` à une priorité inférieure à 10 pour rediriger ailleurs avant que ce greffon n'agisse et n'appelle `exit`.

**2. `option_siteurl` doit être filtrée aussi, pour une autre raison.** Les appels AJAX du tunnel passent par `admin_url()`, qui est construit sur `siteurl` et non sur `home` :

```
vikbooking/admin/helpers/src/platform/org/wordpress/uri.php:211
    $uri = admin_url('admin-ajax.php') . '?' . $path->getQuery();
```

`VikBooking::ajaxUrl()` (`vikbooking/site/helpers/lib.vikbooking.php:13339-13342`) y aboutit, et la page de confirmation l'utilise (`vikbooking/site/views/oconfirm/tmpl/default.php:810` et `:944`). Sans filtrage de `option_siteurl`, ces appels partiraient vers linstantcle.ch depuis l'hôte Sexcape Room. Le §4.1 du brief prévoit déjà de filtrer les deux : c'est confirmé nécessaire, pour ce motif précis.

**3. `JUri::base()` mémorise son résultat** dans `static::$base[$sign]` pour la durée de la requête (`uri.php:123-126`). Le filtre doit être posé avant le premier appel à `JUri::root()`. Mémoire de requête, pas de cache persistant.

### Un levier de repli, si Stripe reconstruit l'URL

Si la relecture du greffon Stripe montre qu'il n'honore pas `return_url`, il reste un point d'accroche documenté qui reçoit l'objet de paiement **avant** la construction du formulaire :

```
vikbooking/libraries/adapter/payment/payment.php:329
    do_action($this->getHook('payment_before_begin_transaction'), array(&$this));
```

soit **`payment_before_begin_transaction_vikbooking`**, documenté dans `vikbooking/libraries/hooks.md:37-49`. L'objet expose un mutateur, `vikbooking/libraries/adapter/payment/payment.php:160` : `public function set($key, $val)`. On peut donc y réécrire `return_url` par marque.

Piège d'implémentation : l'appel est `do_action($hook, array(&$this))`, donc la fonction rappelée reçoit **un tableau** dont l'indice `0` est l'objet de paiement, et non l'objet directement.

---

## Q6. Les tarifs configurés correspondent-ils à la grille attendue ?

**Tranchée, 11 septembre 2026.** Les six requêtes ci-dessous ont été exécutées en lecture seule via `ssh sg-linstantcle "mysql --batch dbvkhvlostfyua -e '…'"`, conformément à `docs/briefs/handoff-acces-mysql.md`. Sorties brutes dans `.local/q1_grille_base.tsv`, `.local/q2_saisons.tsv`, `.local/q3_saisons_par_chambre.tsv`, `.local/q4_restrictions.tsv`, `.local/q5_occupation.tsv` et `.local/q6_repli_minlos.tsv`. Aucune requête n'a renvoyé d'erreur.

La grille tarifaire vit en base, pas dans le code : elle ne devait pas être posée de mémoire ni par déduction, d'autant que c'est précisément sur le moteur de prix que le bug du tarif weekend a coûté 1800 francs. Ce qui suit est lu directement dans les sorties, rien n'est déduit.

Ce qui a pu être établi, en revanche, c'est le **modèle de données**, et il réserve trois pièges qu'il fallait connaître avant de lire la moindre valeur.

### Piège 1 — les jours de semaine

Encodage : entiers PHP `getdate()['wday']`, **`0` = dimanche, `1` = lundi, … `6` = samedi**. Preuve dans l'éditeur de saison, `vikbooking/admin/views/manageseason/tmpl/default.php:300-306` (option `value="0"` → `VBSUNDAY`, … `value="6"` → `VBSATURDAY`).

Ce n'est **pas un masque de bits**, mais une liste de chiffres délimitée, et les deux tables n'utilisent pas le même délimiteur :

| Table | Colonne | Format | Un weekend vendredi + samedi s'écrit |
|---|---|---|---|
| `seasons` | `wdays` | chiffres terminés par `;` (`vikbooking/admin/controller.php:4372-4375`) | `5;6;` |
| `restrictions` | `ctad`, `ctdd` | jetons `-N-` joints par `,` (`vikbooking/admin/controller.php:5825`, `:5875`) | `-5-,-6-` |
| `restrictions` | `wday`, `wdaytwo` | entier simple | `5` et `6` |

**Un `wdays` vide vaut les sept jours**, pas zéro jour — texte d'aide `VBOSPWDAYSHELP` (`vikbooking/libraries/language/admin.php:5102-5104`) et branche `else` du code (`lib.vikbooking.php:7620-7622`).

### Piège 2 — nuit par nuit, ou jour d'arrivée ?

Les deux, selon la table. C'est le cœur du sujet weekend.

**Les saisons se comptent nuit par nuit.** La boucle parcourt chaque nuit du séjour et teste le jour de cette nuit-là :

```
vikbooking/site/helpers/lib.vikbooking.php:7599-7622
    for ($i = 0; $i < $a[0]['days']; $i++) {
        $todayts = $season_fromdayts + ($i * 86400);
        …
        if ($todayts >= $inits && $todayts <= $ends) {
            if ($filterwdays == true) {
                $checkwday = getdate($todayts);
                if (in_array($checkwday['wday'], $wdays)) {
                    $affdays++;
                }
            } else {
                $affdays++;
            }
        }
    }
```

Une nuit « vendredi » est donc la nuit du vendredi au samedi.

**Les restrictions se jugent sur le jour d'arrivée** (`lib.vikbooking.php:795-801` et `:882-889`, toutes contre `$restrcheckin['wday']`), sauf `ctdd` qui porte sur le jour de départ (`:824`).

### Piège 3 — les dates de saison ne sont pas des horodatages

`seasons`.`from` et `seasons`.`to` sont **le nombre de secondes écoulées depuis le 1er janvier 00:00 de l'année de référence**, pas des timestamps Unix :

```
vikbooking/admin/controller.php:4424-4429
    $baseone = getdate($first);
    $basets = mktime(0, 0, 0, 1, 1, $baseone['year']);
    $sfrom = $baseone[0] - $basets;
```

Conséquences : `from > to` signifie que la saison **enjambe le Nouvel An**, et une correction d'année bissextile est appliquée (`vikbooking/admin/controller.php:4432`, lecture `lib.vikbooking.php:1144-1173`). Une conversion naïve donnera un jour d'écart après le 29 février.

À l'inverse, `restrictions`.`dfrom` et `dto` **sont** de vrais horodatages Unix (`vikbooking/admin/views/restrictions/tmpl/default.php:185`).

### Le modèle, en bref

| Table | Rôle | Schéma |
|---|---|---|
| `sir_vikbooking_dispcost` | prix de base, une ligne par **chambre × plan tarifaire × nombre de nuits** ; `cost` est le **total du séjour**, pas le prix par nuit | `sql/install.mysql.utf8.sql:236-244` |
| `sir_vikbooking_prices` | plans tarifaires, avec `minlos` et `minhadv` | `sql/install.mysql.utf8.sql:521-537` |
| `sir_vikbooking_seasons` | saisons et promotions, en surcouche du prix de base | `sql/install.mysql.utf8.sql:604-629` |
| `sir_vikbooking_restrictions` | séjours minimum et maximum, jours d'arrivée, fermetures | `sql/install.mysql.utf8.sql:585-602` |
| `sir_vikbooking_adultsdiff` | tarification **par occupation** (ce n'est pas `dispcost`) | `sql/install.mysql.utf8.sql:21-30` |

Le prix par nuit se retrouve par division : `lib.vikbooking.php:7634`, `$dailyprice = $a[0]['cost'] / $a[0]['days'];`

Sémantique des saisons, `lib.vikbooking.php:7640-7709` :

- `type` = **1 majoration**, **2 remise** (libellés `Charge` / `Discount`, `vikbooking/libraries/language/admin.php:992-996`) ;
- `val_pcent` = **2 pourcentage**, **1 montant absolu** (défaut en base : 2) ;
- `diffcost` est toujours une magnitude positive, le signe vient de `type` ;
- un montant absolu s'applique **par nuit** : `($dailyprice + $absval) * $affdays` (`lib.vikbooking.php:7700-7709`).

Les saisons qui se recouvrent ne s'excluent pas, elles **se cumulent** : le seul tri est `ORDER BY promo ASC` (`lib.vikbooking.php:7366`), et le commentaire du code parle d'application « progressive and cumulative » (`lib.vikbooking.php:7787-7789`).

Deux asymétries à surveiller dans les données réelles (`lib.vikbooking.php:7585-7593`) : un `idprices` vide signifie « tous les plans tarifaires », mais un **`idrooms` vide signifie aucune chambre**, donc une saison inerte. Et une saison sans bornes de dates (`from` et `to` à 0) échappe à toute fenêtre temporelle (`lib.vikbooking.php:7248`, `:7954-7975`) : elle s'applique indéfiniment. Si un tarif weekend se déclenche hors période, c'est la première chose à regarder.

Le séjour minimum vit à **quatre endroits indépendants** : `restrictions.minlos` (le principal), `prices.minlos` (par plan tarifaire), `config.autodefcalnights` (repli global, `lib.vikbooking.php:2090-2093`) et `seasons.promominlos` (éligibilité d'une promotion, pas une contrainte de séjour). Quand deux restrictions de plage se recouvrent, **c'est l'`id` le plus élevé qui l'emporte** (`lib.vikbooking.php:841-850` et `:965-979`).

Il n'existe **aucune table de tarif journalier** : les prix datés passent exclusivement par `seasons`. Vérifié, `tarrules` et `djrules` n'existent nulle part dans l'arbre — ces tables appartiennent à Vik Rent Car et Vik Rent Items.

### Sondage des délimiteurs, 9 septembre 2026

Exécuté avant les requêtes de grille, pour éviter une jointure qui ne renvoie rien en silence.

- `seasons`.`idrooms` : format `-10-,-2-,-1-,` confirmé. Les requêtes 3 et 4 sont valides telles quelles.
- `restrictions`.`idrooms` : format `-7-;`, **point-virgule et non virgule**. Le `LIKE '%-N-%'` fonctionne quand même, par chance et non par conception. Ne jamais écrire une jointure qui suppose le délimiteur : toujours filtrer sur le jeton `-N-`.

### Trois anomalies relevées dans les vingt premières saisons — confirmées sur les 23 saisons complètes, avec une correction

`.local/q2_saisons.tsv` contient les 23 lignes de `sir_vikbooking_seasons` (le sondage du 9 septembre ne portait que sur les vingt premières). Aucune ligne n'a `alerte_bornes` renseignée : **aucune saison, parmi les 23, n'est sans bornes de dates.** L'anomalie « saison sans bornes » redoutée en général n'a pas d'autre occurrence que celle déjà connue par construction (voir piège 3, les saisons à cheval sur le Nouvel An portent `alerte_annee` mais ont bien des bornes).

**A. Le supplément weekend ne couvrait ni la chambre 8 ni la chambre 9 — corrigé par Thomas le 11 septembre 2026, revérifié.**
Au constat du 9 septembre : `Weekend surcharge 2026 (villas)` (id 118) et `2027 (villas)` (id 87) portaient `-10-,-2-,-1-,-7-,-4-,-5-,-6-,`, sans aucune ligne `Weekend surcharge (rooms)`, alors que le doublet villas/rooms existait pour l'Immaculée Conception (130/131), l'Ascension (132/133), la Pentecôte (134/135), la Fête-Dieu (136/137) et les vacances d'hiver (139/140).

**Correction au constat du 9 septembre : `Easter break 2027` a bien un doublet.** La liste complète des 23 saisons montrait déjà `Easter break 2027 (rooms)` (id 143, `-9-,-8-,`, +70/nuit), à côté de `Easter break 2027 (villas)` (id 142). Le sondage précédent, limité aux vingt premières lignes, ne l'avait pas vu.

**Le 11 septembre 2026, Thomas a créé le doublet manquant.** Deux nouvelles lignes dans `sir_vikbooking_seasons`, confirmées par requête (`.local/q2_weekend_rooms_bornes.tsv`, `.local/q3_weekend_8_9.tsv`) :

| id | nom | idrooms | valeur | bornes |
|---|---|---|---|---|
| 146 | Weekend surcharge 2026 (rooms) | `-9-,-8-,` | +80/nuit | 2026-09-01 → 2026-12-31, bornée |
| 147 | Weekend surcharge 2027 (rooms) | `-9-,-8-,` | +80/nuit | 2027-01-01 → 2027-12-31, bornée |

Les deux lignes ont des bornes de dates valides (aucune `alerte_bornes`), portent sur `idprices = -1-,` (le seul plan tarifaire existant) et rejoignent bien les chambres 8 et 9 par la jointure `idrooms LIKE '%-N-%'` (`.local/q3_weekend_8_9.tsv`, vérifié). **L'anomalie A est résolue pour le supplément weekend.**

Chiffré via `.local/q1_grille_base.tsv`, mis à jour le 11 septembre 2026 (`.local/q1_grille_base_maj_8_11sept.tsv`) : chambre 8 et chambre 9 sont désormais toutes deux à **195.00 CHF la nuit en semaine**, et **275.00 CHF la nuit le vendredi et le samedi** (195 + 80 de supplément). Note : le supplément « rooms » vaut +80 CHF/nuit contre +100 CHF/nuit pour le supplément « villas » des chambres 1, 2, 4, 7 et 10 — écart cohérent avec les autres doublets déjà observés (Pâques, Ascension, Pentecôte : 70 contre 100), pas une anomalie supplémentaire.

**Ce qui reste ouvert, non revérifié à cette date : `Fall vacation 2026` (id 138) et `Carnival vacation 2027` (id 141) n'avaient toujours pas de doublet « rooms » au 9 septembre.** Cette session n'a interrogé que le supplément weekend, à la demande explicite ; l'état de ces deux saisons pour les chambres 8 et 9 n'a pas été revérifié aujourd'hui et ne doit pas être supposé corrigé.

**B. `Summer vacation 2026` (id 27) a un `idrooms` vide — confirmé.** `.local/q2_saisons.tsv` le montre directement : colonne `chambres` vide sur cette ligne, seule saison de toutes à porter un `idrooms` vide. Conforme à la lecture de `lib.vikbooking.php:7585-7593` : aucune chambre, donc saison inerte, donc majoration des nuits de dimanche à jeudi jamais appliquée de tout l'été 2026. Autre trait distinctif de cette ligne : c'est la seule saison des 23 dont `year` est `NULL` plutôt qu'un entier — vestige probable d'avant le découpage villas/rooms introduit pour 2027. **Toujours à vérifier en priorité** : comparer le prix d'une réservation réelle d'août 2026 au prix attendu. Si confirmé, le manque à gagner dépasse celui du bug du tarif weekend.

**C. `Long-stay Discount` (id 122) ne porte que sur `-5-,-6-,`**, les deux chambres de test — confirmé par `.local/q3_saisons_par_chambre.tsv` : aucune des sept chambres vendues n'y figure. La remise long séjour ne s'applique à aucune chambre vendue. Volontaire ou oubli, à trancher par Thomas.

**Note annexe.** Les chambres de test 5 et 6 figurent dans presque toutes les saisons « villas ». Sans effet, puisqu'elles ne se vendent pas, mais cela brouille la lecture.

### Anomalies supplémentaires, du même genre, relevées en clôturant Q6

**D et E — résolues le 11 septembre 2026 : Thomas a supprimé la possibilité de réserver plus de 15 nuits, pour toutes les chambres.**

Au constat du 11 septembre (première clôture de Q6), deux anomalies de portée de nuits avaient été relevées : les chambres 1 et 7 n'avaient de prix `dispcost` que jusqu'à 15 nuits quand les chambres 2, 4, 8, 9 et 10 en avaient jusqu'à 30 (anomalie D) ; et sur ce dernier groupe, les chambres 2 et 4 remontaient à leur tarif d'une nuit pour la tranche 16-30 nuits au lieu de poursuivre la baisse, un défaut de monotonicité (anomalie E).

**Rafraîchi le même jour (`.local/q1_grille_base_11sept_refresh.tsv`) : les lignes `dispcost` de 16 à 30 nuits ont été supprimées pour toutes les chambres qui les avaient (2, 4, 8, 9, 10).** Les sept chambres vendues n'ont plus désormais qu'une grille de 1 à 15 nuits, strictement identique en forme à ce que les chambres 1 et 7 avaient déjà. Vérifié : `SELECT … WHERE r.id IN (1,2,4,7,8,9,10)` ne renvoie plus aucune ligne `d.days > 15`, pour aucune des sept chambres.

Conséquence : ce qui était une incohérence de portée entre chambres (D) est devenu une règle uniforme — **séjour maximum 15 nuits pour les sept chambres vendues** — et la rupture de monotonicité (E), qui ne portait que sur la tranche 16-30 désormais retirée, n'a plus de tranche où se produire. **Les deux anomalies sont closes.** Aucune ligne `restrictions.maxlos` ne porte cette limite : elle vient uniquement de l'absence de prix au-delà de 15 nuits dans `dispcost`, comme c'était déjà le cas pour les chambres 1 et 7 avant cette mise à jour.

**F. Portée de chambres incohérente sur la tarification par occupation.** `.local/q5_occupation.tsv` : `sir_vikbooking_adultsdiff` ne contient des lignes que pour les chambres 1 (L'Entracte) et 10 (À Huis Clos) — +30/+60/+90 CHF par nuit pour le 3e, 4e et 5e adulte. Les chambres 2, 4, 7, 8 et 9 n'ont aucune ligne : soit leur capacité ne dépasse pas 2 adultes, soit un surcoût par occupant y est simplement absent de la configuration. Ni l'un ni l'autre n'est établi ici.

### Restrictions, état relevé

Trois lignes en tout : `No checkout on Christmas 2025` (allrooms=1, échue), `L'Entracte families & friends - no check-in Fri/Sat` (chambre 7), et `L'Entracte families & friends - April only` avec `allrooms=0` et `idrooms` **nul**, donc **inerte**.

**Confirmé par la requête 4 (`.local/q4_restrictions.tsv`) : la troisième ligne n'apparaît dans aucun résultat.** La jointure `rs.allrooms = 1 OR rs.idrooms LIKE '%-N-%'` ne peut la retrouver pour aucune chambre : `allrooms=0` exclut la première branche, `idrooms` nul exclut la seconde. Elle est donc invisible — et sans effet — pour les sept chambres vendues comme pour les deux chambres de test. C'est la preuve directe, par la base, de ce que la lecture du code laissait déjà attendre.

**Restriction inerte confirmée : « L'Entracte families & friends - April only » ne s'applique à aucune chambre — même genre d'anomalie que la saison inerte B et la remise inapplicable C, appliqué ici à une restriction plutôt qu'à une saison.**

`.local/q4_restrictions.tsv` montre par ailleurs que `No checkout on Christmas 2025` (id 1, toutes chambres) porte sur le 25 décembre **2025** : cette date est passée à la lecture de ce constat (11 septembre 2026), la restriction n'a donc plus d'effet pratique, sans qu'il s'agisse d'une anomalie de configuration au sens des trois catégories suivies ici — juste une ligne échue laissée en place.

La seule restriction encore active et discriminante est `L'Entracte families & friends - no check-in Fri/Sat` (id 2, chambre 7 uniquement), du 2026-04-09 au 2027-12-31 : arrivée fermée le vendredi et le samedi sur cette chambre, pour cette période.

Conséquence inchangée : les chambres 8, 9 et 10 n'ont aucune restriction propre. Leur séjour minimum retombe sur `prices.minlos` puis sur `config.autodefcalnights`. **Confirmé par la requête 6 (`.local/q6_repli_minlos.tsv`) : `autodefcalnights` vaut `1`.** Combiné à `prices.minlos = 1` pour les sept chambres vendues (requête 1) et à l'absence de restriction `minlos` supérieure à 1 sur une plage encore active (requête 4), **le séjour minimum réel est aujourd'hui d'une nuit pour les sept chambres vendues, sans exception.** À confirmer comme voulu avant l'ouverture à la vente.

### Requêtes exécutées pour clore cette question

**Exécutées le 11 septembre 2026**, en lecture seule, via SSH (`ssh sg-linstantcle "mysql --batch dbvkhvlostfyua -e '…'"`) plutôt que phpMyAdmin — l'accès prévu par `docs/briefs/handoff-acces-mysql.md` était en place entre-temps. Sorties dans `.local/q1_grille_base.tsv` à `.local/q6_repli_minlos.tsv`, une par requête, dans l'ordre ci-dessous.

```sql
-- 1. Grille de base : chambre × plan tarifaire × durée
SELECT r.id AS chambre_id, r.name AS chambre, r.units AS unites,
       p.id AS plan_id, p.name AS plan, p.minlos AS plan_min_nuits,
       d.days AS nuits, d.cost AS total_sejour,
       ROUND(d.cost / NULLIF(d.days,0), 2) AS prix_par_nuit
FROM dbvkhvlostfyua.sir_vikbooking_dispcost d
JOIN dbvkhvlostfyua.sir_vikbooking_rooms  r ON r.id = d.idroom
JOIN dbvkhvlostfyua.sir_vikbooking_prices p ON p.id = d.idprice
WHERE r.id IN (1,2,4,7,8,9,10)
ORDER BY r.id, p.id, d.days;

-- 2. Saisons et promotions, decodees
SELECT s.id, COALESCE(NULLIF(s.spname,''), CONCAT('(sans nom #', s.id, ')')) AS nom,
       CASE s.promo WHEN 1 THEN 'PROMO' ELSE 'SAISON' END AS genre,
       CASE s.type  WHEN 1 THEN 'MAJORATION +' ELSE 'REMISE -' END AS sens,
       CASE s.val_pcent WHEN 2 THEN 'POURCENT' ELSE 'MONTANT (par nuit)' END AS unite,
       s.diffcost AS valeur, s.`year` AS annee,
       s.`from` AS brut_from, s.`to` AS brut_to,
       IF(s.`from` > s.`to`, 'enjambe le Nouvel An', '') AS alerte_annee,
       IF(COALESCE(s.`from`,0)=0 AND COALESCE(s.`to`,0)=0,
          'SANS BORNE - s applique indefiniment', '') AS alerte_bornes,
       IF(COALESCE(s.wdays,'')='', '(les 7 nuits)', s.wdays) AS jours_bruts,
       s.idrooms AS chambres, s.idprices AS plans,
       s.losoverride, s.roundmode, s.checkinincl
FROM dbvkhvlostfyua.sir_vikbooking_seasons s
ORDER BY s.promo ASC, s.`from` ASC, s.id ASC;

-- 3. Saisons rapportees a chaque chambre vendue
SELECT r.id AS chambre_id, r.name AS chambre, s.id AS saison_id,
       COALESCE(NULLIF(s.spname,''), CONCAT('#', s.id)) AS saison,
       CONCAT(CASE s.type WHEN 1 THEN '+' ELSE '-' END,
              TRIM(TRAILING '.000' FROM s.diffcost),
              CASE s.val_pcent WHEN 2 THEN '%' ELSE ' par nuit' END) AS effet,
       IF(COALESCE(s.wdays,'')='', 'toutes les nuits', s.wdays) AS nuits_visees
FROM dbvkhvlostfyua.sir_vikbooking_seasons s
JOIN dbvkhvlostfyua.sir_vikbooking_rooms r
     ON s.idrooms LIKE CONCAT('%-', r.id, '-%')
WHERE r.id IN (1,2,4,7,8,9,10)
ORDER BY r.id, s.promo ASC, s.`from` ASC, s.id ASC;

-- 4. Restrictions et sejours minimum, par chambre
SELECT r.id AS chambre_id, r.name AS chambre, rs.id AS restriction_id, rs.name AS restriction,
       CASE WHEN rs.month > 0 THEN CONCAT('mois ', rs.month) ELSE 'plage de dates' END AS portee,
       IF(COALESCE(rs.dfrom,0)>0, FROM_UNIXTIME(rs.dfrom,'%Y-%m-%d'), NULL) AS du,
       IF(COALESCE(rs.dto,0)>0,   FROM_UNIXTIME(rs.dto,'%Y-%m-%d'),   NULL) AS au,
       rs.minlos AS nuits_min, NULLIF(rs.maxlos,0) AS nuits_max,
       rs.wday AS arrivee_jour_1, rs.wdaytwo AS arrivee_jour_2,
       rs.ctad AS ferme_arrivee, rs.ctdd AS ferme_depart,
       CASE rs.allrooms WHEN 1 THEN 'toutes chambres' ELSE 'chambres listees' END AS niveau
FROM dbvkhvlostfyua.sir_vikbooking_restrictions rs
JOIN dbvkhvlostfyua.sir_vikbooking_rooms r
     ON (rs.allrooms = 1 OR rs.idrooms LIKE CONCAT('%-', r.id, '-%'))
WHERE r.id IN (1,2,4,7,8,9,10)
ORDER BY r.id, (rs.month = 0), rs.month, rs.dfrom, rs.id;

-- 5. Tarification par occupation
SELECT r.id AS chambre_id, r.name AS chambre, a.adults AS pour_n_adultes,
       CASE a.chdisc   WHEN 1 THEN 'MAJORATION +' ELSE 'REMISE -' END AS sens,
       CASE a.valpcent WHEN 1 THEN 'MONTANT' ELSE 'POURCENT' END AS unite,
       a.value AS valeur,
       CASE a.pernight WHEN 1 THEN 'PAR NUIT' ELSE 'PAR SEJOUR' END AS base
FROM dbvkhvlostfyua.sir_vikbooking_adultsdiff a
JOIN dbvkhvlostfyua.sir_vikbooking_rooms r ON r.id = a.idroom
WHERE r.id IN (1,2,4,7,8,9,10)
ORDER BY r.id, a.adults;

-- 6. Repli global du sejour minimum
SELECT `param`, `setting` FROM dbvkhvlostfyua.sir_vikbooking_config
WHERE `param` = 'autodefcalnights';
```

### Tableau, rempli et à valider par Thomas

Rempli à partir des sorties des six requêtes ci-dessus, `.local/q1_grille_base.tsv` à `.local/q6_repli_minlos.tsv`, rafraîchies le 11 septembre 2026. Une ligne par chambre vendue. Prix « semaine » = prix de base `dispcost` pour 1 nuit (requête 1), hors toute saison. Prix « weekend » = même base + `Weekend surcharge` (requêtes 2 et 3) quand la chambre y figure, pour un vendredi ou un samedi hors saison additionnelle. **Ce tableau, une fois validé, devient la valeur attendue de l'oracle tarifaire de la phase 5.**

| Chambre | Expérience | Marque | Prix 1 nuit, semaine | Prix 1 nuit, weekend | Nuits min | Nuits max | Jours d'arrivée imposés | Saisons applicables (hors Long-stay Discount et Summer vacation 2026, inertes pour toutes) | Anomalies |
|---|---|---|---|---|---|---|---|---|---|
| 1 | L'Entracte | L'Instant Clé | 229.00 CHF | 329.00 CHF | 1 | 15 | Aucun | Weekend surcharge 26/27, Carnival 2027, Valentine's (12-13 fév, 14 fév) 2027, Easter break 2027, Ascension 2027, Pentecost 2027, Corpus Christi 2027, Summer vacation 2027, Fall vacation 2026, Immaculée Conception, Winter vacation 2027, Last minute -15% (13 saisons) | F |
| 7 | L'Entracte - All Inclusive | L'Instant Clé | 390.00 CHF | 490.00 CHF | 1 | 15 | Aucun jour imposé ; **arrivée fermée ven/sam du 2026-04-09 au 2027-12-31** (restriction id 2) | Identique à la chambre 1 (13 saisons) | — |
| 10 | À Huis Clos | Sexcape Room | 298.00 CHF | 398.00 CHF | 1 | 15 | Aucun | Identique à la chambre 1 (13 saisons) | F |
| 2 | L'Aparté | L'Instant Clé | 229.00 CHF | 329.00 CHF | 1 | 15 | Aucun | Identique à la chambre 1 (13 saisons) | — |
| 4 | Le Boudoir du Désir | Sexcape Room | 298.00 CHF | 398.00 CHF | 1 | 15 | Aucun | Identique à la chambre 1 (13 saisons) | — |
| 8 | La Parenthèse | L'Instant Clé | 195.00 CHF *(mis à jour le 11 septembre 2026, était 149.00 CHF)* | 275.00 CHF *(supplément weekend rooms +80 créé le 11 septembre 2026 ; jusque-là 195.00 CHF, aucune majoration)* | 1 | 15 | Aucun | Weekend surcharge (rooms) 2026/2027, Valentine's (12-13 fév, 14 fév) 2027, Easter break 2027, Ascension 2027, Pentecost 2027, Corpus Christi 2027, Summer vacation 2027, Immaculée Conception, Winter vacation 2027, Last minute -15% (11 saisons) — **encore sans** Carnival 2027, Fall vacation 2026 (non revérifié depuis le 9 septembre) | A (partiel — weekend corrigé) |
| 9 | L'Indécent | Sexcape Room | 195.00 CHF *(mis à jour le 11 septembre 2026, était 149.00 CHF)* | 275.00 CHF *(supplément weekend rooms +80 créé le 11 septembre 2026 ; jusque-là 195.00 CHF, aucune majoration)* | 1 | 15 | Aucun | Identique à la chambre 8 (11 saisons) | A (partiel — weekend corrigé) |

Notes de lecture du tableau :

- **Nuits min = 1 pour les sept chambres, sans exception** : aucune restriction `minlos` active ne dépasse 1 (requête 4), `prices.minlos` vaut 1 partout (requête 1), et le repli global `autodefcalnights` vaut 1 (requête 6). Les trois sources s'accordent.
- **Nuits max = 15 pour les sept chambres, depuis le 11 septembre 2026.** Avant cette date, seules les chambres 1 et 7 avaient cette limite ; Thomas l'a étendue aux cinq autres en retirant les lignes `dispcost` de 16 à 30 nuits (requête 1, rafraîchie). Ce n'est pas une restriction `restrictions.maxlos` — cette colonne reste `NULL`/0 partout (requête 4) — mais une conséquence de l'absence de prix au-delà de 15 nuits.
- **B — `Summer vacation 2026` (id 27) ne s'applique à aucune chambre**, `idrooms` vide (requête 2) : aucune des sept lignes ci-dessus ne la compte, volontairement omise de la colonne « saisons applicables » plutôt que listée comme inapplicable sept fois.
- **C — `Long-stay Discount` (id 122) ne s'applique à aucune chambre vendue** (requête 3), seulement aux chambres de test 5 et 6 : même traitement, omise ci-dessus.
- **D et E — closes le 11 septembre 2026** (portée de nuits incohérente et rupture de monotonicité tarifaire) : voir le détail plus haut. Absorbées dans la nouvelle règle « nuits max = 15 », elles ne figurent plus dans la colonne « Anomalies ».
- **F — chambres 1 et 10 : seules chambres avec une tarification par occupation** (`sir_vikbooking_adultsdiff`, requête 5), +30/+60/+90 CHF par nuit pour le 3e, 4e et 5e adulte. Les cinq autres chambres n'en ont aucune.

---

## Verdict de phase

**Palier retenu confirmé. Rien dans le code ni dans les données de Vik ne s'oppose à l'architecture du chapitre 4. Les six questions du chapitre 7 sont désormais tranchées.**

- **Q1** — même compte SiteGround, domaine garé possible, close.
- **Q2** — tout part par `wp_mail`, et `vikbooking_before_send_booking_mail` reçoit la réservation : la marque est résoluble à l'envoi. Le point réputé le plus dur du chantier est le mieux outillé.
- **Q3** — les groupes de disponibilité sont déjà complets et symétriques.
- **Q4** — le filtrage natif est faible, mais `vikbooking_apply_search_results_filtering` fait proprement la couche 1.
- **Q5** — l'URL de retour vient de `home_url()`, donc filtrer `option_home` l'atteint. Le greffon Stripe, relu dans `.local/wp-vikstripe/`, reconstruit cette URL pour VikBooking au lieu d'honorer celle que le cœur lui remet, mais la reconstruction passe par le même `JUri::root()`/`home_url()` : le filtrage prévu la couvre déjà.
- **Q6** — la grille tarifaire est extraite et documentée dans le tableau ci-dessus. Six anomalies de configuration relevées (A à F, détaillées plus haut), aucune ne remettant en cause l'architecture retenue : ce sont des défauts de données dans Vik, pas des obstacles techniques au chantier de réservation.

Le repli du §4.1, tunnel entièrement reconstruit, **n'a pas lieu d'être ouvert**.

### Les six anomalies de configuration relevées dans les données de Vik, pour mémoire

À traiter par Thomas dans l'administration Vik, hors de ce chantier de code, et à consigner dans `journal-vik.md` le jour où elles le sont :

- **A.** Chambres 8 et 9 exclues du carnaval 2027 et des vacances d'automne 2026 (non revérifié depuis le 9 septembre) — le supplément weekend, lui, a été corrigé par Thomas le 11 septembre 2026 (saisons id 146/147, +80/nuit).
- **B.** `Summer vacation 2026` (id 27) inerte, `idrooms` vide — majoration d'été 2026 jamais appliquée, sur aucune chambre.
- **C.** `Long-stay Discount` (id 122) ne porte que sur les chambres de test 5 et 6 — remise long séjour inapplicable à toute chambre vendue.
- ~~**D.** Chambres 1 et 7 sans prix `dispcost` au-delà de 15 nuits.~~ **Close, 11 septembre 2026** : devenue la règle pour les sept chambres, séjour maximum 15 nuits partout.
- ~~**E.** Chambres 2 et 4 : rupture de monotonicité tarifaire, le prix par nuit remonte au tarif d'une nuit pour un séjour de 16 à 30 nuits.~~ **Close, 11 septembre 2026** : la tranche 16-30 nuits où l'anomalie se produisait a été retirée.
- **F.** Tarification par occupation (`adultsdiff`) présente uniquement sur les chambres 1 et 10, absente des cinq autres.
- Restriction inerte confirmée : « L'Entracte families & friends - April only » (`allrooms=0`, `idrooms` nul) ne s'applique à aucune chambre.

### Ce qui a changé dans le brief

1. **§4.4, e-mails.** Désactiver les e-mails clients de Vik est impossible : aucun réglage natif ne le permet, et le hook d'envoi ne peut pas annuler. On **réécrit le message en vol** via `VBOMailWrapper` plutôt que d'en émettre un second. Un seul chemin d'envoi, donc pas de risque de doublon.
2. **§4.1, réécriture d'URL.** Filtrer `option_home` suffit pour l'URL de retour, mais `option_siteurl` doit l'être aussi : les appels AJAX du tunnel passent par `admin_url()`, qui est bâti sur `siteurl`.
3. **§4.3, filtrage.** Le filtre natif ne couvre que la vue `search`. `roomslist`, `availability` et `roomdetails` n'exposent aucun point d'accroche. Si ces écrans sont publiés sur l'hôte Sexcape Room, la garde de la couche 2 est leur seule protection.

### Ce qui bloque la clôture

Plus rien côté code ou données. Q6, la vérification d'hébergement du §Q1 et la lecture du greffon Stripe (§Q5) sont réglées. Ne reste que la validation de Thomas, qui ne bloque pas l'ouverture de la phase 1 :

**Validation de Thomas requise sur le tableau de Q6** — le tableau devient l'oracle tarifaire de la phase 5 seulement après cette validation — **et sur le traitement des six anomalies A à F**, avant la phase 5 pour la première, avant l'ouverture à la vente des chambres 8 et 9 pour les anomalies A, D et E.

### Recommandation

Ouvrir la **phase 1** — registre, résolution de marque, réécriture d'URL, écran de santé, journalisation. Le point Stripe ci-dessus ne la bloque pas : il vise la phase 4.

**Validation de Thomas requise avant d'écrire la première ligne de la phase 1.**
