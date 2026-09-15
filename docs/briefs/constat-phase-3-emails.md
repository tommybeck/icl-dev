# Constat, phase 3 — e-mails par marque

15 septembre 2026. Ce que la phase 3 construit, ce qu'elle a vérifié dans le code de Vik Booking 1.8.14 et dans la base de production, et les trois points qu'elle laisse ouverts parce qu'aucun n'est un geste de code.

Source lue : copie SFTP de Vik Booking **1.8.14** et de VikChannelManager dans `.local/`, en lecture seule. Relevés de base : `dbvkhvlostfyua` par SSH, **en lecture seule**, comme le veut `convention-tarifs-annuelle.md` — aucune écriture, aucune modification de configuration.

Règle de ce document, la même que pour `constat-phase-0.md` : chaque affirmation cite la ligne ou la requête qui l'établit. Ce qui n'est pas établi est annoncé comme non établi.

Sorties brutes des relevés de base dans `.local/p3_emails_releves_2026-09-15.tsv`, hors dépôt.

---

## 1. Ce que la phase 3 livre

`mu-plugins/lme-brands/includes/mail-brand.php`, plus les fonctions pures correspondantes dans `includes/core.php` et le bloc `mail` du registre.

Deux points d'accroche, tous deux dans Vik, aucun fichier de Vik modifié :

| Hook WordPress | Émis par | Ce qu'on en fait |
|---|---|---|
| `vikbooking_before_send_booking_mail` | `lib.vikbooking.php:6502` | expéditeur, adresse de réponse, objet, corps du message client |
| `vikbooking_before_create_mail_ical` | `lib.vikbooking.php:5563` | nom de la maison dans la pièce jointe `.ics` du client |

**Un seul chemin d'envoi, jamais un second message.** Le brief l'avait déjà corrigé après la phase 0 : aucun réglage de Vik ne désactive l'e-mail client d'une réservation confirmée, et `vikbooking_before_send_booking_mail` est un `do_action_ref_array` (`dispatcher.php:32`) qui ne retourne rien, donc n'annule rien. On réécrit le message que Vik s'apprête à envoyer, par les mutateurs de `VBOMailWrapper` (`wrapper.php:166` et suivantes). Les pièces jointes iCal survivent, et le doublon est structurellement impossible.

96 tests unitaires passent (`php mu-plugins/lme-brands/tests/test-core.php`), dont 44 ajoutés par cette phase. **Rien n'est déployé.**

---

## 2. Le périmètre : `$who` vaut `guest`, `channel` est nul

### Le destinataire

`sendBookingEmail()` boucle sur son tableau `$for` et déclenche le hook une fois par destinataire (`lib.vikbooking.php:6403` à `:6502`). `$who` est l'élément brut de ce tableau.

Relevé exhaustif des appels de `VikBooking::sendBookingEmail()` dans les deux plugins — quinze sites d'appel, définition exclue :

| Fichier | `$for` |
|---|---|
| `vikbooking/site/controller.php:1241`, `:1454`, `:1615`, `:2486`, `:3643` | `['guest', 'admin']` |
| `vikbooking/site/controller.php:2972` | `['guest']` |
| `vikbooking/site/controller.php:4779`, `:4821`, `:4864` | `['admin']` |
| `vikbooking/admin/controller.php:3917` | `['guest']` |
| `vikbooking/admin/helpers/src/model/reservation.php:1979` | `['guest']` |
| `vikbooking/admin/views/tmplfileprew/view.html.php:48` | `[]`, avec `$send = false` (aperçu, aucun envoi) |
| `vikchannelmanager/site/controller.php:1672`, `helpers/app.php:2248`, `helpers/newbookings.vikbooking.php:357` | `['guest']` |

**Aucun appel ne passe autre chose que `guest` ou `admin`.** « `$who` égal à `guest` » décrit donc exactement le périmètre voulu par le brief.

Le code implémente néanmoins la cascade de Vik plutôt que l'égalité stricte (`lme_brands_mail_audience()`), et c'est délibéré. Vik reconnaît son destinataire ainsi (`lib.vikbooking.php:6411-6425`) :

```php
if (strpos($who, '@') !== false) { /* adresse personnalisée */ }
elseif (stripos($who, 'guest') !== false || stripos($who, 'customer') !== false) { /* le client */ }
elseif (stripos($who, 'admin') !== false) { /* l'administrateur */ }
```

Un appelant futur qui passerait `'customer'` enverrait au client un message que l'égalité stricte laisserait filer — et un message non réécrit part sous l'expéditeur global de l'installation, donc sous une marque qui n'est peut-être pas la bonne. Suivre la cascade de Vik élargit le périmètre exactement là où Vik l'élargit, et nulle part ailleurs. L'ordre des tests est reproduit tel quel : une adresse littérale contenant le mot « guest » est un destinataire personnalisé, pas le client.

### La réservation directe

`sir_vikbooking_orders.channel` est `varchar(64) DEFAULT NULL` (`sql/install.mysql.utf8.sql:407`). Relevé du 15 septembre 2026, en lecture seule :

```sql
SELECT COUNT(*) AS total, SUM(channel IS NULL) AS directes,
       SUM(channel IS NOT NULL AND channel <> '') AS ota,
       SUM(channel = '') AS channel_vide
  FROM sir_vikbooking_orders;
-- 1758   1079   679   0
```

**Aucune ligne ne porte la chaîne vide** : la colonne est soit NULL, soit un nom de canal (`airbnbapi_Airbnb`, `booking.com_Booking.com`, sept valeurs en tout). Le test `empty($booking['channel'])` couvre les deux formes possibles et correspond aux données réelles.

Conséquence assumée, et elle vaut d'être dite : **une réservation OTA d'une chambre Sexcape Room continue de recevoir l'e-mail de L'Instant Clé.** C'est le §4.4 du brief qui l'ordonne (« Ne pas toucher aux messages liés aux canaux OTA, qui ont leurs propres règles »), pas un oubli. À rouvrir le jour où les chambres Sexcape Room seront distribuées en OTA.

---

## 3. Résoudre la marque, ou ne pas la résoudre

### La relecture des chambres

Le hook reçoit `[$who, $booking, $mail]`. `$booking` est la ligne complète de `sir_vikbooking_orders` (`lib.vikbooking.php:6077-6079`), **mais les identifiants de chambre n'y sont pas** : `$ordersrooms` existe dans la portée de la fonction (`:6102-6104`) sans figurer dans les arguments de `trigger` (`:6502`). C'était déjà le constat de phase 0, il est confirmé.

`lme_brands_booking_room_ids()` relit donc `sir_vikbooking_ordersrooms` par `idorder`, en lecture seule, avec un cache de requête : `sendBookingEmail()` déclenche le hook une fois par destinataire, ce qui ferait sinon deux requêtes identiques pour un seul envoi.

### Une seule issue vaut pour une marque

Toutes les chambres de la réservation résolvent, et elles résolvent vers la même marque. Tout le reste est indéterminé :

| Issue | Ce qui la produit | Ce qui se passe |
|---|---|---|
| `ok` | toutes les chambres résolvent vers la même marque | identité de cette marque |
| `unknown_room` | au moins une chambre absente du registre | identité neutre, erreur `unknown_room` **et** `mail_brand_undetermined`, alerte |
| `mixed_brands` | la réservation mêle deux marques | identité neutre, erreur `mail_brand_undetermined`, alerte |
| `no_rooms` | aucune chambre lisible | identité neutre, erreur `mail_brand_undetermined`, alerte |

`mixed_brands` n'est pas théorique. La garde de réservation (`includes/booking-guard.php`) interdit qu'une réservation du tunnel mêle deux marques, mais rien n'interdit à Thomas de composer une telle réservation dans l'administration de Vik.

### L'expéditeur neutre, et pourquoi il garde l'adresse de Vik

Relevé du 15 septembre 2026, `sir_vikbooking_config` :

| Paramètre | Valeur |
|---|---|
| `senderemail` | `info@maisonnette-enchantee.ch` |
| `adminemail` | `info@maisonnette-enchantee.ch` |
| `attachical` | `1` |
| `sitelogo` | `linstant-cle-noir-logo-transp-new.png` |

Et `sir_vikbooking_texts`, `param = 'fronttitle'` : **`L'Instant Clé`**.

Aujourd'hui, donc, **tout e-mail client de cette installation part de `L'Instant Clé <info@maisonnette-enchantee.ch>`**, quelle que soit la chambre réservée — y compris pour Le Boudoir du Désir, chambre 4, qui est vendue et qui est une chambre Sexcape Room.

L'identité neutre du registre (`neutral`) porte `sender_email => null` et `reply_to => null`, ce qui signifie « garder l'adresse que Vik a posée » et ne remplacer que le nom affiché, l'objet et le contenu. Deux raisons :

1. `info@maisonnette-enchantee.ch` ne nomme ni L'Instant Clé ni Sexcape Room. Une marque indéterminée n'en trahit donc aucune ;
2. cette boîte existe, puisqu'elle sert déjà. Inventer une adresse neutre qui n'existe pas ferait rebondir la réponse d'un client, ce qui remplacerait un problème de marque par un problème de courrier perdu.

Le mécanisme est en place pour le jour où une adresse neutre dédiée existera : il suffira de la renseigner. C'est le geste naturel au moment du chantier C2.

### Le piège de `setSender()`, désormais refusé par le registre

```php
// vikbooking/admin/helpers/src/mail/wrapper.php:182
'name' => $address != $name ? $name : null,
```

Passer la même chaîne comme adresse et comme nom efface le nom. Et `getSenderName()` (`wrapper.php:203`) se replie alors sur `VikBooking::getFrontTitle()`, c'est-à-dire **`L'Instant Clé`**. L'égalité ne produirait donc pas un nom manquant : elle produirait la mauvaise marque, silencieusement.

`lme_brands_validate_config()` refuse désormais un registre où `sender_name` égale `sender_email`, et `lme_brands_apply_mail_identity()` refait le contrôle à l'envoi, journalise en erreur et ne pose rien plutôt que de poser une identité bancale.

---

## 4. Les rappels avant séjour : **ils n'empruntent pas ce chemin d'envoi**

C'est la vérification que la phase 3 devait faire. La réponse est non, et elle est démontrée.

### Le chemin réel

La tâche planifiée est `VikBookingCronJobEmailReminder`, `vikbooking/admin/cronjobs/email_reminder.php`. Elle n'appelle **jamais** `sendBookingEmail()`. Elle compose son propre message et l'envoie directement :

```
email_reminder.php:576     $send_res = $this->sendEmailReminder($booking, $message);
email_reminder.php:660     return $vbo_app->sendMail($admin_sendermail, $admin_sendermail, $booking['custmail'], …);
jv_helper.php:123-131      $mail_data = new VBOMailWrapper([...]);       // son propre wrapper
jv_helper.php:137          return VBOFactory::getPlatform()->getMailer()->send($mail_data);
```

`sendBookingEmail()` n'est pas sur ce chemin, donc `onBeforeSendBookingMailVikBooking` n'est jamais déclenché pour un rappel. **`vikbooking_before_send_booking_mail` ne voit pas les rappels.** La réserve que le brief posait au §4.4 est confirmée, pas levée.

### Ce qui part aujourd'hui, et sous quel nom

Le rappel est actif. `sir_vikbooking_cronjobs`, relevé du 15 septembre 2026 :

| id | `cron_name` | `class_file` | `published` | cadence | dernière exécution |
|---|---|---|---|---|---|
| 1 | E-mail Pre-Checkin | `precheckin_reminder` | 0 | horaire | 2026-01-14 |
| 5 | Email pre check-in (new) | `email_reminder` | 0 | quotidien | 2025-01-07 |
| 6 | Check-in info (old) | `email_reminder` | 0 | horaire | 2026-01-14 |
| **7** | **Check-in info** | **`email_reminder`** | **1** | **horaire** | **2026-09-15 09:50:53** |
| 8 | Check-in info (test) | `email_reminder` | 0 | quotidien | 2026-04-20 |

Une seule est publiée, la 7. Ses paramètres : `checktype = checkin`, `remindbefored = 2` (deux jours avant l'arrivée), `ota_res = 1` (réservations OTA **et** directes), `test = OFF`, `subject = « Infos de dernière minute pour votre séjour »`.

L'expéditeur de ce message se construit ainsi :

1. `email_reminder.php:658` : `$admin_sendermail = VikBooking::getSenderMail()` → `info@maisonnette-enchantee.ch` ;
2. `email_reminder.php:660` : l'adresse est passée **à la fois** comme `$from_address` et comme `$from_name` ;
3. `jv_helper.php:90-95` : quand les deux sont égales, Vik tente `JFactory::getApplication()->get('fromname')`, qui sous WordPress se résout en `get_option('fromname')` (`libraries/adapter/config/config.php:156`). Relevé : **cette option n'existe pas** dans `sir_options`. Le repli échoue, les deux restent égales ;
4. `wrapper.php:182` : adresse égale nom, donc `'name' => null` ;
5. `phpmailer.php:47` : `$mailer->setSender([$mail->getSenderMail(), $mail->getSenderName()])`, et `getSenderName()` se replie sur `VikBooking::getFrontTitle()`.

**Conclusion : le rappel avant séjour part de `L'Instant Clé <info@maisonnette-enchantee.ch>`, pour toutes les chambres, Sexcape Room comprises.** Ce n'est pas une hypothèse : c'est la chaîne complète, ligne à ligne, sur une tâche qui s'est exécutée ce matin.

Le corps du message le confirme et l'aggrave : `tpl_text` de la tâche 7 contient, **hors de tout bloc conditionnel**, l'image `https://linstantcle.ch/wp-content/plugins/vikbooking/admin/resources/linstant-cle-noir-logo-transp-new.png` et un titre `<h1>L'Instant Clé</h1>`. Le rappel est donc inconditionnellement habillé L'Instant Clé.

### Le point d'accroche proposé

Un seul hook voit passer **tous** les e-mails de Vik, rappels compris :

```
vikbooking/admin/helpers/src/platform/org/wordpress/mailer.php:52
    VBOFactory::getPlatform()->getDispatcher()->trigger('onBeforeSendMail', [$mail]);
```

soit **`vikbooking_before_send_mail`**, un seul argument, la `VBOMailWrapper`. Il est appelé depuis `prepare()`, donc avant `$service->send($mail)` (`mailer.php:34-37`) : la réécriture y est encore possible.

Son défaut est connu et c'est le seul : **il ne transporte aucun contexte métier.** Ni réservation, ni chambre. Il faut donc retrouver la réservation autrement, et il existe pour cela un chemin qui n'est pas une devinette.

`VikBookingHelperConditionalRules` est un singleton dont le magasin de propriétés est `protected static $helper` (`conditional_rules.php:39`), avec un accesseur public `get($key, $def)` (`:360`). La tâche de rappel y dépose la réservation et ses chambres juste avant de composer :

```
email_reminder.php:646     $message = $this->parseCustomerEmailTemplate($message, $booking, $booking_rooms, $vbo_tn);
email_reminder.php:729-731     VikBooking::getConditionalRulesInstance()->set(['booking', 'rooms'], [$booking, $booking_rooms])->parseTokens($tpl);
email_reminder.php:660     return $vbo_app->sendMail(…);   // puis prepare(), puis onBeforeSendMail
```

L'ordre est le bon : le dépôt précède le déclenchement. Et ce n'est pas une particularité du rappel — `sendBookingEmail()` fait exactement la même chose (`lib.vikbooking.php:5734`), tout comme `admin/controllers/mail.php:65` et les autres tâches planifiées. C'est le mécanisme par lequel Vik transporte lui-même sa réservation jusqu'au moteur de textes conditionnels.

**Le piège, et la parade.** `static::$helper` n'est **jamais vidé** entre deux envois. Lire la réservation qui s'y trouve sans vérification, c'est risquer d'attribuer à un message la marque du message précédent — exactement la « mauvaise marque » que le brief interdit. La parade est un recoupement, pas une confiance :

```php
add_action( 'vikbooking_before_send_mail', function ( $mail ) {
    // 1. Ne rien refaire sur un message déjà traité par le hook métier.
    // 2. Lire la réservation déposée par l'appelant.
    $booking = VikBooking::getConditionalRulesInstance()->get( 'booking' );
    // 3. N'y croire que si le destinataire du message est bien le client
    //    de cette réservation. Sinon : identité neutre, avertissement, alerte.
    if ( ! is_array( $booking ) || empty( $booking['custmail'] )
        || ! in_array( $booking['custmail'], (array) $mail->getRecipient(), true ) ) {
        return; // état périmé ou message sans réservation : on ne devine pas.
    }
    // 4. À partir d'ici, même code que le message client : résolution par
    //    lme_brands_booking_room_ids() puis lme_brands_mail_identity().
}, 10, 1 );
```

Le point 1 est nécessaire : `sendBookingEmail()` passe aussi par `prepare()`, donc par ce hook, **après** `vikbooking_before_send_booking_mail`. Sans marqueur, le message client serait réécrit deux fois. Un `SplObjectStorage` statique des wrappers déjà traités suffit ; `VBOMailWrapper` est `final`, on ne peut pas lui ajouter de propriété.

**Pourquoi ce n'est pas livré avec cette phase.** Le brief demandait de vérifier et de proposer, et il y a une raison de fond de s'y tenir : ce hook touche **tous** les e-mails de Vik — devis, messagerie, factures, toutes les tâches planifiées — et sa corrélation repose sur un état partagé que Vik ne remet pas à zéro. C'est exactement le genre de mécanisme qui mérite un accord explicite avant d'être posé, et une recette à lui. Le code est écrit dans ce document ; le poser est une décision, pas une évidence.

**Et une moitié du problème ne demande aucun code.** La tâche de rappel accepte un paramètre `listings` qui filtre `ordersrooms.idroom IN (...)` (`email_reminder.php:399-406`). Dupliquer la tâche 7 en deux tâches, une par marque, chacune avec ses `listings`, son `subject` et son `tpl_text`, règle le corps et l'objet du rappel dans l'administration de Vik, sans une ligne de code. Ne resterait au code que le nom de l'expéditeur. C'est le partage habituel de ce chantier : la configuration d'abord, le code pour ce que la configuration ne sait pas faire.

### Trois défauts relevés en chemin, dans le rappel qui tourne aujourd'hui

Ils ne concernent pas la séparation des marques, mais ils concernent des clients qui reçoivent un message incomplet. Ils appartiennent à l'administration de Vik, donc à Thomas.

1. **Les chambres 7, 8, 9 et 10 ne déclenchent aucun bloc de check-in.** Les textes conditionnels du rappel ne couvrent que `maisonnette` (chambres 2, 4, 6), `cinema` (1, 5) et `boudoir` (4) — relevé complet dans `sir_vikbooking_condtexts`. Pour une réservation de L'Entracte all inclusive (7), La Parenthèse (8), L'Indécent (9) ou À Huis Clos (10), **tous** ces blocs disparaissent : pas de nom de chambre, pas d'heure d'arrivée ni de départ, pas de code de porte, pas de place de parking, pas d'adresse, pas de plan d'accès. Les chambres 8, 9 et 10 portent `avail = 1` au 15 septembre 2026, donc sont vendables ; la chambre 7 est vendue depuis longtemps.

2. **Quatre textes conditionnels sont inertes.** Les enregistrements 70 à 73 (`at_room_name_lindcent`, `at_room_name_la_parenthse`, `room_name_lindcent`, `room_name_la_parenthse`) ont `rules = '[]'`. Dans `parseTokens()`, `$compliant` est initialisé à `false` (`conditional_rules.php:413`) et la boucle sur les règles ne s'exécute pas : le jeton est **retiré**, toujours. Une règle vide ne veut pas dire « toujours vrai », elle veut dire « jamais ». Ces quatre textes ont été créés pour les chambres 8 et 9 et ne s'afficheront jamais en l'état.

3. **Un jeton du gabarit est mal orthographié.** `tpl_text` de la tâche 7 contient `{condition:access_map_cinema}`, sans espace après le deux-points. L'expression de recherche l'accepte (`/\{condition: ?([a-zA-Z0-9_]+)\}/U`, `conditional_rules.php:375`), mais les clés du dictionnaire sont les jetons stockés tels quels (`:295`), et l'enregistrement 66 porte `{condition: access_map_cinema}`, **avec** espace. La correspondance échoue et le jeton est retiré. **Le plan d'accès du Cinéma n'a jamais été envoyé**, pour les chambres 1 et 5. Un espace.

Aucun de ces trois points n'est corrigé ici : ils vivent dans l'administration de Vik, où seul Thomas écrit, et sont à consigner dans `journal-vik.md` une fois traités.

---

## 5. Le corps du message : pourquoi le code ne le réécrit pas, et ce qui le fera

### Ce que le hook reçoit est déjà cuit

Au moment où `vikbooking_before_send_booking_mail` se déclenche, le corps est entièrement assemblé : gabarit chargé (`lib.vikbooking.php:6384`), jetons `{order_details}`, `{rooms_info}`, `{logo}` remplacés, textes conditionnels appliqués, enveloppe HTML posée (`:6387-6388`). Y poser notre propre gabarit reviendrait à poser un texte dont les jetons ne seront plus jamais parsés.

**Le hook qui aurait servi ne sert pas.** `onBeforeParseEmailTemplate` (`lib.vikbooking.php:5727`) est annoncé par Vik comme permettant « to manipulate the template string », et il se déclenche au bon endroit — `$parsed = $tmpl;` juste avant. Mais il passe son gabarit **par valeur** :

```php
// lib.vikbooking.php:5727 — par valeur, inutilisable
VBOFactory::getPlatform()->getDispatcher()->trigger('onBeforeParseEmailTemplate', [$parsed, $order_info, $rooms]);

// lib.vikbooking.php:5563 — par référence, utilisable
VBOFactory::getPlatform()->getDispatcher()->trigger('onBeforeCreateMailIcalVikBooking', [$recip, $booking, &$ics_str]);
```

Vérifié en exécutant les deux formes : avec `&`, le rappel modifie bien la variable d'origine ; sans `&`, PHP 8 émet `Argument #1 ($p) must be passed by reference, value given` et la valeur d'origine ne bouge pas. Vik connaît l'idiome, il l'emploie ailleurs (`model/quote.php:423` : `[&$tagParameters]`). Il ne l'a pas employé là. Le hook est donc décoratif, et l'utiliser produirait un avertissement PHP à chaque e-mail.

Restaient deux voies, toutes deux écartées :

- **rejouer `VikBooking::parseEmailTemplate()`** avec notre propre gabarit. Il faudrait lui reconstruire `$rooms`, `$rates`, `$options` et `$total`, c'est-à-dire refaire le calcul de prix de Vik — précisément le composant qui a déjà coûté 1800 francs, et qui dériverait à la première mise à jour ;
- **masquer la classe `VikBookingCronJobEmailReminder`** en déclarant la nôtre avant elle (`factory/aware.php:197` teste `class_exists` avant de charger le fichier). C'est recopier neuf cents lignes de Vik pour en changer trois, et les figer.

### Ce que le code fait à la place

`mail.replacements` du registre applique des substitutions littérales à l'objet et au corps, et `mail.signature_html` insère un fragment avant la dernière `</body>`. **Les deux sont vides pour les deux marques, et ce vide est une position, pas un trou.**

Le corps vient du gabarit unique `vikbooking/site/helpers/email_tmpl.php`, qui est celui de L'Instant Clé : logo de la marque, photo de la propriété, blocs « occasions spéciales », « repas », « massages », et treize URL absolues sur `linstantcle.ch`. Remplacer « L'Instant Clé » par « Sexcape Room » dans ce corps ne le rendrait pas sexcapien. Cela le rendrait faux — un client Sexcape Room lirait une invitation à réserver un petit-déjeuner à la villa — et cela ferait taire le contrôle de fuite, dont c'est justement le travail de signaler que ce corps n'est pas encore le bon.

Deux précisions sur ce gabarit, pour mémoire. Il est modifiable depuis l'administration de Vik et **survit aux mises à jour** : `VikBookingUpdateManager::getTemplateFiles()` (`libraries/update/manager.php:38`) le recense parmi les fichiers dont le contenu est sauvegardé en option et restauré. Le modifier n'est donc pas la faute que la règle absolue n°1 interdit — c'est un point de personnalisation prévu. Mais il reste **unique pour toute l'installation** : il ne peut pas porter deux marques à lui seul.

### Ce qui séparera le corps par marque : la configuration, pas le code

Le mécanisme existe déjà, il est natif, et **il est déjà en service sur cette installation**.

Toute la copie des e-mails de L'Instant Clé vit dans `sir_vikbooking_condtexts` : 72 enregistrements au 15 septembre 2026, chacun un jeton `{condition: …}` et un jeu de règles. Et 21 d'entre eux utilisent déjà la règle native `rooms.php`, qui conditionne un bloc aux chambres réservées — par exemple `{condition: front_door_pin_maisonnette}`, règle `rooms` = 2, 4, 6.

Séparer le corps par marque se fait donc ainsi, sans une ligne de code :

1. pour chaque bloc du gabarit qui doit différer, créer deux textes conditionnels au lieu d'un, chacun avec une règle `rooms.php` : chambres 1, 2, 7, 8 pour L'Instant Clé, chambres 4, 9, 10 pour Sexcape Room ;
2. poser les deux jetons côte à côte dans `email_tmpl.php`. Celui dont les règles ne sont pas satisfaites est retiré, l'autre reste — c'est déjà ce que fait le gabarit du rappel avec `{condition: at_room_name_maisonnette}{condition: at_room_name_cinema}` ;
3. faire de même pour le logo : le jeton `{logo}` de Vik rend le logo global (`sitelogo = linstant-cle-noir-logo-transp-new.png`), donc il doit être encadré, pas laissé nu.

Le contrôle de fuite du code dira quand c'est fini : il se taira.

**Ce que cela coûte, et qu'il faut savoir avant de s'y engager.** La correspondance chambre → marque existerait alors à deux endroits : dans `config/brands.php`, et dans les règles `rooms` saisies dans Vik. Les textes conditionnels 70 à 73 montrent exactement comment cette seconde copie se désynchronise en silence. La parade n'est pas de renoncer — c'est le seul mécanisme qui atteigne le corps du message *et* celui du rappel — mais de la surveiller : un contrôle des règles `rooms.php` contre le registre a sa place dans l'écran de santé, ou dans l'oracle de la phase 5. Non construit ici.

---

## 6. Ce qui bloque le déploiement, et qui n'est pas du code

**Les quatre adresses du registre ne sont pas vérifiées.** `config/brands.php` déclare `reservations@linstantcle.ch` et `reservations@sexcaperoom.ch` en expéditeur et en adresse de réponse. Ces valeurs viennent de la phase 1, où elles étaient annoncées comme provisoires. Or la phase 3 les rend agissantes : au déploiement, l'expéditeur des e-mails clients passe de `info@maisonnette-enchantee.ch` à ces adresses — **pour les deux marques, L'Instant Clé comprise**.

Avant de déployer, il faut donc que, pour chacune des quatre :

- la boîte existe et soit relevée ;
- le domaine publie SPF, DKIM et DMARC ;
- le service d'envoi transactionnel du chantier C2 soit autorisé à émettre en son nom.

Un expéditeur `@sexcaperoom.ch` émis par le serveur qui héberge linstantcle.ch part en indésirable sans cet alignement — c'est déjà le §4.4 du brief. Ce que la phase 3 ajoute au constat, c'est que **le chantier C2 porte sur trois domaines et non deux** : `linstantcle.ch`, `sexcaperoom.ch`, et `maisonnette-enchantee.ch`, dont l'adresse sert aujourd'hui d'expéditeur à toute l'installation et reste l'expéditeur de repli de l'identité neutre.

Tant que ce point n'est pas tranché, la phase 3 reste dans le dépôt. Elle n'est pas déployée, ce qui est de toute façon la consigne.

---

## 7. Écarts relevés, sans instruction d'exécution

Constatés en passant, tous en lecture seule. Aucun n'est corrigé par la phase 3.

1. **L'allemand est une langue réelle et n'est déclaré nulle part.** 453 des 1758 réservations portent `lang` = `de-CH` ou `de-DE`, contre 1057 en `fr-FR` et 90 en `en-US`. Le registre déclare `languages => ['en', 'fr']` pour L'Instant Clé. Conséquence aujourd'hui : un client germanophone reçoit un objet de repli — et, pour la garde de réservation de la phase 2, un écran de refus en anglais. Corriger `languages` est une décision de marque avec des effets de bord, pas un geste de code : laissé tel quel, signalé ici. L'objet neutre, lui, porte une entrée `de`, qui ne coûte rien.

2. **Le nom de la chambre 7 diffère entre Vik et le registre.** `sir_vikbooking_rooms.name` porte `L'Entracte - All Inclusive`, le registre porte `L'Entracte all inclusive`, et le chapitre 2 du brief tranche pour la seconde forme, « partout : dans Vik, dans le registre, dans le code, dans Airtable, dans les journaux ». C'est donc Vik qui est à aligner, par Thomas.

3. **La chambre 10 s'appelle `A Huis Clos` dans Vik**, sans accent, et `À Huis Clos` dans le registre. Même nature, même remède.

4. **Les groupements de textes conditionnels ne suivent pas les groupes de disponibilité.** Vik raisonne en `maisonnette` (2, 4, 6) et `cinema` (1, 5) ; le brief raisonne en villa Aparté (2, 4) et villa Entracte (1, 7, 10). Les deux découpages sont défendables — l'un décrit un bâtiment, l'autre un calendrier — mais ils ne se recouvrent pas, et la chambre 7 tombe entre les deux. À arbitrer avant de dupliquer les textes conditionnels par marque (§5), pas après.

5. **L'option `home` du site vaut exactement `https://linstantcle.ch`**, comme `siteurl`. La réserve posée en phase 1 sur l'exactitude du `host` de L'Instant Clé dans le registre est levée : la valeur est juste. Commentaire mis à jour dans `config/brands.php`.

---

## 8. Recette

Les critères 6 et 11 du chapitre 8 du brief. Procédures détaillées dans `mu-plugins/lme-brands/README.md`, section « Procédures de vérification manuelle », points 9 à 12.

| # | Assertion | Vérifiable par |
|---|---|---|
| 1 | Une réservation Sexcape Room produit un e-mail client dont l'expéditeur, le nom affiché et l'adresse de réponse sont ceux de Sexcape Room | recette manuelle, en-têtes du message |
| 2 | Une réservation L'Instant Clé produit l'e-mail de L'Instant Clé, objet natif inchangé | recette manuelle |
| 3 | L'e-mail de l'administrateur n'est pas touché | recette manuelle |
| 4 | Une réservation OTA n'est pas touchée | recette manuelle |
| 5 | Une réservation mêlant deux marques part sous l'identité neutre, journalise `mail_brand_undetermined` et alerte | recette manuelle, réservation composée dans l'administration |
| 6 | Une réservation d'une chambre absente du registre fait la même chose, plus `unknown_room` | recette manuelle |
| 7 | La pièce jointe `.ics` d'un client Sexcape Room porte `SUMMARY` (`lib.vikbooking.php:5536`) et `LOCATION` (`:5553`) = `Sexcape Room` | ouvrir le fichier joint |
| 8 | Tant que le corps n'est pas séparé par marque, tout e-mail Sexcape Room journalise `mail_brand_leak` et alerte | `debug.log` |
| 9 | Le jour où le corps est séparé, `mail_brand_leak` se tait — **c'est la vérification automatique du critère n°6 du brief** | `debug.log` |
| 10 | Le rappel avant séjour d'une réservation Sexcape Room part sous l'identité Sexcape Room | **non tenu aujourd'hui.** Voir §4 |

Le point 10 est le seul critère de sortie que cette phase ne tient pas, et il est tenu ouvert exprès : le brief demandait de le vérifier et de proposer, pas de le livrer.

---

## 9. Ce que le plan de marche doit intégrer

**Je n'écris pas dans `plan-de-marche.md`** : il appartient à Cowork (règle du fichier unique, §1 du plan). Ce qui suit est la matière à y reporter, rangée par chantier, pour que rien de ce constat ne reste dans une conversation.

### Chantier B, moteur

| # | Quoi | Tenu par | Attend |
|---|---|---|---|
| B3 | **Fait.** Commit `Phase 3 : l'e-mail client porte la marque de sa réservation` | Code | revue par Cowork → `docs/revue-phase-3.md` |
| **B5** | **Nouveau.** Marquer les rappels avant séjour par `vikbooking_before_send_mail` | Code | décision de Thomas sur le point d'accroche (§4), et B5 bis ci-dessous |

Deux corrections de dépendances dans le tableau B existant :

- B3 était noté « attend B1, et C2 pour la recette ». À lire désormais : **B3 est écrit, et ne se déploie pas sans C2.** Les quatre adresses du registre deviennent agissantes au déploiement, y compris pour L'Instant Clé, qui fonctionne aujourd'hui (§6) ;
- B5 est volontairement placé après B4 et non avant : le point d'accroche des rappels touche tous les e-mails de Vik, et il vaut mieux l'ouvrir sur une installation dont le tunnel est déjà recetté.

### Chantier C, infrastructure

**C2 s'élargit, et c'est le point à reporter en priorité.** Il était écrit « SPF, DKIM et DMARC sur sexcaperoom.ch, plus un service d'envoi transactionnel authentifié pour les deux domaines ». Trois domaines sont en jeu, pas deux :

| Domaine | Rôle | État |
|---|---|---|
| `maisonnette-enchantee.ch` | expéditeur **actuel** de toute l'installation, et expéditeur de repli de l'identité neutre | à aligner |
| `linstantcle.ch` | expéditeur cible de L'Instant Clé | boîte `reservations@` à vérifier |
| `sexcaperoom.ch` | expéditeur cible de Sexcape Room | boîte `reservations@` à créer |

Ajouter, comme sous-étape explicite : **vérifier que les quatre boîtes du registre existent et sont relevées**, avant tout déploiement de B3.

### Chantier D, habillage

D1 a relevé le système visuel du **site**. Il lui manque son pendant : **le contenu des e-mails par marque** — corps, signature, images, liens. C'est le livrable qui débloque la séparation du §5, et il ne peut venir que de Cowork et de Thomas. Tant qu'il manque, l'alerte `mail_brand_leak` restera allumée, ce qui est le comportement voulu mais pas un état d'arrivée.

### Chantier E, surveillance

Ajouter un point : **contrôler les règles `rooms.php` des textes conditionnels contre le registre**. Séparer le corps des e-mails par marque (§5) crée une seconde copie de la correspondance chambre → marque, saisie à la main dans Vik. Les textes conditionnels 70 à 73 montrent exactement comment cette copie se désynchronise en silence. Sa place est dans l'écran de santé ou dans un scénario Make, à trancher.

### Nouveau chantier F — contenu des e-mails de Vik

Trois défauts qui touchent des clients qui paient aujourd'hui, **indépendants de toute histoire de marque**. Gestes de Thomas dans l'administration de Vik, à consigner dans `journal-vik.md`.

| # | Quoi | Portée |
|---|---|---|
| F1 | Corriger `{condition:access_map_cinema}` en `{condition: access_map_cinema}` dans le gabarit de la tâche 7 | chambres 1 et 5, plan d'accès jamais envoyé |
| F2 | Donner une règle `rooms.php` aux textes conditionnels 70 à 73, aujourd'hui inertes | chambres 8 et 9 |
| F3 | Créer les blocs de check-in manquants pour les chambres 7, 8, 9 et 10 | quatre chambres sans nom, ni horaire, ni code de porte, ni adresse dans leur rappel |

F1 est une minute de travail et le plus ancien des trois. F3 doit précéder la première vente des chambres 8, 9 et 10, qui portent déjà `avail = 1`.

**Arbitrage préalable à F3 et au §5 :** Vik groupe ses textes conditionnels par bâtiment — `maisonnette` (2, 4, 6) et `cinema` (1, 5) — là où le brief groupe par calendrier — villa Aparté (2, 4) et villa Entracte (1, 7, 10). Les deux découpages sont défendables, ils ne se recouvrent pas, et la chambre 7 tombe entre les deux. À trancher avant de dupliquer les textes conditionnels, pas après.

### Recette, chapitre 8 du brief

- **Critère n°6** : devient auto-vérifié. L'absence de `mail_brand_leak` dans le journal en est la preuve continue, pas seulement un contrôle de livraison (§8, point 9) ;
- **Critère n°6 bis, à ajouter** : « Le rappel avant séjour d'une réservation Sexcape Room part de l'expéditeur Sexcape Room, avec le contenu de cette marque. » Non tenu aujourd'hui, et c'est le seul critère de sortie que la phase 3 laisse ouvert.

---

## Verdict de phase

**Le message client de réservation est par marque.** Expéditeur, adresse de réponse, objet, pièce jointe iCal, et le corps par un mécanisme dont le vide actuel est documenté et alarmé. Marque indéterminée : identité neutre, erreur journalisée, alerte, jamais la mauvaise marque. Aucun second envoi, aucun fichier de Vik modifié, 96 tests unitaires au vert.

**Trois choses restent ouvertes, et aucune n'est du code à écrire ici :**

1. les quatre adresses d'expéditeur, à créer et à aligner (§6) — bloque le déploiement ;
2. le corps par marque, à séparer dans les textes conditionnels de Vik (§5) — Thomas, avec la copie des chantiers D et B3 ;
3. les rappels avant séjour, dont le point d'accroche est proposé (§4) — à ouvrir sur décision.

Ce qu'il faut en reporter dans `plan-de-marche.md`, chantier par chantier, est au §9 — je ne l'écris pas moi-même, ce fichier appartient à Cowork.

**Recommandation de séquencement.** Traiter d'abord les trois défauts du §4 (chantier F proposé au §9) : ils touchent des clients qui paient aujourd'hui, indépendamment de toute histoire de marque, et le plan d'accès du Cinéma manquant depuis un temps inconnu est un espace de trop dans un jeton.
