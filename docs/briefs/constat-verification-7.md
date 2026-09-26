# Constat — vérification 7, rappel avant séjour, déclenché une fois sur `staging13`

26 septembre 2026. Suite de `constat-reprise-1836-1837.md` §4. Ce constat
exécute la décision de Thomas du 26 septembre : déclencher **une seule fois**
la tâche 7 de Vik Booking sur la préproduction, le même jour. À l'intention
de Thomas et de Cowork.

**Méthode.** Toutes les lectures sont passées par SSH (`sg-linstantcle`) et
WP-CLI, dans `~/www/staging13.linstantcle.ch/public_html`, et jamais par
EMCP. Les réglages ont été lus par leur clé, jamais par joker. Les valeurs
exposées à la traduction ont été relues en hexadécimal. Aucune clé ni
aucune valeur de `LME_MAIL_GUARD_CATCHALL_EMAIL` n'a été lue. Les adresses
et les noms des vrais clients ne sont pas recopiés, seul le domaine des
adresses apparaît. Je n'ai rien écrit en base et rien déployé. Les seules
écritures sont celles que Vik et WP Mail SMTP font d'eux-mêmes quand la
tâche tourne (§5).

---

## 1. Préalables, revérifiés avant le déclenchement

| Préalable | Vérifié comment | Résultat |
|---|---|---|
| `environment: staging` | `wp eval 'echo bin2hex(wp_get_environment_type());'` | `73746167696e67`, soit `staging` |
| Vik Channel Manager absent | `ls wp-content/plugins/vikchannelmanager`, `wp plugin list` | dossier absent, greffon non listé |
| `lme-mail-guard` 1.1.0 identique au dépôt | md5 des cinq fichiers hors tests, serveur contre dépôt | cinq empreintes identiques, `must-use`, version 1.1.0 |
| `LME_MAIL_GUARD_CATCHALL_EMAIL` présente | `defined()`, non vide après `trim`, `lme_mail_guard_resolved_catchall() !== null` | définie, non vide, résolue. La valeur n'a pas été lue. |
| « Do not send » inactif | `get_option('wp_mail_smtp')['general']['do_not_send']`, relu en hexadécimal | `false`. **Les messages partent réellement.** |
| Mailer | `…['mail']['mailer']` | `gmail` (API Gmail de WP Mail SMTP Pro 4.9.0) |
| Forçage de l'expéditeur par WP Mail SMTP | `…['mail']['from_email_force']`, `…['from_name_force']` | `false` et `false` : WP Mail SMTP garde le `From` que Vik compose |
| Autres chemins sortants | `wp plugin list` | MailPoet, WooCommerce, WooCommerce Payments et PayPal inactifs |
| Tâche 7 | `sir_vikbooking_cronjobs`, id 7, `params` lus par clé | publiée, `checktype = checkin`, `remindbefored = 2`, `less_days_advance = 1`, `ota_res = 1`, `test = OFF`, `listings` vide. Dernier passage : 20.09 07:51 UTC |

**Un piège confirmé.** La première lecture de l'environnement, faite sans
hexadécimal, a rendu `mise en scène`. La traduction à la volée touche donc
aussi la sortie de `wp eval` sur ce site, pas seulement EMCP. Toute valeur
lue par WP-CLI qui sert à décider doit passer par `bin2hex`, comme le fait
déjà `recetter-moteur.sh`.

---

## 2. La fenêtre, listée avant le déclenchement

**Correction du §4.3 de `constat-reprise-1836-1837.md`.** La fenêtre n'est
pas calculée dans le fuseau du site. `execute()` la bâtit avec `mktime()`
et `date()` (`admin/cronjobs/email_reminder.php:282-296`), donc dans le
fuseau par défaut de PHP. WordPress force ce fuseau à `UTC`, relevé à
`date_default_timezone_get()`. Un passage lancé aujourd'hui couvre donc les
arrivées du **26.09 00:00:00 UTC au 28.09 23:59:59 UTC**.

Pour chaque réservation, la marque est celle que donne le registre
`lme-brands` lu sur la cible (`lme_brands_resolve_room()`). Les réservations
voisines sont listées pour montrer où passent les limites de la fenêtre.

| Réservation | Arrivée (UTC) | État | Chambre | Marque | Origine | Domaine du destinataire | Dans la fenêtre | Déjà notifiée |
|---|---|---|---|---|---|---|---|---|
| 1817 | 25.09 15:00 | confirmed | 4 | sexcaperoom | site | gmail.com | non, la veille | non |
| 1490 | 25.09 15:02 | confirmed | 1 | linstantcle | site | gmail.com | non, la veille | non |
| **1434** | 26.09 15:00 | confirmed | 4 | **sexcaperoom** | site | gmail.com | **oui** | non |
| 1812 | 27.09 15:00 | cancelled | 2 | linstantcle | Airbnb | — | non, annulée | non |
| **1838** | 27.09 15:00 | confirmed | 2, L'Aparté | **linstantcle** | site | domaine de recette | **oui** | non |
| **1823** | 28.09 16:00 | confirmed | 10 | **sexcaperoom** | site | gmail.com | **oui** | non |
| 1824 | 29.09 15:00 | confirmed | 4 | sexcaperoom | site | gmail.com | non, le lendemain | non |
| 1648 | 29.09 15:01 | confirmed | 1 | linstantcle | Expedia | — | non, le lendemain | non |

**Fenêtre relevée : 1434, 1823 et 1838, exactement la fenêtre attendue.**
Elle contient les deux marques. J'ai donc déclenché la tâche.

Au registre `lme-brands`, `sexcaperoom` a pour expéditeur
`Sexcape Room <reservations@sexcaperoom.ch>` et `linstantcle`
`L'Instant Clé <reservations@linstantcle.ch>`, valeurs relues en hexadécimal.

À noter : 1823 porte le nom de client « TEST TEST », mais une adresse
gmail.com. Ce n'est pas une réservation de la recette automatisée. J'ignore
si c'est un vrai client.

---

## 3. Le déclenchement

```
2026-09-26T09:28:28Z
$ wp cron event run vikbooking_cron_email_reminder_7
Executed the cron event 'vikbooking_cron_email_reminder_7' in 2.349s.
Success: Executed a total of 1 cron event.
EXIT=0
```

Il a été lancé une seule fois, sur `staging13` seulement. Aucun autre
évènement WP-Cron n'a tourné : `DISABLE_WP_CRON` vaut toujours `true`, et
les crochets `_3`, `_4` et `precheckin_reminder_2` restent en retard, comme
avant.

---

## 4. Les trois messages, relevés à trois endroits

### 4.1 Journal de Vik (`logs` de la tâche 7)

```
2026-09-26T09:28:32+00:00
eMail sent to …@gmail.com - Booking ID 1434 (…)
eMail sent to …@gmail.com - Booking ID 1823 (…)
eMail sent to …@recette-automatisee.icl-dev.invalid - Booking ID 1838 (RECETTE VERIFICATION 7 NE PAS TRAITER)
```

### 4.2 Transcription du garde-fou (`wp-content/lme-mail-guard-envelopes.log`, lignes 12 à 14)

La ligne est écrite au filtre `wp_mail`. Le `From` y est donc déjà composé :
B5 le réécrit plus tôt, sur `vikbooking_before_send_mail`.

| Heure (UTC) | Destinataire d'origine | `From` composé | `Reply-To` | Objet |
|---|---|---|---|---|
| 09:28:30 | …@gmail.com | `Sexcape Room <reservations@sexcaperoom.ch>` | `info@maisonnette-enchantee.ch` | Infos de dernière minute pour votre séjour |
| 09:28:31 | …@gmail.com | `Sexcape Room <reservations@sexcaperoom.ch>` | `info@maisonnette-enchantee.ch` | Infos de dernière minute pour votre séjour |
| 09:28:31 | adresse de recette de 1838 | `L'Instant Clé <reservations@linstantcle.ch>` | `info@maisonnette-enchantee.ch` | Infos de dernière minute pour votre séjour |

Les deux messages Sexcape Room sont ceux de 1434 et de 1823, les deux seules
réservations de cette marque dans la fenêtre. Je ne peux pas dire lequel est
lequel sans recopier leur adresse, mais c'est sans conséquence : ils portent
le même `From`.

### 4.3 Journal d'envoi de WP Mail SMTP (`sir_wpmailsmtp_emails_log`, lignes 7 à 9)

| Ligne | État | Mailer | Destinataires finaux | Cc / Bcc | `From` | `X-Original-To` |
|---|---|---|---|---|---|---|
| 7 | 1, envoyé | gmail | 1, la fourre-tout | 0 / 0 | `Sexcape Room <reservations@sexcaperoom.ch>` | …@gmail.com |
| 8 | 1, envoyé | gmail | 1, la fourre-tout | 0 / 0 | `Sexcape Room <reservations@sexcaperoom.ch>` | …@gmail.com |
| 9 | 1, envoyé | gmail | 1, la fourre-tout | 0 / 0 | `L'Instant Clé <reservations@linstantcle.ch>`, encodé en Q | adresse de recette de 1838 |

« La fourre-tout » veut dire ceci : chaque destinataire a été comparé à la
constante sur le serveur, sans casse ni espaces, et la comparaison a rendu
vrai. La valeur n'a jamais été imprimée. `error_text` est vide sur les trois
lignes.

---

## 5. Ce que le déclenchement a écrit

Ces écritures ont été faites par Vik et par WP Mail SMTP, sur la base de la
préproduction seulement :

- `sir_vikbooking_cronjobs`, id 7. `flag_char` passe de 268 à 271
  identifiants, avec l'ajout de 1434, 1823 et 1838. `last_exec` vaut
  2026-09-26 09:28:32 UTC, et `logs` reçoit le bloc du §4.1. Un second
  passage aujourd'hui ne renverrait donc rien à ces trois réservations.
- `sir_wpmailsmtp_emails_log`, lignes 7 à 9. `people` et `headers`
  contiennent les deux vraies adresses en `X-Original-To`.
- `wp-content/lme-mail-guard-envelopes.log`, lignes 12 à 14, avec les deux
  vraies adresses. Ce sont des données personnelles, à purger avec la
  préproduction.
- `wp-content/debug.log`, quatre lignes, reproduites au §6.

Rien n'a atteint la production. Aucun vrai client n'a reçu de message.

---

## 6. Journal d'erreurs pendant le passage

```
[lme-brands] [WARNING] [host_override_used] Levier de préproduction actif : hôte de résolution de marque forcé à 'reservation.sexcaperoom.ch' par LME_BRANDS_HOST_OVERRIDE (hôte réel de la requête : (absent)).
[lme-mail-guard] [WARNING] [production_brand_host_redirected] … '…@gmail.com' … la marque 'sexcaperoom' …   (×2)
[lme-mail-guard] [WARNING] [production_brand_host_redirected] … '…@recette-automatisee.icl-dev.invalid' … la marque 'sexcaperoom' …
```

**Le levier `LME_BRANDS_HOST_OVERRIDE` est actif sur `staging13`**, réglé sur
`reservation.sexcaperoom.ch`. Il n'a pas faussé l'expéditeur : 1838 est
sortie en L'Instant Clé, ce qui montre que B5 choisit la marque par les
chambres de la réservation, et non par l'hôte. Mais l'avertissement du
garde-fou, qui raisonne sur l'hôte, attribue à tort les trois messages à
Sexcape Room, 1838 comprise. Ce n'est que du bruit dans le journal, et le
détournement n'en dépend pas. Reste à savoir si ce levier doit rester
posé : c'est à Thomas de le dire.

Il n'y a eu ni erreur ni fatale.

---

## 7. Verdict

**Côté serveur, la vérification 7 est établie.** Une seule exécution de la
tâche a composé trois rappels, un par réservation de la fenêtre. Ils portent
deux expéditeurs différents, chacun conforme à la marque de sa réservation
dans le registre : `reservations@sexcaperoom.ch` pour 1434 et 1823,
`reservations@linstantcle.ch` pour 1838. C'est le critère décisif de B5.
Les trois messages ont été détournés vers la fourre-tout, sans Cc ni Bcc.
Le mailer les a acceptés sans erreur.

**Un point n'est pas établi : le `From` reçu.** Le mailer est l'API Gmail.
Gmail remplace le `From` par l'adresse du compte connecté s'il ne connaît
pas `reservations@sexcaperoom.ch` et `reservations@linstantcle.ch` comme
alias d'envoi (« Send mail as »). Le serveur ne peut pas le montrer. Pour
fermer la vérification 7, il reste à **relire les en-têtes bruts des trois
messages dans la fourre-tout** (Show original) : `From`, `Reply-To`,
`X-Original-To`, et l'alignement SPF, DKIM et DMARC.

---

## 8. Points ouverts

1. **Thomas** relit les en-têtes bruts des trois messages dans la
   fourre-tout, reçus le 26.09 vers 09:28 UTC, objet « Infos de dernière
   minute pour votre séjour ». Il vérifie en particulier que le `From` n'a
   pas été réécrit par Gmail.
2. **`Reply-To` vaut `info@maisonnette-enchantee.ch` pour les deux
   marques.** C'est le `senderemail` de Vik. B5 ne touche pas au
   `Reply-To`, et ce comportement est voulu depuis `mail-brand.php:336`.
   Conséquence : un client Sexcape Room qui répond au rappel écrit à une
   adresse d'une troisième marque. C'est à trancher avant B10.
3. **Le corps** reste le gabarit de la tâche 7, au logo L'Instant Clé pour
   les deux marques (chantier F, `brief-f3-rappel-avant-sejour.md`). Il n'a
   pas été relu ici. Thomas peut le voir dans les messages reçus.
4. **Encodage de `X-Original-To` pour 1838**, relevé dans le journal de WP
   Mail SMTP : `=?us-ascii?Q?recette+…@recette-automatisee.icl-dev.?==?us-ascii?Q?invalid?=`.
   Il y a deux mots encodés sans espace entre eux, ce que la RFC 2047 ne
   permet pas. C'est sans effet sur le routage, et sans effet sur
   `recetter-moteur.sh`, qui lit la transcription du garde-fou et non cet
   en-tête. Reste à voir, dans le message reçu, comment un client de
   messagerie l'affiche.
5. **1823, « TEST TEST »** : réservation `confirmed` à la chambre 10, avec
   une adresse gmail.com. Est-ce un essai oublié en production, recopié
   dans la préproduction ?
6. **1838** reste `confirmed`. Cowork l'annule quand elle aura servi, comme
   les autres réservations d'essai (`constat-reprise-1836-1837.md` §7.1).
7. **`recetter-moteur.sh`** : la vérification 7 constate toujours sans
   déclencher, et doit continuer ainsi. Ce déclenchement unique ne la
   modifie pas.

---

## Journal des versions

- 26 septembre 2026 : première version, après le déclenchement unique de la
  tâche 7 sur `staging13`, à 09:28:28 UTC.
