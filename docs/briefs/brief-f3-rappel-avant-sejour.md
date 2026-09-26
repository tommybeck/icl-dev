# F3 : recette Vik du rappel avant séjour, chambres 4, 7, 8, 9 et 10

**Version 1.1, 26 septembre 2026.** Rédaction Cowork. À déplacer dans `icl-dev/docs/briefs/`, pas à recopier. Remplace la version 1.0 du même jour, dont le chapitre 2 était faux (voir « Correction » en fin de document).

**Décision de Thomas du 26 septembre 2026.** Les écritures dans Vik passent par une **recette versionnée**, exécutée par script, pour trois tables : `sir_vikbooking_condtexts` (textes conditionnels), `sir_vikbooking_cronjobs` (gabarit des tâches planifiées) et `sir_vikbooking_adultsdiff` (suppléments par adulte). La décision du 12 septembre, « aucune écriture en base », est levée **pour ce périmètre seulement**. Tarifs, saisons et restrictions restent dans les écrans de Vik, parce que leur enregistrement pousse vers Airbnb et Booking.com. Motif établi par lecture du contrôleur de Vik 1.8.15 (`admin/controller.php:15116-15222`) : l'écran natif d'un texte conditionnel n'écrit que la ligne, sans autre effet. Le script fait donc la même chose, de façon prévisible et rejouable.

Toutes les valeurs « avant » ci-dessous ont été lues en production et en préproduction le 26 septembre, en lecture seule, **par `TO_BASE64()` et `SHA2()`** (voir « Correction »). Les codes de porte n'ont pas été lus.

---

## 1. Comment le rappel choisit ses blocs

Le gabarit de la tâche 7 (« Check-in info ») juxtapose une variante par bâtiment, par exemple `{condition: checkin_time_maisonnette}{condition: checkin_time_cinema}`. Chaque texte conditionnel porte une règle `rooms.php` ; seule la variante dont la règle contient la chambre de la réservation s'affiche, les autres s'effacent (`admin/helpers/conditional_rules.php:375` et suivantes). **Une chambre absente de toutes les règles reçoit un vide** : c'est le cas des chambres 7, 8, 9 et 10 aujourd'hui, et le nom de la chambre 4 sort « L'Aparté ».

## 2. Ce que fait la recette

Chaque opération identifie sa cible par le **jeton** (`token`), pas par l'identifiant, compare l'état trouvé à l'état « avant », et :

- s'il vaut l'état « après » : ne fait rien, et le dit ;
- s'il vaut l'état « avant » : écrit l'état « après », et met `lastupd` à l'heure UTC comme le fait Vik ;
- sinon : **s'arrête sans rien écrire**, en nommant l'écart.

Format des règles, identique à ce que Vik écrit : `[{"id":"rooms.php","params":{"rooms":["1","5","7","10"]}}]`, identifiants en chaînes, ordre croissant.

### 2.1 Règles modifiées

| Jeton | Avant | Après | Pourquoi |
|---|---|---|---|
| `{condition: front_door_pin_cinema}` | 1, 5 | 1, 5, 7, 10 | 7 et 10 sont dans la villa de L'Entracte |
| `{condition: checkin_time_cinema}` (16:00) | 1, 5 | 1, 5, 7, 10 | idem, heures confirmées par Thomas |
| `{condition: checkout_time_cinema}` (12:00) | 1, 5 | 1, 5, 7, 10 | idem |
| `{condition: address_cinema}` | 1, 5 | 1, 5, 7, 8, 9, 10 | suites : même adresse que L'Entracte |
| `{condition: parking_space_cinema}` | 1, 5 | 1, 5, 7, 8, 9, 10 | suites : même parking |
| `{condition: access_map_cinema}` | 1, 5 | 1, 5, 7, 8, 9, 10 | suites : même plan |
| `{condition: room_name_cinema}` (« L'Entracte ») | 1, 5 | 1, 5, 7 | 7 est L'Entracte all inclusive |
| `{condition: at_room_name_cinema}` (« à l'Entracte ») | 1, 5 | 1, 5, 7 | idem |
| `{condition: room_name_maisonnette}` (« L'Aparté ») | 2, 4, 6 | 2, 6 | le Boudoir recevait « L'Aparté » |
| `{condition: at_room_name_lindcent}` | aucune | 9 | F2 |
| `{condition: at_room_name_la_parenthse}` | aucune | 8 | F2 |
| `{condition: room_name_lindcent}` | aucune | 9 | F2 |
| `{condition: room_name_la_parenthse}` | aucune | 8 | F2 |

### 2.2 Textes créés

Si le jeton existe déjà, la recette vérifie qu'il porte exactement ces valeurs, sinon elle s'arrête. `debug = 0`.

| `name` | `token` | Règle | `msg` |
|---|---|---|---|
| Room name Boudoir | `{condition: room_name_boudoir}` | 4 | `Le Boudoir du Désir` |
| Room name Huis Clos | `{condition: room_name_huis_clos}` | 10 | `À Huis Clos` |
| At room name Huis Clos | `{condition: at_room_name_huis_clos}` | 10 | `dans la villa À Huis Clos` |
| Check-in time Suites | `{condition: checkin_time_suites}` | 8, 9 | `15:00` |
| Checkout time Suites | `{condition: checkout_time_suites}` | 8, 9 | `12:00` |
| Front door PIN Suites | `{condition: front_door_pin_suites}` | 8, 9 | vide ; **Thomas saisit le code dans Vik**, il ne passe jamais par le script ni par le dépôt |

La recette ne réécrit jamais le `msg` de « Front door PIN Suites » une fois créé.

### 2.3 Gabarit de la tâche 7

Cible : `sir_vikbooking_cronjobs`, `class_file = 'email_reminder.php'` et `cron_name = 'Check-in info'`, clé `tpl_text` de `params`. Empreinte SHA-256 du `tpl_text` actuel, **identique en production et en préproduction** : `22b98ba381185bf34cf7d958fff9035e6dd2917e3b53ace7eaf3eb39d02563f3`. Après : `44b2f30a00f863212e42bde3b156f5ae42857222812a6c4546f23b13d069909d`.

Six remplacements exacts, chacun présent **une seule fois** dans le texte avant ; sinon arrêt. Rien d'autre ne change, pas un mot de la prose.

| Chaîne avant | Chaîne après |
|---|---|
| `{condition: at_room_name_cinema}` | `{condition: at_room_name_cinema}{condition: at_room_name_huis_clos}{condition: at_room_name_lindcent}{condition: at_room_name_la_parenthse}` |
| `{condition: checkin_time_cinema}` | `{condition: checkin_time_cinema}{condition: checkin_time_suites}` |
| `{condition: checkout_time_cinema}` | `{condition: checkout_time_cinema}{condition: checkout_time_suites}` |
| `{condition: room_name_cinema}` | `{condition: room_name_cinema}{condition: room_name_boudoir}{condition: room_name_huis_clos}{condition: room_name_lindcent}{condition: room_name_la_parenthse}` |
| `{condition:access_map_cinema}` | `{condition: access_map_cinema}` (F1 : sans l'espace, Vik ne trouvait pas le texte et effaçait le plan des chambres 1 et 5) |
| `{condition: front_door_pin_cinema}` | `{condition: front_door_pin_cinema}{condition: front_door_pin_suites}` |

Seule `tpl_text` change dans `params`. `params` est stocké par `json_encode` avec échappement `é` et `\/` : le réencodage doit reproduire le format que produit l'écran de Vik, à établir dans son code d'enregistrement des tâches plutôt qu'à supposer. Les autres clés (`test`, `test_email`, `subject`…) ne sont ni lues en clair ni modifiées.

Texte après, pour relecture :

```html
<p style="text-align: center;"><img src="https://linstantcle.ch/wp-content/plugins/vikbooking/admin/resources/linstant-cle-noir-logo-transp-new.png" alt="L'Instant Clé" width="180"></p><h1 style="text-align: center;"><span style="font-family: verdana;">L'Instant Clé</span></h1><p><br></p><p>Bonjour <b>{customer_name}</b>,</p><p><br></p><p>{{LINE_START}}Votre séjour commence dans deux jours et nous vous contactons pour nous assurer que vous puissiez sereinement faire votre nid <b>{condition: at_room_name_maisonnette}{condition: at_room_name_cinema}{condition: at_room_name_huis_clos}{condition: at_room_name_lindcent}{condition: at_room_name_la_parenthse}</b><b style="background-color: rgb(24, 27, 34);">{condition: at_room_name_boudoir}</b> :-){{LINE_END}}</p><p><br></p><p><br></p><p><br></p><p><br></p><h2>Arrivée et départ</h2><p>{{LINE_START}}Nous aurons terminé l'entretien et la préparation pour votre séjour pour que vous puissiez profiter des lieux dès <b>{condition: checkin_time_maisonnette}{condition: checkin_time_cinema}{condition: checkin_time_suites}</b> le <b>{checkin_date}</b>.{{LINE_END}}</p><p>{{LINE_START}}Comme il nous tient à coeur que chaque séjour soit parfait, le temps entre deux visites nous est compté et vous nous aideriez beaucoup en respectant l'heure de départ de <b>{condition: checkout_time_maisonnette}{condition: checkout_time_cinema}{condition: checkout_time_suites}</b> le <b>{checkout_date}</b>.{{LINE_END}}</p><p><br></p><p><br></p><p><br></p><p><br></p><h2>Accès</h2><p>{{LINE_START}}L'adresse est <b>{condition: address_maisonnette}{condition: address_cinema}</b>.{{LINE_END}}</p><p>En arrivant par le haut du village, prenez la première à droite. Si vous venez du bas, prenez la dernière à gauche avant la sortie du village.</p><p>{{LINE_START}}Une fois dans le Chemin du Clos-du-Vernay, roulez doucement à travers le quartier puis longez le verger sur votre droite. <b>{condition: room_name_maisonnette}{condition: room_name_cinema}{condition: room_name_boudoir}{condition: room_name_huis_clos}{condition: room_name_lindcent}{condition: room_name_la_parenthse}</b> est juste sur la droite, et votre place de parc est <b>{condition: parking_space_maisonnette}{condition: parking_space_cinema}</b>.{{LINE_END}}</p><p><br></p><p><br></p><p><br></p><p><br></p><h2>Parking &amp; porte d'entrée</h2><p style="text-align: center;">{{LINE_START}}<b>{condition: access_map_maisonnette}{condition: access_map_cinema}</b>{{LINE_END}}</p><p>Vous pourrez ouvrir la porte d'entrée avec le code <b>{condition: front_door_pin_maisonnette}{condition: front_door_pin_cinema}{condition: front_door_pin_suites}</b> que nous avons programmé pour vous, et si nous vous être utile d'une quelconque manière, faites-nous signe par téléphone, Signal, Telegram ou WhatsApp au +41 78 231 05 05 (PAS de SMS svp).</p><p>&nbsp;</p><p>Nous vous souhaitons un magnifique de début de séjour,</p><p>Aurore &amp; Thomas</p>
```

### 2.4 Suppléments par adulte, À Huis Clos (chambre 10)

`sir_vikbooking_adultsdiff`, `idroom = 10`, `chdisc = 1`, `valpcent = 1`, `pernight = 1` inchangés :

| `adults` | `value` avant | `value` après |
|---|---|---|
| 3 | 30.00 | 45.00 |
| 4 | 60.00 | 90.00 |
| 5 | 90.00 | 135.00 |

Capacité inchangée, 5 adultes. La description reste « pour deux ». L'Entracte (chambre 1) reste à 30, 60 et 90.

## 3. Comment la recette s'exécute

Script écrit par Code, dans le dépôt, sur le modèle de `deployer-moteur.sh` : **simulation par défaut** ; refus si `wp_get_environment_type()` ne correspond pas à la cible annoncée ; **sauvegarde** des lignes touchées avant écriture et `--rollback` qui les remet ; idempotent, la seconde exécution ne signale rien ; sortie non nulle au premier écart. Code le lance sur la préproduction. **Thomas seul le lance en production.**

## 4. Vérifier sans écrire à un client

Sur la préproduction, après la recette : une réservation d'essai par chambre concernée (4, 7 ou 10, 8 ou 9) arrivant dans les deux jours, puis déclenchement de la tâche 7 par Code. Les messages arrivent dans la fourre-tout : aucun blanc dans l'heure, l'adresse, le nom de la chambre, la place, le plan. Le code de porte des suites reste vide tant que Thomas ne l'a pas saisi. **Ne jamais utiliser le mode test de la tâche en production** : il détourne vers l'adresse de test tous les rappels du jour, vrais clients compris.

## 5. Consigner

Chaque exécution en production se consigne dans `journal-vik.md` : date, recette, empreintes avant et après, et la sortie du script.

---

## Correction de la version 1.0

La version 1.0 affirmait que le gabarit contenait des jetons mal formés (`{condition : …}`, `adresse_cinéma`) et que le rappel de production envoyait des jetons bruts. **C'était faux.** Les réponses d'EMCP sur linstantcle.ch arrivent réécrites par une traduction automatique : espaces typographiques, noms francisés, prose paraphrasée. La valeur en base, relue par `TO_BASE64()`, porte les bons jetons ; seul `{condition:access_map_cinema}` (F1) est réellement fautif. Le gabarit « corrigé » de la 1.0 aurait remplacé la prose de Thomas par une paraphrase. Règle qui en sort : **toute valeur texte lue par EMCP sur ces sites se relit par `TO_BASE64()` ou se compare par `SHA2()` avant de servir à conclure ou à réécrire.** Le greffon maison « Email Content Formatter » (`vik-booking-email-cleaner`, auteur Thomas Becker) a aussi été lu : il traite `{{LINE_START}}` et `{{TIME_RULES}}`, pas les jetons `{condition: …}`.

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.1 | 2026-09-26 | Recette scriptée sur décision de Thomas : périmètre d'écriture, règles modifiées, textes créés, six remplacements exacts du gabarit, suppléments d'À Huis Clos. Suites rattachées à l'adresse, au parking et au plan de L'Entracte, code de porte propre. Chapitre 2 de la 1.0 retiré, faux. |
| 1.0 | 2026-09-26 | Création. |
