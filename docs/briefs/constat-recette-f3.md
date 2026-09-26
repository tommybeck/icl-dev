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

## 5. Chapitre 4 : non rejoué

Rien n'a été déclenché. La tâche 7 ne retient que les réservations
`confirmed` (`admin/cronjobs/email_reminder.php`, clause `o.status`), et
1434, 1823 et 1838 sont déjà marquées comme notifiées. Il faut donc trois
nouvelles réservations confirmées. Thomas a demandé que Code les crée dans
l'administration de Vik sur `staging13`. **La politique de permissions de
cette session l'a refusé**, dès la lecture du code de création de
réservation de l'administration. Code n'a pas cherché de contournement.

### Pour reprendre

1. **Créer trois réservations confirmées sur `staging13`, dans
   l'administration de Vik** (Cowork ou Thomas). Nom
   « RECETTE F3 NE PAS TRAITER », adresse
   `recette+f3-<chambre>@recette-automatisee.icl-dev.invalid`.
   Disponibilités relevées à 09:5x UTC :

   | Chambre | Arrivée | Départ | Pourquoi ces dates |
   |---|---|---|---|
   | 4, Le Boudoir du Désir | 27.09 | 28.09 | libre entre 1434 (départ le 27.09 à 11:00) et 1824 (arrivée le 29.09) |
   | 10, À Huis Clos (ou 7) | 27.09 | 28.09 | 1823 arrive le 28.09 à 16:00 |
   | 8 ou 9 | 27.09 | 28.09 | libres |

   Une arrivée le 27.09 tombe dans la fenêtre d'un déclenchement le 26.09
   (du 26 au 28) comme le 27.09 (du 27 au 29).
2. **Code revérifie** les préalables de `constat-verification-7.md` §1 :
   `staging`, Vik Channel Manager absent, `lme-mail-guard` en place avec la
   fourre-tout résolue, « Do not send » à `false`, `test = OFF`. Il liste
   la fenêtre, qui ne doit contenir que les trois nouvelles réservations,
   puis lance **une fois** `wp cron event run vikbooking_cron_email_reminder_7`.
3. **Relire dans la fourre-tout** qu'il n'y a aucun blanc dans l'heure,
   l'adresse, le nom de la chambre, la place et le plan. Le code de porte
   des suites reste vide tant que Thomas ne l'a pas saisi.
4. Annuler ensuite les trois réservations, comme les autres essais.

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

---

## Journal des versions

- 26 septembre 2026 : première version. Recette appliquée sur `staging13`,
  rollback éprouvé, vérification par requête. Chapitre 4 en attente de
  réservations d'essai.
