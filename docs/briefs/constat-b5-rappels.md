# Constat, chantier B5 — la source des rappels avant séjour

17 septembre 2026. Ce que B5 pose, ce qu'il refuse de poser, et les deux réserves qu'il laisse ouvertes parce qu'aucune n'est un geste de code.

Suite directe du §4 de `constat-phase-3-emails.md`, qui avait établi ligne à ligne que les rappels n'empruntent pas le chemin d'envoi de la phase 3, et proposé le point d'accroche sans le poser. Thomas a tranché : on le pose.

Source lue : copie SFTP de Vik Booking **1.8.14** dans `.local/`, en lecture seule. Aucune écriture en base, aucune modification de configuration, **rien de déployé**.

Règle de ce document, la même que pour les précédents : chaque affirmation cite la ligne qui l'établit. Ce qui n'est pas établi est annoncé comme non établi.

---

## 1. Ce que B5 livre

`mu-plugins/lme-brands/includes/mail-brand.php` gagne un second point d'accroche, et `includes/core.php` deux fonctions pures de plus. Aucun fichier de Vik modifié.

| Hook WordPress | Émis par | Ce qu'on en fait |
|---|---|---|
| `vikbooking_before_send_mail` | `platform/org/wordpress/mailer.php:52` | expéditeur et nom d'affichage, **rien d'autre** |

110 tests unitaires passent (`php mu-plugins/lme-brands/tests/test-core.php`), dont 14 ajoutés par ce chantier. **Rien n'est déployé.**

Le hook est déclenché depuis `prepare()`, donc avant `$service->send($mail)` (`mailer.php:34-37`) : la réécriture y est encore possible. Il ne retourne rien — `trigger()` est un `do_action_ref_array` (`dispatcher.php:32`) —, donc il n'annule aucun envoi et ne peut pas en produire un second. Le nom du hook se déduit de `getHook()` (`dispatcher.php:73-88`) : `onBeforeSendMail` → `vikbooking_before_send_mail`.

---

## 2. Ce qui est posé, et ce qui ne l'est pas

**L'expéditeur et le nom d'affichage.** Ni objet, ni corps, ni adresse de réponse.

C'est la consigne, et elle a sa raison. Le corps d'un rappel ne vient pas d'un gabarit de fichier mais du champ `tpl_text` de la tâche planifiée, saisi dans l'administration de Vik. Il contient aujourd'hui, **hors de tout bloc conditionnel**, le logo et un `<h1>L'Instant Clé</h1>` (§4 du constat de phase 3). Y substituer des chaînes depuis le code reviendrait à réécrire à l'aveugle un texte que Thomas tient à la main, et à créer une seconde copie de la correspondance chambre → marque là où le mécanisme natif des textes conditionnels fait déjà le travail. C'est l'argument du §5 du constat de phase 3, et il vaut mot pour mot ici : **B5 corrige l'expéditeur, le chantier F corrige le contenu, et les deux sont nécessaires.**

**L'adresse de réponse n'est pas posée non plus.** La tâche de rappel passe `$admin_sendermail` en `$reply_address` (`email_reminder.php:660`), donc la réponse d'un client arrive aujourd'hui sur `info@maisonnette-enchantee.ch`, une boîte qui existe et qui est relevée. La poser par marque ferait basculer cette réponse vers `reservations@sexcaperoom.ch`, une boîte qui n'existe pas encore (§6 du constat de phase 3, chantier C2). Ne pas y toucher, c'est garder un chemin de réponse qui fonctionne au lieu d'en inventer un qui rebondit.

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
| `admin/controllers/mail.php:65` | message envoyé à la main depuis l'écran d'une réservation |
| `admin/helpers/widgets/bulk_messaging.php:1170` | messagerie en lot |
| `site/helpers/lib.vikbooking.php:5734` | message client de réservation (`parseEmailTemplate()`) |
| `site/helpers/lib.vikbooking.php:9821`, `:9904` | gabarits SMS, administrateur et client — pas des e-mails |
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

## Verdict de chantier

**La source des rappels avant séjour porte la marque de leur réservation.** Expéditeur et nom d'affichage, rien d'autre. La réservation se retrouve par le magasin natif de Vik et se vérifie par le destinataire, jamais par une devinette. Marque indéterminée : identité neutre, alerte, jamais la mauvaise marque. Aucun message doublé, aucun fichier de Vik modifié, 110 tests unitaires au vert. Le contenu reste au chantier F, et rien n'est déployé.
