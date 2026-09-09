# Constat, phase 0

Réponses aux six questions du chapitre 7 du brief `sexcape-room-reservation.md`.
Règle : chaque réponse cite la preuve qui l'établit. Une question sans preuve se déclare non tranchée, jamais déduite.

**Source lue pour Q2, Q4, Q5 et Q6 :** copie SFTP de Vik Booking **1.8.14** (`vikbooking.php:6`), déposée dans `.local/vikbooking/` et `.local/vikchannelmanager/`. Le `.local/README.md` annonçait `.local/vik-source/` : le dossier porte en fait le nom du plugin. Lecture seule, rien n'a été modifié. Tous les chemins ci-dessous sont relatifs à `.local/`, toutes les lignes ont été relues une à une.

---

## Q1. Hébergement, les deux domaines peuvent-ils partager une installation ?

**Tranchée, 9 septembre 2026, par Thomas dans SiteGround Site Tools.**

linstantcle.ch et sexcaperoom.ch sont sur le **même compte SiteGround**, mais déclarés comme **deux sites distincts** : racines séparées dans le système de fichiers, installations WordPress séparées.

Conséquence pour l'architecture : le palier retenu tient. `reservation.sexcaperoom.ch` s'ajoute en **domaine garé** sur le site linstantcle.ch, et est donc servi par l'installation qui porte Vik. Même compte veut dire aucune démarche inter-comptes, ni pour le domaine ni pour le certificat.

**Complément du 9 septembre 2026, Thomas.** Le DNS de sexcaperoom.ch est chez **name.com**, et **aucun enregistrement générique `*` n'existe**. Sans joker, `reservation.sexcaperoom.ch` ne résout que si un enregistrement est créé explicitement : aucun conflit possible avec le site sexcaperoom.ch. **Q1 est close.**

Mise en oeuvre, dans l'ordre, la première étape conditionnant les suivantes :

1. name.com, créer un enregistrement `A` pour `reservation` pointant sur l'adresse IP du site linstantcle.ch, relevée dans SiteGround Site Tools.
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

Le cœur lui remet `return_url` et rien d'autre. Mais **qu'elle utilise cette valeur telle quelle pour `return_url` / `success_url` / `cancel_url` de Stripe, ou qu'elle la reconstruise, n'est pas prouvé.** C'est de la déduction tant que le dossier du greffon Stripe n'a pas été récupéré par SFTP et relu. Le critère de recette n° 7 dépend de ce point.

**Action préalable à la phase 4 : déposer la copie du greffon Stripe dans `.local/` et refaire ce trajet.**

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

**Non tranchée. La grille n'a pas pu être extraite : aucun accès à la base depuis ce poste.**

`wp`, `mysql` et `mysqldump` sont absents du PATH, et `.local/` ne contient aucun export. La grille tarifaire vit en base, pas dans le code. La poser ici de mémoire ou par déduction serait exactement l'erreur que ce constat doit éviter — d'autant que c'est précisément sur le moteur de prix que le bug du tarif weekend a coûté 1800 francs.

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

### Trois anomalies relevées dans les vingt premières saisons

**A. Le supplément weekend ne couvre ni la chambre 8 ni la chambre 9.**
`Weekend surcharge 2026 (villas)` (id 118) et `2027 (villas)` (id 87) portent `-10-,-2-,-1-,-7-,-4-,-5-,-6-,`. Aucune ligne `Weekend surcharge (rooms)` n'existe, alors que le doublet villas/rooms existe pour l'Immaculée Conception (130/131), l'Ascension (132/133), la Pentecôte (134/135), la Fête-Dieu (136/137) et les vacances d'hiver (139/140). Même absence sur `Fall vacation 2026` (138), `Carnival vacation 2027` (141) et `Easter break 2027` (142).

Conséquence : **La Parenthèse et L'Indécent se vendraient au tarif semaine tous les vendredis et samedis de 2026 et 2027.** Ces deux chambres ouvrent à la vente au lancement de Sexcape Room. À corriger avant l'ouverture, indépendamment de ce chantier.

**B. `Summer vacation 2026` (id 27) a un `idrooms` vide.** Si la lecture de `lib.vikbooking.php:7585-7593` est juste, cela signifie aucune chambre, donc saison inerte, donc majoration des nuits de dimanche à jeudi jamais appliquée de tout l'été 2026. **À vérifier en priorité** : relire ces lignes, puis comparer le prix d'une réservation réelle d'août 2026 au prix attendu. Si confirmé, le manque à gagner dépasse celui du bug du tarif weekend.

**C. `Long-stay Discount` (id 122) ne porte que sur `-5-,-6-,`**, les deux chambres de test. La remise long séjour ne s'applique à aucune chambre vendue. Volontaire ou oubli, à trancher par Thomas.

**Note annexe.** Les chambres de test 5 et 6 figurent dans presque toutes les saisons « villas ». Sans effet, puisqu'elles ne se vendent pas, mais cela brouille la lecture.

### Restrictions, état relevé

Trois lignes en tout : `No checkout on Christmas 2025` (allrooms=1, échue), `L'Entracte families & friends - no check-in Fri/Sat` (chambre 7), et `L'Entracte families & friends - April only` avec `allrooms=0` et `idrooms` **nul**, donc **inerte**.

Conséquence : les chambres 8, 9 et 10 n'ont aucune restriction propre. Leur séjour minimum retombe sur `prices.minlos` puis sur `config.autodefcalnights`. À confirmer comme voulu avant l'ouverture à la vente.

### Requêtes à exécuter pour clore cette question

À lancer dans phpMyAdmin, en lecture seule. Leur sortie collée ici clôt Q6.

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

### Tableau à valider par Thomas

À remplir avec la sortie des requêtes ci-dessus. Une ligne par chambre vendue. **Ce tableau, une fois validé, devient la valeur attendue de l'oracle tarifaire de la phase 5.**

| Chambre | Expérience | Marque | Prix 1 nuit, semaine | Prix 1 nuit, weekend | Nuits min | Jours d'arrivée imposés | Saisons applicables |
|---|---|---|---|---|---|---|---|
| 1 | L'Entracte | L'Instant Clé | | | | | |
| 7 | L'Entracte all inclusive | L'Instant Clé | | | | | |
| 10 | À Huis Clos | Sexcape Room | | | | | |
| 2 | L'Aparté | L'Instant Clé | | | | | |
| 4 | Le Boudoir du Désir | Sexcape Room | | | | | |
| 8 | La Parenthèse | L'Instant Clé | | | | | |
| 9 | L'Indécent | Sexcape Room | | | | | |

---

## Verdict de phase

**Palier retenu confirmé. Rien dans le code de Vik ne s'oppose à l'architecture du chapitre 4. La phase 0 n'est pas close pour autant : Q6 reste ouverte et une lecture manque.**

Cinq questions sur six sont tranchées, et chacune conforte le palier retenu plutôt qu'elle ne l'entame :

- **Q1** — même compte SiteGround, domaine garé possible.
- **Q2** — tout part par `wp_mail`, et `vikbooking_before_send_booking_mail` reçoit la réservation : la marque est résoluble à l'envoi. Le point réputé le plus dur du chantier est le mieux outillé.
- **Q3** — les groupes de disponibilité sont déjà complets et symétriques.
- **Q4** — le filtrage natif est faible, mais `vikbooking_apply_search_results_filtering` fait proprement la couche 1.
- **Q5** — l'URL de retour vient de `home_url()`, donc filtrer `option_home` l'atteint.

Le repli du §4.1, tunnel entièrement reconstruit, **n'a pas lieu d'être ouvert**.

### Ce qui a changé dans le brief

1. **§4.4, e-mails.** Désactiver les e-mails clients de Vik est impossible : aucun réglage natif ne le permet, et le hook d'envoi ne peut pas annuler. On **réécrit le message en vol** via `VBOMailWrapper` plutôt que d'en émettre un second. Un seul chemin d'envoi, donc pas de risque de doublon.
2. **§4.1, réécriture d'URL.** Filtrer `option_home` suffit pour l'URL de retour, mais `option_siteurl` doit l'être aussi : les appels AJAX du tunnel passent par `admin_url()`, qui est bâti sur `siteurl`.
3. **§4.3, filtrage.** Le filtre natif ne couvre que la vue `search`. `roomslist`, `availability` et `roomdetails` n'exposent aucun point d'accroche. Si ces écrans sont publiés sur l'hôte Sexcape Room, la garde de la couche 2 est leur seule protection.

### Ce qui bloque la clôture

1. **Q6 non tranchée.** Aucun accès base depuis le poste de travail. Les six requêtes de la section Q6 sont prêtes ; leur sortie, collée dans le tableau à valider, clôt la question. **C'est le préalable à la phase 5**, l'oracle tarifaire n'ayant aucune référence tant que Thomas n'a pas validé cette grille.
2. **La passerelle Stripe n'a pas été lue.** Elle ne fait pas partie de Vik Booking : c'est un greffon distinct, absent de la copie. Le cœur lui remet une `return_url` bâtie sur `home_url()`, mais qu'elle l'honore n'est pas prouvé. **Déposer le dossier du greffon Stripe dans `.local/` et refaire le trajet du §Q5 avant la phase 4.** Un levier de repli existe si nécessaire : `payment_before_begin_transaction_vikbooking` avec `JPayment::set('return_url', …)`.
3. **Vérification d'hébergement en suspens depuis Q1** : que sexcaperoom.ch ne capte pas déjà ses sous-domaines par une règle générique.

### Recommandation

Ouvrir la **phase 1** — registre, résolution de marque, réécriture d'URL, écran de santé, journalisation. Aucun des trois points ci-dessus ne la bloque : le premier vise la phase 5, le deuxième la phase 4, le troisième la bascule de la phase 6.

**Validation de Thomas requise avant d'écrire la première ligne de la phase 1.**
