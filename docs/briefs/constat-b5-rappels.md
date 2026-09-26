# Constat, chantier B5 — la source des rappels avant séjour

17 septembre 2026. Ce que B5 pose, ce qu'il refuse de poser, et les deux réserves qu'il laisse ouvertes parce qu'aucune n'est un geste de code.

**Complété le 26 septembre 2026** : Thomas a décidé que l'adresse de réponse des messages de B5 s'aligne sur l'expéditeur de la marque. Le §2 est corrigé en conséquence, le §3 est rectifié sur deux lignes, et le §10 établit la décision à la source et décrit ce qui a changé.

Suite directe du §4 de `constat-phase-3-emails.md`, qui avait établi ligne à ligne que les rappels n'empruntent pas le chemin d'envoi de la phase 3, et proposé le point d'accroche sans le poser. Thomas a tranché : on le pose.

Source lue : copie SFTP de Vik Booking **1.8.14** dans `.local/`, en lecture seule. Aucune écriture en base, aucune modification de configuration, **rien de déployé**.

Règle de ce document, la même que pour les précédents : chaque affirmation cite la ligne qui l'établit. Ce qui n'est pas établi est annoncé comme non établi.

---

## 1. Ce que B5 livre

`mu-plugins/lme-brands/includes/mail-brand.php` gagne un second point d'accroche, et `includes/core.php` deux fonctions pures de plus. Aucun fichier de Vik modifié.

| Hook WordPress | Émis par | Ce qu'on en fait |
|---|---|---|
| `vikbooking_before_send_mail` | `platform/org/wordpress/mailer.php:52` | expéditeur, nom d'affichage et, depuis le 26 septembre, adresse de réponse — **rien d'autre** (§10) |

110 tests unitaires passent (`php mu-plugins/lme-brands/tests/test-core.php`), dont 14 ajoutés par ce chantier. **Rien n'est déployé.**

Le hook est déclenché depuis `prepare()`, donc avant `$service->send($mail)` (`mailer.php:34-37`) : la réécriture y est encore possible. Il ne retourne rien — `trigger()` est un `do_action_ref_array` (`dispatcher.php:32`) —, donc il n'annule aucun envoi et ne peut pas en produire un second. Le nom du hook se déduit de `getHook()` (`dispatcher.php:73-88`) : `onBeforeSendMail` → `vikbooking_before_send_mail`.

---

## 2. Ce qui est posé, et ce qui ne l'est pas

**L'expéditeur et le nom d'affichage** — et depuis le 26 septembre l'adresse de réponse, voir §10. Ni objet, ni corps.

C'est la consigne, et elle a sa raison. Le corps d'un rappel ne vient pas d'un gabarit de fichier mais du champ `tpl_text` de la tâche planifiée, saisi dans l'administration de Vik. Il contient aujourd'hui, **hors de tout bloc conditionnel**, le logo et un `<h1>L'Instant Clé</h1>` (§4 du constat de phase 3). Y substituer des chaînes depuis le code reviendrait à réécrire à l'aveugle un texte que Thomas tient à la main, et à créer une seconde copie de la correspondance chambre → marque là où le mécanisme natif des textes conditionnels fait déjà le travail. C'est l'argument du §5 du constat de phase 3, et il vaut mot pour mot ici : **B5 corrige l'expéditeur, le chantier F corrige le contenu, et les deux sont nécessaires.**

**L'adresse de réponse n'était pas posée non plus. Paragraphe périmé depuis le 26 septembre, gardé pour la trace ; la décision et le code sont au §10.** Son motif est tombé avec C2, fait le 15 septembre : la boîte `reservations@sexcaperoom.ch` existe, par groupe Google (`plan-de-marche.md`, tableau C). La tâche de rappel passe `$admin_sendermail` en `$reply_address` (`email_reminder.php:660`), donc la réponse d'un client arrive aujourd'hui sur `info@maisonnette-enchantee.ch`, une boîte qui existe et qui est relevée. La poser par marque ferait basculer cette réponse vers `reservations@sexcaperoom.ch`, une boîte qui n'existe pas encore (§6 du constat de phase 3, chantier C2). Ne pas y toucher, c'est garder un chemin de réponse qui fonctionne au lieu d'en inventer un qui rebondit.

**Pas de contrôle de fuite sur ce chemin, et c'est une décision, pas un oubli.** La phase 3 fouille le message client à la recherche du nom, de l'hôte et de l'adresse des autres marques, et alerte : c'est ce qui rend le critère de recette n°6 vérifiable en continu. Ici, on sait déjà que le corps nomme L'Instant Clé, c'est inventorié en chantier F : une alerte à chaque envoi répéterait un fait connu au lieu d'en signaler un nouveau. **Le jour où F sera fait, brancher `lme_brands_report_mail_leak()` sur ce chemin est le geste qui garde F fait.** Il tient en une ligne, il n'est pas écrit aujourd'hui, et c'est à consigner au chantier E.

---

## 3. Retrouver la réservation sans la deviner

### Le magasin partagé

`vikbooking_before_send_mail` reçoit un seul argument, la `VBOMailWrapper`, et aucun contexte métier : ni réservation, ni chambre. C'est son seul défaut, et il était connu.

La réservation se retrouve dans `VikBookingHelperConditionalRules`, où l'émetteur la dépose avant de composer son message. C'est le mécanisme natif par lequel Vik transporte sa réservation jusqu'au moteur de textes conditionnels, pas un détournement : le magasin est un `protected static $helper` (`conditional_rules.php:39`) avec un accesseur public `get($key, $def)` (`:360`).

Relevé exhaustif des dépôts dans Vik Booking 1.8.14 et VikChannelManager :

| Fichier | Ce qu'il émet |
|---|---|
| `admin/cronjobs/email_reminder.php:729` | **le rappel avant séjour** — tâche 7, publiée, horaire |
| `admin/cronjobs/precheckin_reminder.php:567` | rappel de pré-enregistrement — tâche 1, non publiée |
| `admin/cronjobs/invoices_generator.php:400` | factures |
| `admin/controllers/mail.php:65` | **aperçu** de l'éditeur visuel (`preview_visual_editor()`), adressé à `dummy@email.com` (`:69-77`) : jamais rattaché, le recoupement échoue toujours. Rectifié le 26 septembre ; la ligne disait « message envoyé à la main » |
| `admin/helpers/widgets/bulk_messaging.php:1170` | messagerie en lot |
| `site/helpers/lib.vikbooking.php:5734` | message client de réservation (`parseEmailTemplate()`) |
| `site/helpers/lib.vikbooking.php:9821`, `:9904` | gabarits SMS, administrateur et client. **Rectifié le 26 septembre** : `:9904` (`parseCustomerSMSTemplate()`) sert aussi le **message envoyé à la main** depuis l'écran d'une réservation, `sendcustomemail()` l'appelant pour composer le corps (`admin/controller.php:10043`) avant `sendMail()` (`:10052`) |
| `site/helpers/lib.vikbooking.php:10201`, `:11462` | gabarits de facture et de document de pré-enregistrement |
| `admin/helpers/einvoicing/drivers/mydata_aade.php:2929` | facturation électronique grecque — hors sujet ici |

L'ordre est le bon partout : le dépôt précède la composition, qui précède l'envoi, qui déclenche `prepare()`. Pour le rappel : `email_reminder.php:646` compose, `:729-731` dépose, `:660` envoie.

### Le piège, et la parade

`static::$helper` n'est **jamais vidé** entre deux envois. Une exécution de la tâche de rappel boucle sur toutes les arrivées à deux jours (`email_reminder.php:478`) : le magasin garde donc, à tout instant, la réservation du message précédent. Le lire sans vérification, c'est risquer d'attribuer à un message la marque du message d'avant — exactement la « mauvaise marque » que le §4.4 du brief interdit.

La parade est un recoupement, pas une confiance : **on n'y croit que si le client de la réservation déposée est parmi les destinataires du message**. C'est `lme_brands_mail_booking_matches_recipients()`, fonction pure, dix cas couverts par les tests. Si le recoupement échoue, on ne touche à rien : le message part comme avant ce plugin, ce qui est un état connu, jamais une marque devinée.

Ce recoupement tient parce que chaque tour de la boucle recompose son message avec **sa** réservation avant de l'envoyer : deux réservations de marques différentes dans la même exécution produisent deux messages de marques différentes. C'est la contre-épreuve n°5 de la recette, et c'est celle qui compte.

**Ce que le recoupement ne couvre pas, et qu'il faut savoir.** Si deux messages successifs vont au même client et qu'un seul dépose sa réservation, le second prendrait la marque du premier. Les deux concernent alors le même client ; la marque serait fausse seulement si ce client a des réservations dans les deux marques et que le second message ne dépose rien. Aucun émetteur relevé ci-dessus n'est dans ce cas — tous déposent. C'est une réserve théorique, énoncée parce qu'elle est le seul angle mort de la parade, pas parce qu'un chemin réel la produit.

**Le mode test de la tâche ne casse rien.** Quand `test = ON`, la tâche écrase `$booking['custmail']` par l'adresse de test (`email_reminder.php:559`) **avant** de passer le tableau à `sendEmailReminder()`, donc avant le dépôt. Le recoupement porte sur la même valeur des deux côtés. La tâche 7 est de toute façon en `test = OFF`.

---

## 4. Un message, un seul traitement

Le message client de réservation traverse **les deux** hooks, sur le même objet : `vikbooking_before_send_booking_mail` (`lib.vikbooking.php:6502`), puis `send()` (`:6504`), puis `prepare()`, puis `vikbooking_before_send_mail`. Sans garde, il serait traité deux fois, et la copie de l'administrateur — que la phase 3 laisse délibérément intacte — serait reprise par le hook générique.

Le hook métier marque donc chaque message qu'il a vu, dès son entrée, **avant même de décider quoi que ce soit** : sa décision de ne rien faire pour l'administrateur ou pour une réservation OTA est une décision, et le hook générique ne doit ni la refaire, ni la contredire.

Trois choix d'implémentation, chacun pour une raison :

1. **Jamais `spl_object_id()`.** PHP réattribue un identifiant après libération de l'objet. Un identifiant réutilisé ferait passer un rappel pour un message déjà traité, et ce rappel partirait sous la mauvaise marque — le défaut exact que tout ce chantier évite.
2. **`WeakMap`, avec `SplObjectStorage` en repli** avant PHP 8.0. `VBOMailWrapper` est `final` (`wrapper.php:37`) : on ne peut pas lui ajouter de propriété, la marque doit vivre à côté. `WeakMap` ne retient pas ses clés ; `SplObjectStorage` les retient, d'où le repli et non l'inverse. Les deux répondent à `isset()` et à l'affectation par index, donc le code est le même.
3. **Le hook générique ne marque rien.** `prepare()` ne se déclenche qu'une fois par envoi, et reposer la même identité une seconde fois ne changerait rien. Marquer coûterait, sur le repli `SplObjectStorage`, de retenir en mémoire chacun des messages d'une exécution de la tâche de rappel. Seul le hook métier marque, et il ne voit qu'une poignée de messages par requête.

---

## 5. Le périmètre réel dépasse le rappel, et c'est assumé

Le recoupement retient tout message adressé au client d'une réservation directe dont la marque se résout. Le tableau du §3 dit lesquels : le rappel avant séjour, mais aussi le rappel de pré-enregistrement, les factures, la messagerie en lot et le message envoyé à la main depuis l'écran d'une réservation.

Ce n'est pas un débordement. **Tous** partent aujourd'hui de `L'Instant Clé <info@maisonnette-enchantee.ch>`, y compris pour une chambre Sexcape Room, et la marque de leur réservation est la bonne pour chacun d'eux. Restreindre B5 au seul rappel demanderait de distinguer l'émetteur, ce que le hook ne permet pas sans deviner : ni le magasin partagé, ni la `VBOMailWrapper` ne disent d'où vient le message. Un tel filtre reposerait sur l'objet du message ou sur la forme de l'expéditeur, deux signaux fragiles qu'une modification de la tâche dans l'administration casserait en silence.

La recette, elle, porte sur le rappel avant séjour : c'est le message qui part toutes les heures, et c'est celui qu'un client du Boudoir du Désir reçoit déjà.

Restent dehors, et c'est voulu :

- les messages qui ne vont pas au client d'une réservation — notifications à l'administrateur, devis sans réservation déposée ;
- les réservations OTA (`channel` renseigné), §4.4 du brief. La tâche 7 les notifie pourtant (`ota_res = 1`) : à rouvrir le jour où les chambres Sexcape Room seront distribuées en OTA, comme pour le message client ;
- les messages que le hook métier a déjà tranchés.

---

## 6. Marque indéterminée

Même règle que la phase 3, même fonction pure, même journal :

| Issue | Identité posée | Journal |
|---|---|---|
| `ok` | expéditeur et nom de la marque | rien |
| `unknown_room` | **neutre** | `unknown_room` + `mail_brand_undetermined`, alerte |
| `mixed_brands` | **neutre** | `mail_brand_undetermined`, alerte |
| `no_rooms` | **neutre** | `mail_brand_undetermined`, alerte |

L'identité neutre porte `sender_email => null`, ce qui veut dire « garder l'adresse que Vik a posée » : seul le nom d'affichage cesse de nommer une marque, et la réponse du client continue d'arriver sur une boîte relevée. Le code du journal est le même que pour le message client — c'est la même situation —, et le contexte porte `chemin: vikbooking_before_send_mail` pour distinguer les deux chemins.

---

## 7. Ce qui bloque le déploiement

**Le même point qu'en phase 3, et il n'a pas bougé.** Les quatre adresses du registre ne sont pas vérifiées : au déploiement, l'expéditeur des rappels passe de `info@maisonnette-enchantee.ch` à `reservations@linstantcle.ch` et `reservations@sexcaperoom.ch` — pour les deux marques, L'Instant Clé comprise. Sans boîte relevée, sans SPF, DKIM et DMARC alignés et sans autorisation d'émettre par le service transactionnel du chantier C2, ces rappels partent en indésirable. Voir §6 de `constat-phase-3-emails.md`.

**Un point qui lui est propre.** La tâche 7 tourne **toutes les heures, en production, depuis longtemps**. B3 et B5 ne se déploient pas séparément : le jour de la bascule, le rappel change d'expéditeur en même temps que la confirmation, sur des réservations déjà prises. Rien dans le code ne l'empêche, mais cela se prévoit — et cela se vérifie en préproduction d'abord, jamais en production.

Tant que C2 n'est pas tranché, B5 reste dans le dépôt. Il n'est pas déployé, ce qui est de toute façon la consigne.

---

## 8. Recette

Procédure détaillée dans `mu-plugins/lme-brands/README.md`, section « Procédures de vérification manuelle », point 14. Résumé des assertions :

| # | Assertion | Vérifiable par |
|---|---|---|
| 1 | Un rappel de chambre Sexcape Room part de `Sexcape Room <reservations@sexcaperoom.ch>` | en-têtes bruts du message |
| 2 | Son objet et son corps sont **inchangés**, logo et titre L'Instant Clé compris | lecture du message, attendu tant que F n'est pas fait |
| 3 | Un rappel de chambre L'Instant Clé part de `L'Instant Clé <reservations@linstantcle.ch>` | en-têtes bruts |
| 4 | Un rappel de réservation à marques mêlées part sous l'identité neutre et alerte | en-têtes, `debug.log` |
| 5 | Dans une **même exécution** de la tâche, deux réservations de marques différentes produisent deux messages de marques différentes | en-têtes des deux messages |
| 6 | Le message client de réservation n'est ni doublé, ni retraité, et la copie de l'administrateur reste intacte | recette manuelle, point 9 du README |
| 7 | Un rappel de réservation OTA n'est pas touché | en-têtes, `debug.log` vide de lignes `lme-brands` |

Le critère n°5 est celui qui tient tout le reste : il prouve que la réservation est relue entre deux envois, donc que la parade contre l'état périmé fonctionne sur le seul chemin où l'état périmé existe réellement.

**Le critère de sortie n°6 bis du brief** — « Le rappel avant séjour d'une réservation Sexcape Room part de l'expéditeur Sexcape Room, avec le contenu de cette marque » — est **à moitié tenu** : l'expéditeur l'est, le contenu attend le chantier F. Le point 10 du chapitre 8 du constat de phase 3, laissé ouvert exprès, se ferme pour sa première moitié.

---

## 9. Ce que le plan de marche doit intégrer

**Je n'écris pas dans `plan-de-marche.md`** : il appartient à Cowork. Matière à y reporter :

### Chantier B

| # | Quoi | État |
|---|---|---|
| B5 | Marquer la source des rappels avant séjour | **Fait.** Attend revue par Cowork, et C2 pour le déploiement |

À corriger dans le tableau B : B5 était noté « ouvert, après B4 ». Il est écrit, et **ne se déploie pas sans C2**, exactement comme B3 — mêmes quatre adresses, même conséquence sur L'Instant Clé.

### Chantier E, surveillance

Ajouter un point : **brancher le contrôle de fuite sur le chemin des rappels le jour où F sera fait.** `lme_brands_report_mail_leak()` existe et est déjà appelé pour le message client ; l'appeler aussi depuis `lme_brands_brand_mail_source()` rendra le contenu des rappels vérifiable en continu, comme il l'est pour la confirmation. Une ligne, pas faite aujourd'hui, et volontairement : tant que F n'est pas fait, elle crierait un fait déjà connu.

### Chantier F

Inchangé, et sa nécessité est renforcée : un rappel qui part du bon expéditeur avec le logo et le titre de l'autre marque est **plus incohérent** qu'avant, pas moins. F1, F2 et F3 restent des gestes de Thomas dans l'administration de Vik, à consigner dans `journal-vik.md`.

---

## 10. L'adresse de réponse — décision du 26 septembre 2026

**Décision de Thomas** : le Reply-To des messages que B5 réécrit s'aligne sur l'expéditeur de la marque de la réservation, comme pour le message client de la phase 3. Marque mêlée ou absente : comportement d'avant, et journal.

Ce qui l'a motivée : la revue de B5 du 24 septembre (`plan-de-marche.md`, « La revue de B5 », point 3) et la recette F3 sur staging13 (`constat-recette-f3.md` §5.4), où les trois rappels partaient bien de `reservations@sexcaperoom.ch` ou `reservations@linstantcle.ch` mais portaient tous `Reply-To: …@maisonnette-enchantee.ch`. Un client Sexcape Room qui répondait au rappel voyait un troisième domaine.

Source : copie SFTP de Vik Booking **1.8.14** dans `.local/`, en lecture seule. `CLAUDE.md` cite 1.8.15 pour une autre ligne : les numéros ci-dessous sont ceux de 1.8.14 et **n'ont pas été relus sur 1.8.15**. À refaire lors de la recette sur staging, la vérification tenant en cinq `grep`.

### 10.1 Comment Vik passe l'adresse de réponse, à la source

Le chemin est le même pour le rappel et pour tous les émetteurs qui passent par `VboApplication::sendMail()` :

1. **L'émetteur la passe en quatrième argument.** `sendMail($from_address, $from_name, $to, $reply_address, …)` (`admin/helpers/jv_helper.php:68`). Le rappel passe `$admin_sendermail` aux positions 1, 2 et 4 (`admin/cronjobs/email_reminder.php:658-660`), soit `senderemail` de la configuration, repli sur `adminemail` (`site/helpers/lib.vikbooking.php:3587-3592`).
2. **`sendMail()` la range dans le wrapper.** `new VBOMailWrapper([... 'reply' => $reply_address ...])` (`jv_helper.php:123-131`). `bind()` traduit la clé `reply` en appel à `setReply()` (`admin/helpers/src/mail/wrapper.php:133-151`).
3. **Le wrapper n'en garde qu'une.** `private $reply`, chaîne ou null (`wrapper.php:58`) ; `setReply()` la **remplace** (`:261-266`) ; `getReply()` la rend (`:273-276`). Pas de liste, donc pas d'accumulation possible.
4. **Le hook passe avant sa lecture.** `send()` appelle `prepare()` (`platform/org/wordpress/mailer.php:34`), qui déclenche `onBeforeSendMail` (`:52`), puis seulement `$service->send($mail)` (`:37`).
5. **La lecture a lieu à l'envoi.** Le service PHPMailer fait `if ($mail->getReply()) $mailer->addReplyTo($mail->getReply())` (`admin/helpers/src/mail/service/phpmailer.php:52-56`). Une valeur vide ne produit aucun en-tête.
6. **L'objet d'envoi est neuf à chaque message.** `JFactory::getMailer()` rend `new JMail` sans cache (`libraries/adapter/factory/factory.php:185-197`, commentaire « avoids having a JMail instance already filled-in »). Le Reply-To d'un rappel ne peut donc pas déborder sur le suivant dans une même exécution de la tâche.
7. **L'en-tête final.** `JMail::Send()` compose `Reply-to: <adresse>` et appelle `wp_mail()` (`libraries/adapter/mail/mail.php:172-178`, `:201`).

**Conséquence : `$mail->setReply()` dans `vikbooking_before_send_mail` suffit**, par le mutateur public de la classe `final`, sans toucher au greffon. C'est le même mutateur que la phase 3 emploie déjà (`lme_brands_apply_mail_identity()`).

En aval, rien ne réécrit l'en-tête sur ce qui a été observé. Le garde-fou de messagerie de la préproduction ne touche pas au Reply-To et le consigne (`mu-plugins/lme-mail-guard/includes/core.php:388`). WP Mail SMTP avec le mailer `gmail` a transmis le Reply-To tel quel sur les trois rappels de la recette F3 (`constat-recette-f3.md` §5.4). **Non établi** : le comportement d'un autre greffon qui filtrerait `wp_mail` en production. Aucun n'est connu à ce jour, mais aucun relevé ne l'exclut.

### 10.2 Ce qui change dans le dépôt

| Fichier | Changement |
|---|---|
| `includes/core.php` | Nouvelle fonction pure `lme_brands_mail_source_reply_to( $identity, $sender_applied )` : l'adresse de réponse de la marque, ou null pour garder celle de Vik |
| `includes/mail-brand.php` | `lme_brands_brand_mail_source()` pose `setReply()` après l'expéditeur ; exige `setReply()` et `getReply()` du wrapper ; le journal de marque indéterminée porte `reply_to_kept` |
| `tests/test-core.php` | 6 tests : 5 sur la fonction pure, 1 sur le registre réel |
| `tests/test-mail-source.php` | **nouveau**, 22 tests du hook lui-même, avec doublures de WordPress et de Vik |
| `README.md` | Section B5, section Tests, recette point 14 |

**141 tests purs et 22 tests du hook, tous au vert.** Contre-épreuve faite : avec la ligne `setReply()` neutralisée, trois tests de `test-mail-source.php` tombent. Ils vérifient donc bien le changement, et ne passent pas par hasard. **Rien n'est déployé**, et aucun fichier de Vik n'est modifié.

`test-mail-source.php` est le premier test de ce plugin à exécuter un vrai point d'accroche hors WordPress. Ses doublures reproduisent `VBOMailWrapper` 1.8.14 sur les seules méthodes employées : effacement du nom égal à l'adresse, repli de `getSenderMail()`, remplacement par `setReply()`. Le registre est le vrai.

### 10.3 Les règles, cas par cas

| Situation | From | Reply-To | Journal |
|---|---|---|---|
| Marque résolue, expéditeur posé | marque | **marque** (`reply_to` du registre) | rien |
| `mixed_brands`, `unknown_room`, `no_rooms` | adresse de Vik, nom neutre | **adresse de Vik, inchangée** | `mail_brand_undetermined`, erreur, avec `chemin` et `reply_to_kept` |
| Expéditeur refusé par le gardien de `setSender()` | Vik | **Vik, inchangée** | `mail_sender_name_unusable` |
| Réservation périmée, OTA, déjà tranché, rien de déposé | inchangé | inchangé | rien, comme avant |
| Wrapper sans `setReply()` ou `getReply()` | inchangé | inchangé | `mail_wrapper_unexpected` |

Trois choix, chacun pour une raison.

- **Le Reply-To suit le From.** Si l'expéditeur n'est pas posé, l'adresse de réponse ne part pas seule vers la marque : un message sous l'expéditeur global avec la réponse chez Sexcape Room recréerait l'incohérence que la décision supprime. **La phase 3 ne fait pas ce test** : `lme_brands_apply_mail_identity()` pose le Reply-To même si l'expéditeur est refusé. Le cas n'arrive pas avec un registre valide, qui refuse un nom égal à l'adresse dès son chargement. Écart signalé, non corrigé : hors du périmètre de la décision.
- **`reply_to` du registre, pas `sender_email`.** « Comme pour la phase 3 » : c'est le champ qu'elle lit. Les deux valent la même adresse pour chaque marque, et un test sur le registre réel l'exige désormais. Il tombera exprès le jour où une marque répondrait ailleurs qu'elle n'écrit, parce que ce serait revenir sur la décision.
- **Le wrapper incomplet bloque tout le traitement**, expéditeur compris, au lieu de marquer le message à moitié. `setReply()` existe depuis la création de la classe. Son absence voudrait dire un Vik profondément modifié, et c'est une anomalie à journaliser, pas à contourner.

L'« absence de marque » de la décision, ce sont les trois issues non `ok` de la résolution, et elles journalisent. **L'absence de réservation n'en fait pas partie.** Un magasin vide, ou une réservation dont le client n'est pas destinataire, est le cas ordinaire des notifications à l'administrateur : il reste silencieux, comme avant (§3).

### 10.4 Le périmètre, relu avec cette décision

Le changement touche tous les messages que B5 retient (§5), pas seulement le rappel. Relevé des quatrièmes arguments de `sendMail()` :

| Émetteur | Reply-To avant | Après |
|---|---|---|
| Rappel avant séjour, tâche 7 (`email_reminder.php:660`) | `senderemail` de Vik | `reservations@` de la marque |
| Rappel de pré-enregistrement, tâche 1 non publiée (`precheckin_reminder.php:508`) | `senderemail` | idem |
| Messagerie en lot (`widgets/bulk_messaging.php:1112`) | `senderemail` | idem |
| Envoi de facture au client (`lib.vikbooking.php:11313`) | `senderemail` | idem |
| **Message envoyé à la main** depuis l'écran d'une réservation (`admin/controller.php:10052`) | l'adresse saisie dans le champ `emailfrom` du formulaire (`:9990`) | idem |

**Le dernier cas demande l'attention de Thomas.** Aujourd'hui, un message écrit à la main depuis une réservation prend comme adresse de réponse celle que Thomas met dans le champ expéditeur. B5 remplaçait déjà l'expéditeur. Il remplace désormais aussi la réponse, qui arrivera sur le groupe `reservations@` de la marque. C'est cohérent avec la décision, mais la réponse n'arrivera plus là où Thomas l'attendait.

**Non établi** : qui lit les groupes `reservations@linstantcle.ch` et `reservations@sexcaperoom.ch`, et si leurs membres reçoivent bien les messages. C2 dit « par groupe Google », pas qui en est membre. **Préalable au déploiement** : une réponse d'essai à chacune des deux adresses, reçue par une personne.

**Délivrabilité : aucun effet.** DMARC aligne le domaine du `From` et celui de la signature DKIM, jamais le Reply-To. La réserve du relais Gmail notée dans la revue de B5 porte sur le From, et elle est inchangée.

### 10.5 Recette ajoutée

Dans `README.md`, point 14, les assertions 1, 3, 4, 5 et 7 portent maintenant sur `Reply-To:` en plus de `From:`. Sur la préproduction, le garde-fou de messagerie détourne les messages : le Reply-To d'origine se lit dans sa transcription.

| # | Assertion ajoutée |
|---|---|
| 1 bis | Rappel de chambre Sexcape Room : `Reply-To: reservations@sexcaperoom.ch` |
| 3 bis | Rappel de chambre L'Instant Clé : `Reply-To: reservations@linstantcle.ch` |
| 4 bis | Réservation à marques mêlées : `Reply-To` inchangé (`info@maisonnette-enchantee.ch`), `reply_to_kept` au journal |
| 5 bis | Même exécution, deux marques : deux `Reply-To` différents |
| 7 bis | Réservation OTA : `Reply-To` inchangé |

### 10.6 Pour le plan de marche

Je n'écris pas dans `plan-de-marche.md`. Matière à y reporter :

- **La revue de B5, point 3** : tranché le 26 septembre, écrit et testé, non déployé.
- **Le verrou « attend C2 »** de ce constat (§7) est périmé, comme la revue l'a relevé. Le remplace le préalable du §10.4 : une réponse d'essai reçue sur chacun des deux groupes `reservations@`.
- **À signaler à Thomas** : les réponses à ses messages écrits à la main depuis une réservation iront au groupe `reservations@` de la marque.

---

## Verdict de chantier

**La source des rappels avant séjour porte la marque de leur réservation.** Expéditeur, nom d'affichage et, depuis le 26 septembre, adresse de réponse — rien d'autre. La réservation se retrouve par le magasin natif de Vik et se vérifie par le destinataire, jamais par une devinette. Marque indéterminée : identité neutre, alerte, jamais la mauvaise marque. Aucun message doublé, aucun fichier de Vik modifié, 110 tests unitaires au vert à la livraison, 141 tests purs et 22 tests du hook au 26 septembre. Le contenu reste au chantier F, et rien n'est déployé.
