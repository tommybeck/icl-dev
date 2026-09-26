# Constat — recette F3 du rappel avant séjour, appliquée sur `staging13`

26 septembre 2026. Exécute `brief-f3-rappel-avant-sejour.md`, version 1.1.
Destiné à Thomas et à Cowork.

**Méthode.** Toutes les lectures et écritures sont passées par SSH
(`sg-linstantcle`) et `wp eval-file -`, dans
`~/www/staging13.linstantcle.ch/public_html`. EMCP n'a pas été utilisé. La
sortie de WP-CLI est traduite à la volée sur ce site
(`constat-verification-7.md` §1). Chaque comparaison a donc été faite côté
serveur, sur les valeurs brutes, et chaque état est rapporté ici par son
SHA-256 ou en hexadécimal. Le `msg` des textes existants n'a pas été lu, et
pas davantage celui des codes de porte. **Rien n'a été lancé en
production.**

---

## 1. Deux points établis à la source avant d'écrire

### 1.1 Encodage de `params` par l'écran des tâches de Vik

L'écran d'enregistrement (`admin/controllers/cronjob.php:94-130`) passe
`vikcronparams` au modèle `VBOModelCronjob`. Son `preflight()`
(`admin/helpers/src/model/cronjob.php:59-89`) ne fait qu'une chose : il
retire les balises de surlignage `vbo-editor-hl-specialtag`. Il appelle
ensuite `VBOMvcModel::prepareSaveData()`
(`admin/helpers/src/mvc/model.php:379`), qui encode toute valeur non
scalaire par **`json_encode()` sans option**. On obtient donc `é` écrit
`é`, `/` écrit `\/`, et un texte ASCII pur. Un passage de la tâche
réenregistre `params` par le même chemin (`dispatch()`, même fichier,
ligne 186).

Relevé sur la tâche 7 : `json_encode(json_decode(params, true))` redonne
`params` à l'octet près. Les huit clés sont toutes des chaînes, dans cet
ordre : `checktype`, `remindbefored`, `less_days_advance`, `ota_res`,
`test`, `test_email`, `subject`, `tpl_text`. Échappements présents :
`è` ×6, `é` ×19, `à` ×4, `ê` ×1. Le moteur exige cet
aller-retour exact avant de réencoder quoi que ce soit.

### 1.2 `translateContents` sur `#__vikbooking_condtexts`

`admin/fields/translations.xml:63-66` déclare une seule colonne
traduisible, `msg` (`name` est en `skip`). `getSpecialTags()` et
`getBySpecialTag()` (`admin/helpers/conditional_rules.php:285-316`)
passent chaque ligne à `translateContents()`
(`site/helpers/translator.php:536`). Celui-ci charge
`#__vikbooking_translations` par `table`, `lang` et
`reference_id = id du texte`. `translateArrayValues()` (ligne 760) ne
remplace `msg` que si la traduction est **non vide**. Rien ne se passe pour
la langue par défaut.

Le risque réel est une traduction orpheline, laissée par un texte
supprimé : elle pourrait tomber sur l'identifiant d'un texte créé.

Relevé sur `staging13` : 162 lignes pour `condtexts`, en fr-FR, de-CH et
de-DE, qui visent les ids 1 à 68. **Aucune n'est orpheline.** Aucune ne
vise un id ≥ 69. Le moteur refuse de créer tant qu'une traduction non vide
vise un id inexistant, et vérifie après création qu'aucune ne vise les
nouveaux.

---

## 2. Un écart avec le brief

Le brief (§2.3) cible `class_file = 'email_reminder.php'`, mais **la
valeur en base est `email_reminder`, sans extension.** Relevé en
hexadécimal, les cinq tâches sont concernées. La recette cible donc
`class_file = 'email_reminder'` et `cron_name = 'Check-in info'`, ce qui
donne une seule ligne, l'id 7. Toutes les autres valeurs « avant » du brief
ont été retrouvées telles quelles sur `staging13`.

---

## 3. La recette

| Fichier | Rôle | SHA-256 |
|---|---|---|
| `appliquer-recette-vik.sh` | options, garde de production, transport, contrôle et lecture du rapport | `e13c3df1…2d27` |
| `recettes-vik/moteur-recette.php` | toute comparaison et toute écriture, côté serveur | `35a5621b…8d73` |
| `recettes-vik/f3-rappel-avant-sejour.json` | les données du brief, rien d'autre | `f6a5b582…5b77` |

- **Simulation par défaut.** `--appliquer` est nécessaire pour écrire. En
  production, il faut en plus `--confirmer-production`, et cette option
  est aussi exigée pour `--rollback`.
- **Refus si l'environnement ne correspond pas.** Le moteur compare lui-même
  `wp_get_environment_type()` à `--env` et l'hôte de l'option `home` à
  `--hote`. Il vérifie aussi que les trois tables sont en InnoDB.
- **Transport.** Le moteur et la recette, en base64, partent par l'entrée
  standard de `wp eval-file - --skip-plugins --skip-themes`. Le rapport
  revient en une ligne `RAPPORT <hex du JSON> <sha256>`. Le script local le
  décode, contrôle l'empreinte, puis vérifie que le serveur a reçu la
  recette à l'octet près.
- **Tout ou rien.** Toutes les opérations sont d'abord évaluées. S'il y a
  un écart, le script sort en 2 sans rien écrire. Sinon, il sauvegarde les
  lignes, puis écrit dans une transaction. Chaque `UPDATE` porte l'état
  « avant » dans son `WHERE` : il doit toucher exactement une ligne, sinon
  `ROLLBACK`. Toute la recette est ensuite relue.
- **Sauvegarde.** Elle est écrite dans
  `~/.icl-dev-recette-backups/<hôte>/<recette>/<horodatage UTC>/lignes.json`,
  en mode 600, sur le serveur. Pour une règle, elle garde `rules` et
  `lastupd`. Pour le gabarit, `params`. Pour un supplément, `value`. Les
  textes créés sont retrouvés par leur jeton. Aucun `msg` existant n'est
  sauvegardé.
- **`--rollback`.** Il ne remet une ligne que si elle vaut encore exactement
  ce que la recette a écrit. Si Thomas a saisi entre-temps le code des
  suites, c'est un écart et rien n'est remis.
- **`msg_libre`.** « Front door PIN Suites » est créé vide. Son `msg`
  n'est ensuite jamais lu, jamais comparé et jamais réécrit.

---

## 4. Exécutions sur `staging13`

| Heure (UTC) | Commande | Résultat | Sortie |
|---|---|---|---|
| 09:4x | simulation | 23 à écrire, 0 écart | 0 |
| 09:4x | `--appliquer` avec une recette altérée (supplément « avant » à 31.00) | ÉCART sur idroom=10 adults=3, rien d'écrit | 2 |
| 09:4x | simulation avec `--env production` sur `staging13` | refus : environnement relevé `73746167696e67` (`staging`), attendu `70726f64756374696f6e` | 1 |
| 09:49:52 | `--appliquer` | 23 écrits, relus « après ». Sauvegarde SHA-256 `4210f2c5…cd50` | 0 |
| 09:50:50 | `--appliquer` | rien à écrire | 0 |
| 09:50:52 | `--rollback` | 23 remis, relus « avant » | 0 |
| 09:50:54 | `--rollback` | rien à remettre | 0 |
| 09:50:57 | simulation | 23 à écrire, 0 écart : la base est revenue exactement à l'état « avant » | 0 |
| 09:51:00 | `--appliquer` | 23 écrits. Sauvegarde **identique** à la première, SHA-256 `4210f2c5…cd50`, ce qui prouve que le retour arrière avait remis les `lastupd` d'origine | 0 |
| 09:51:01 et après | `--appliquer` | rien à écrire | 0 |

Le rollback a été éprouvé parce que Thomas sera seul à lancer la recette en
production. Un retour arrière jamais essayé n'en est pas un. Conséquence :
les textes créés portent les ids **80 à 85** et non 74 à 79. En production,
ils recevront d'autres ids, sans effet puisque tout passe par le jeton.

### Vérification par requête, hors du moteur

Lecture seule, `lastupd = 2026-09-26 09:51:00` pour chaque ligne écrite.
Règles décodées localement depuis l'hexadécimal :

| id | Jeton | `rules` |
|---|---|---|
| 3, 36, 38 | `front_door_pin_cinema`, `checkin_time_cinema`, `checkout_time_cinema` | 1, 5, 7, 10 |
| 60, 63, 66 | `address_cinema`, `parking_space_cinema`, `access_map_cinema` | 1, 5, 7, 8, 9, 10 |
| 65, 68 | `room_name_cinema`, `at_room_name_cinema` | 1, 5, 7 |
| 64 | `room_name_maisonnette` | 2, 6 |
| 70, 72 | `at_room_name_lindcent`, `room_name_lindcent` | 9 |
| 71, 73 | `at_room_name_la_parenthse`, `room_name_la_parenthse` | 8 |
| 80 | `room_name_boudoir`, msg SHA-256 `2d889524…` | 4 |
| 81 | `room_name_huis_clos`, `d368449c…` | 10 |
| 82 | `at_room_name_huis_clos`, `0080dfdf…` | 10 |
| 83 | `checkin_time_suites`, `5f66259e…` | 8, 9 |
| 84 | `checkout_time_suites`, `9616672e…` (même empreinte que `checkout_time_cinema`, soit `12:00`) | 8, 9 |
| 85 | `front_door_pin_suites`, non lu | 8, 9 |

- Tâche 7 : `SHA2(params)` vaut `7833cfc2…f465`, et
  `SHA2(JSON_UNQUOTE(JSON_EXTRACT(params,'$.tpl_text')))` vaut
  **`44b2f30a00f863212e42bde3b156f5ae42857222812a6c4546f23b13d069909d`**.
  C'est l'empreinte attendue, recalculée par MySQL et non par le moteur.
- `adultsdiff` : la chambre 10 est à 45.00, 90.00 et 135.00, avec
  `chdisc`, `valpcent` et `pernight` à 1. La chambre 1 est inchangée, à
  30.00, 60.00 et 90.00.
- Traductions visant un id ≥ 74 : 0. Textes conditionnels : 78, soit 72
  plus 6.

---

## 5. Chapitre 4 : rejoué le 26 septembre à 10:12 UTC

Une première tentative n'avait rien déclenché : la tâche 7 ne retient que
les réservations `confirmed`, 1434, 1823 et 1838 étaient déjà notifiées, et
la politique de permissions de la session avait refusé à Code la création de
réservations. Cowork a ensuite créé trois réservations d'essai confirmées sur
`staging13` : 1839, 1840 et 1841. Le chapitre 4 a été rejoué sur ces trois
réservations, en suivant l'ancien « Pour reprendre ».

**Méthode.** La méthode est celle du §1 de `constat-verification-7.md` : SSH,
`wp eval-file -`, valeurs de décision en hexadécimal. Code n'a rien écrit en
base. Il n'a rien déployé non plus. Aucun bloc rendu n'est recopié ici. Chaque
bloc a été extrait sur le serveur du `content_html` que WP Mail SMTP journalise
(`log_email_content = true`), puis comparé au `msg` des textes conditionnels :
par SHA-256 d'abord, puis après normalisation des entités et des espaces, et,
pour le plan, par le nom du fichier image. Une valeur non secrète est donnée
par son empreinte. Le code de porte, lui, est donné comme vide ou non, avec le
jeton qui l'a produit, **sans empreinte ni longueur** : un code court se
retrouverait à partir de son empreinte.

**Incident de lecture.** Une première lecture du champ `logs` de la tâche 7
a pris un extrait trop large. Le champ est rangé du plus récent au plus
ancien, et l'extrait a ramené tout l'historique dans la session de Code,
avec les noms des clients. Les adresses étaient masquées. Rien de cet
historique n'est recopié ici ni ailleurs.

### 5.1 Préalables, revérifiés à 10:11 UTC

| Préalable | Résultat |
|---|---|
| `wp_get_environment_type()`, en hexadécimal | `staging`. Option `home` : `https://staging13.linstantcle.ch` |
| Vik Channel Manager | dossier absent, 0 greffon listé |
| `lme-mail-guard` | les cinq fichiers hors tests ont le même md5 sur le serveur et dans le dépôt |
| `LME_MAIL_GUARD_CATCHALL_EMAIL` | définie, non vide, résolue. La valeur n'a pas été lue. |
| « Do not send » | `false` |
| Mailer, forçage du `From` et du nom | `gmail`, `false`, `false` |
| Tâche 7 | publiée, `checktype = checkin`, `remindbefored = 2`, `less_days_advance = 1`, `ota_res = 1`, `test = OFF`, `listings` absent. SHA-256 de `tpl_text` : `44b2f30a…909d`, l'empreinte « après » de la recette |
| Fuseau PHP, `DISABLE_WP_CRON` | `UTC`, `true` |

### 5.2 La fenêtre, listée avant le déclenchement

Elle est calculée comme dans `execute()` : du **26.09 00:00:00 UTC au
28.09 23:59:59 UTC**. `flag_char` comptait alors 271 identifiants.

| Réservation | Arrivée (UTC) | Départ | État | Chambre | Marque (`lme-brands`) | Destinataire | Dans la fenêtre | Déjà notifiée |
|---|---|---|---|---|---|---|---|---|
| 1817 | 25.09 15:00 | 26.09 11:00 | confirmed | 4 | sexcaperoom | …@gmail.com | non | non |
| 1490 | 25.09 15:02 | 27.09 11:00 | confirmed | 1 | linstantcle | …@gmail.com | non | non |
| 1434 | 26.09 15:00 | 27.09 11:00 | confirmed | 4 | sexcaperoom | …@gmail.com | oui | **oui** |
| 1812 | 27.09 15:00 | 28.09 11:00 | cancelled | 2 | linstantcle | Airbnb | non, annulée | non |
| 1838 | 27.09 15:00 | 28.09 11:00 | confirmed | 2 | linstantcle | recette | oui | **oui** |
| **1841** | 27.09 15:00 | 28.09 12:00 | confirmed | **8** | **linstantcle** | `recette+f3-8@…` | **oui** | **non** |
| **1840** | 27.09 16:00 | 28.09 12:00 | confirmed | **10** | **sexcaperoom** | `recette+f3-10@…` | **oui** | **non** |
| **1839** | 28.09 15:00 | 29.09 11:00 | confirmed | **4** | **sexcaperoom** | `recette+f3-4@…` | **oui** | **non** |
| 1823 | 28.09 16:00 | 29.09 12:00 | confirmed | 10 | sexcaperoom | …@gmail.com | oui | **oui** |
| 1824 | 29.09 15:00 | 30.09 11:00 | confirmed | 4 | sexcaperoom | …@gmail.com | non | non |

Dans la fenêtre, **1839, 1840 et 1841 sont les seules réservations non
encore notifiées**. La condition était remplie, la tâche a été déclenchée.

### 5.3 Le déclenchement

```
2026-09-26T10:12:34Z
$ wp cron event run vikbooking_cron_email_reminder_7
Executed the cron event 'vikbooking_cron_email_reminder_7' in 2.259s.
Success: Executed a total of 1 cron event.
EXIT=0
```

La tâche a été lancée une fois, sur `staging13` seulement. Journal de Vik :
trois lignes `eMail sent to recette+f3-<chambre>@… - Booking ID 1839 / 1840 /
1841 (RECETTE F3 NE PAS TRAITER)`, horodatées 10:12:38. Aucune autre
réservation n'a été touchée, et 1434, 1823 et 1838 n'ont pas reçu de
second message.

### 5.4 Les trois messages

Il y a deux relevés, et ils concordent : la transcription du garde-fou
(lignes 15 à 17) et le journal de WP Mail SMTP (lignes 10 à 12). Sur les
trois messages, l'état est 1, le mailer est `gmail` et `error_text` est vide.
Le destinataire final est unique, la fourre-tout (comparaison faite sur le
serveur), sans Cc ni Bcc. L'objet est « Infos de dernière minute pour votre
séjour ».

| Réservation | Ligne du journal | `From` composé | `Reply-To` | `X-Original-To` |
|---|---|---|---|---|
| 1839, chambre 4 | 10 | `Sexcape Room <reservations@sexcaperoom.ch>` | …@maisonnette-enchantee.ch | `recette+f3-4@…` |
| 1840, chambre 10 | 11 | `Sexcape Room <reservations@sexcaperoom.ch>` | …@maisonnette-enchantee.ch | `recette+f3-10@…` |
| 1841, chambre 8 | 12 | `L'Instant Clé <reservations@linstantcle.ch>`, encodé en Q | …@maisonnette-enchantee.ch | `recette+f3-8@…` |

Chaque `From` est celui de la marque que le registre donne à la chambre.

Aucun des trois corps ne contient plus de jeton `{condition: …}`, de
`{balise}` ni de `{{LINE_…}}`.

**Les six blocs, et le code de porte.** Pour chaque case, le jeton dont le
`msg` a produit le bloc, suivi de l'empreinte (16 premiers caractères du
SHA-256).

| Bloc | 1839, chambre 4, Boudoir | 1840, chambre 10, Huis Clos | 1841, chambre 8, La Parenthèse |
|---|---|---|---|
| Heure d'arrivée | `checkin_time_maisonnette`, `5f66259e…` = `15:00` | `checkin_time_cinema`, `5d2b616b…` = `16:00` | `checkin_time_suites`, `5f66259e…` = `15:00` |
| Heure de départ | `checkout_time_maisonnette`, `17ea4724…` = `11:00` | `checkout_time_cinema`, `9616672e…` = `12:00` | `checkout_time_suites`, `9616672e…` = `12:00` |
| Adresse | `address_maisonnette`, `eee143b0…` | `address_cinema`, `92ac10b9…` | `address_cinema`, `92ac10b9…` |
| Nom de la chambre | `room_name_boudoir`, `2d889524…` | `room_name_huis_clos`, `d368449c…` | `room_name_la_parenthse`, `6be601cb…` |
| Place | `parking_space_maisonnette` (texte identique, balises près), `84cc05b8…` | `parking_space_cinema`, `a644fc53…` | `parking_space_cinema`, `a644fc53…` |
| Plan | une image, `Carte-parking-entree-Maisonnette-Enchantee.jpg` | une image, `Carte-parking-entree-Cinema-Enchante.jpg` | une image, `Carte-parking-entree-Cinema-Enchante.jpg` |
| Code de porte | **plein**, `front_door_pin_maisonnette` | **plein**, `front_door_pin_cinema` | **vide**, `front_door_pin_suites` |
| Complément « faire votre nid » | `at_room_name_boudoir` (second `<b>`) | `at_room_name_huis_clos`, `0080dfdf…` | `at_room_name_la_parenthse`, `0eb87c48…` |

Les heures ont été identifiées en comparant l'empreinte du bloc à celle de
la chaîne, calculée localement. Elles concordent avec les heures
d'arrivée et de départ enregistrées dans chaque réservation. Dans le
`msg`, le plan est donné par un chemin relatif. Dans le message envoyé, il
pointe vers `staging13.linstantcle.ch`.

**Aucun blanc** dans l'heure d'arrivée, l'heure de départ, l'adresse, le
nom de la chambre, la place et le plan, pour aucune des trois chambres.
**Le code de porte des suites est vide**, comme prévu. Le Boudoir ne
reçoit plus « L'Aparté ». La chambre 10 reçoit l'heure, l'adresse, la
place, le plan et le code de L'Entracte. La chambre 8 reçoit l'adresse,
la place et le plan de L'Entracte, et ses propres heures.

### 5.5 Ce que le déclenchement a écrit, par Vik et WP Mail SMTP

- `sir_vikbooking_cronjobs`, id 7 : `flag_char` passe de 271 à 274
  identifiants, avec l'ajout de 1839, 1840 et 1841. `last_exec` vaut
  2026-09-26 10:12:38 UTC, et `logs` reçoit le bloc du §5.3.
- `sir_wpmailsmtp_emails_log`, lignes 10 à 12, adresses de recette seulement.
- `wp-content/lme-mail-guard-envelopes.log`, lignes 15 à 17.
- `wp-content/debug.log` : l'avertissement `host_override_used`, puis
  trois `production_brand_host_redirected` qui attribuent à tort les trois
  messages à `sexcaperoom`, 1841 comprise. C'est le même bruit que dans
  `constat-verification-7.md` §6. Il n'y a eu ni erreur ni fatale. Une
  dernière ligne `host_override_used`, à 10:13:32, vient de la lecture
  faite par Code après le passage, et non de la tâche.

### 5.6 Verdict

**Côté serveur, le chapitre 4 est établi.** Un seul passage de la tâche 7
a composé trois rappels, un par chambre visée par la recette. Chacun porte
l'expéditeur de sa marque et ses six blocs, tous pleins, chacun produit
par le texte conditionnel attendu. Le code de porte des suites est vide.

Un point reste hors de portée du serveur : **le message tel qu'il a été
reçu**. Le mailer passe par l'API Gmail, qui peut réécrire le `From`
(`constat-verification-7.md` §7). Le rendu visuel n'a pas été vu non plus.

---

## 6. Points ouverts

1. **Production : Thomas seul.**
   `./appliquer-recette-vik.sh --env production --hote linstantcle.ch`
   d'abord en simulation. Cela doit donner 23 à écrire et 0 écart, les
   valeurs « avant » du brief ayant été lues identiques en production.
   Ensuite `--appliquer --confirmer-production`, puis une consignation
   dans `journal-vik.md` (brief §5) : la sortie du script contient les
   empreintes avant et après.
2. **Code de porte des suites** : Thomas le saisit dans Vik, texte
   « Front door PIN Suites ». Après cette saisie, `--rollback` s'arrête
   sur ce texte, comme prévu.
3. **Langues** : les six textes créés n'ont aucune traduction. Un rappel en
   allemand affichera donc le texte français. C'est déjà le cas des textes
   existants, dont les traductions sont toutes vides.
4. **Sauvegardes sur le serveur** : deux dossiers sous
   `~/.icl-dev-recette-backups/staging13.linstantcle.ch/`. Ils contiennent
   `params` de la tâche 7, donc l'adresse `test_email`. Ils sont à purger
   avec la préproduction.
5. **Brief** : corriger `class_file` au §2.3 (Cowork).
6. **Réservations d'essai 1839, 1840 et 1841** : elles restent `confirmed`.
   Cowork les annule, comme 1838.
7. **Fourre-tout** (Thomas) : relire les trois messages reçus le 26.09 vers
   10:12 UTC. Vérifier le rendu, les deux plans, et que le `From` n'a pas
   été réécrit par Gmail.
8. **Chambre 8 sous L'Instant Clé** : le registre `lme-brands` rattache la
   chambre 8 à `linstantcle`, et 1841 est donc sortie en L'Instant Clé,
   avec le logo du gabarit. La recette F3 n'y touche pas. Si les suites
   doivent sortir sous une autre marque, c'est une décision de registre, à
   prendre par Thomas.
9. **`debug.log` de `staging13`** : 214 Mo. Il est à purger avec la
   préproduction.

---

## Journal des versions

- 26 septembre 2026 : première version. Recette appliquée sur `staging13`,
  rollback éprouvé, vérification par requête. Chapitre 4 en attente de
  réservations d'essai.
- 26 septembre 2026 : chapitre 4 rejoué sur 1839, 1840 et 1841, une
  exécution de la tâche 7 à 10:12:34 UTC. Les six blocs sont pleins pour
  les chambres 4, 10 et 8, et le code de porte des suites est vide. §5
  réécrit, points ouverts 6 à 9 ajoutés.
