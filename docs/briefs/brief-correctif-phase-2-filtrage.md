# Brief — correctif de la phase 2 : toute chambre active est une chambre comme les autres

Version 2, 14 septembre 2026. Rédaction Cowork, implémentation Code.

Porte sur le commit `6b6b273`, phase 2, revue du 14 septembre 2026. **La version 1 de ce brief demandait de coder une exception ; elle est remplacée.** La décision de Thomas du 14 septembre supprime le cas particulier plutôt que de le traiter.

---

## 1. Ce qui est juste et ne doit pas bouger

La phase 2 est conforme au prompt B2. Le hook natif couvre `search`, la garde couvre les deux branches de `saveorder()`, les trois vues sans hook sont traitées à la source en retirant le paramètre avant lecture de `$_REQUEST`, et rien n'est deviné hors du registre. **Rien de tout cela ne se rouvre.**

---

## 2. La décision, et le défaut qu'elle efface

**Décision de Thomas, 14 septembre 2026. Toute chambre active dans Vik est réservable et porte une marque. Une chambre désactivée dans Vik n'est servie nulle part.** Il n'y a plus de troisième catégorie.

Ce que cela répare, sans avoir à l'écrire en code : `lme_brands_strip_foreign_category()` ne conservait une chambre que si son statut valait `ok`, et la catégorie Vik 1 contient les chambres 1, 2, 7 **et la chambre de test 5**, au statut `excluded`. Un `roomslist` appelé avec `category_id=1` sur l'hôte L'Instant Clé perdait donc son paramètre alors que la catégorie est entièrement de cette marque. Une fois la chambre 5 devenue une chambre L'Instant Clé ordinaire, elle ne disqualifie plus rien sur cet hôte, et elle disqualifie correctement la catégorie sur l'hôte Sexcape Room. **Le défaut disparaît par le modèle, pas par une exception.**

Le second bénéfice est aussi important : quand toute chambre active porte une marque, le statut `unknown` redevient une vraie anomalie, la chambre créée dans Vik que personne n'a enregistrée. L'alerte retrouve du sens.

---

## 3. Vik porte déjà la notion, ne pas la dupliquer

`sir_vikbooking_rooms` a une colonne `avail`, `tinyint(1)`, défaut 1. Relevé le 14 septembre 2026 : les sept chambres vendues sont à 1, les chambres 5 et 6 à 0.

**`excluded_room_ids` duplique donc un fait que Vik détient déjà**, et une duplication tenue à la main finit par diverger. Règle absolue n°5. La clé disparaît du registre, et « désactivée » se lit dans `avail`.

---

## 4. Le registre après changement

Les chambres 5 et 6 entrent dans `rooms` avec la même forme que les autres.

| Chambre | Marque | Pourquoi |
|---|---|---|
| 5, clone Cinéma | `linstantcle` | Clone de L'Entracte |
| 6, clone Maisonnette | `sexcaperoom` | **Affectation délibérée, pas une filiation.** Par sa nature la 6 est un clone de la Maisonnette, donc L'Instant Clé. Elle est affectée à Sexcape Room pour que chaque marque dispose d'une chambre de test et que la recette exerce les deux hôtes. Écrire cette raison dans le fichier : sans elle, le prochain lecteur « corrigera » vers L'Instant Clé en croyant réparer une erreur. |

**`availability_group` des deux : `null`, vérifié.** `sir_vikbooking_calendars_xref` ne contient aucune ligne pour les chambres 5 et 6. Les seuls groupes réels y sont 1, 7, 10 pour la villa Entracte et 2, 4 pour la villa Aparté.

---

## 5. Où `avail` s'applique

**Dans la garde, sans condition.** Une chambre à `avail = 0` est refusée à la création de réservation, quelle que soit sa marque, et l'événement est journalisé. C'est la couche opposable : une requête POST fabriquée à la main ne doit pas pouvoir réserver une chambre désactivée.

**En présentation, vérifier avant d'écrire.** Vik masque probablement déjà les chambres à `avail = 0` de ses propres écrans. Établir si c'est le cas dans `.local/`, preuve à l'appui, et n'ajouter du code que si Vik ne le fait pas. Ne pas dupliquer un comportement natif.

**La recette E5, décision de Thomas :** la chambre de test est activée dans Vik le temps du test, puis désactivée. Conséquence à traiter et non à subir : pendant cette fenêtre la chambre est publiquement visible et réservable par un vrai visiteur. La désactivation doit donc survenir **y compris quand le test échoue** ; un chemin d'échec qui laisse la chambre active est exactement l'échec silencieux que la règle absolue n°6 interdit. La procédure d'E5 le dira quand E5 sera écrit, ce brief ne fait que poser la contrainte.

---

## 6. Surface de changement

Le statut `excluded` disparaît du greffon. Sites relevés dans le commit `6b6b273` :

- `config/brands.php` : commentaire d'en-tête sur les chambres de test, clé `excluded_room_ids` et son commentaire, plus deux entrées `rooms` à créer.
- `includes/core.php` : validation de `excluded_room_ids`, branche `excluded` de `lme_brands_resolve_room()`, et la documentation des statuts.
- `includes/registry.php` : valeur par défaut `excluded_room_ids`, et la branche de journalisation `excluded_room`.
- `includes/health-screen.php` : fusion des identifiants connus et exclus, message d'aide qui nomme `excluded_room_ids`, et la branche d'affichage `excluded`.
- `includes/booking-guard.php` : le `continue` sur `excluded` devient du code mort ; il est remplacé par le refus sur `avail = 0` du chapitre 5.
- `includes/room-filter.php` : plus aucune exception à coder, mais relire les trois fonctions de retrait à la lumière du modèle à deux statuts.
- `tests/test-core.php` : voir chapitre 8.

---

## 7. La règle 7, non tenue

`lme_brands_reject_booking_attempt()` passe ses deux chaînes par `esc_html__( ..., 'lme-brands' )` alors qu'aucun `load_muplugin_textdomain()` n'existe dans le greffon. Le domaine n'étant jamais chargé, les chaînes sortent en français, y compris sur linstantcle.ch dont la langue native est l'anglais.

**Attendu :** le visiteur refusé lit le message dans la langue de l'hôte qui l'a refusé. Le registre porte déjà `languages` par marque. Deux voies, à trancher par Code et à justifier dans le commit : charger un domaine de traduction en bonne et due forme, ou résoudre la langue par la marque de l'hôte courant.

---

## 8. Jeu d'essai

Le test de `room_ids_matching_category` donne aujourd'hui la catégorie 1 comme contenant les chambres 1 et 7. **La production y range aussi la 2 et la 5, et c'est la 5 qui déclenchait le défaut : le jeu d'essai ne pouvait pas l'attraper.** Aligner la carte sur la production, relevée le 14 septembre :

| Catégorie | Chambres |
|---|---|
| 1 | 1, 2, 5, 7 |
| 3 | 4, 9 |
| 4 | 8 |
| aucune | 6, 10 |

Les assertions qui attendent le statut `excluded` pour les chambres 5 et 6, celle qui vérifie le chevauchement entre `rooms` et `excluded_room_ids`, et le jeu d'essai de configuration qui porte la clé, sont à réécrire selon le modèle à deux statuts. Ajouter deux assertions de non-régression : sur l'hôte L'Instant Clé la catégorie 1 n'est pas retirée, et une chambre à `avail = 0` est refusée par la garde.

---

## 9. Recette

1. Sur l'hôte L'Instant Clé, `roomslist` avec `category_id=1` conserve son paramètre et rend les chambres actives de cette catégorie.
2. Sur l'hôte Sexcape Room, le même appel retire le paramètre et journalise un avertissement nommant la chambre étrangère.
3. Sur l'hôte Sexcape Room, `category_id=3` conserve son paramètre.
4. Aucun avertissement `excluded_room` n'existe plus, le code qui l'émettait ayant disparu.
5. Une tentative de réservation d'une chambre à `avail = 0` est refusée et journalisée, sur les deux hôtes.
6. Le visiteur refusé par la garde lit le message dans la langue de l'hôte, vérifié sur les deux.

---

## 10. Hygiène, hors correctif

Deux fichiers `.DS_Store` sont suivis par Git, à la racine et sous `docs/`, malgré la règle `.gitignore` qui les couvre : l'ignorance ne dépiste pas ce qui est déjà suivi. `git rm --cached` sur les deux.

Constat annexe, à porter au journal Vik plutôt qu'à corriger ici : `sir_vikbooking_calendars_xref` contient une ligne orpheline, id 8, qui lie une chambre 3 absente de `sir_vikbooking_rooms` à la chambre 2. Sans effet connu aujourd'hui, mais à savoir avant de s'appuyer sur cette table pour les groupes de disponibilité.

---

## 11. Prompt de lancement

À coller dans Claude Code depuis `~/Documents/icl-dev`. **Sonnet 5, effort moyen.**

> Lis `CLAUDE.md`, puis `docs/briefs/brief-correctif-phase-2-filtrage.md` en entier, puis `docs/briefs/constat-phase-0.md` §Q4. Applique la décision du chapitre 2 : toute chambre active dans Vik porte une marque, une chambre désactivée n'est servie nulle part, et le statut `excluded` disparaît du greffon. Fais entrer les chambres 5 et 6 dans le registre selon le chapitre 4, en écrivant la raison de l'affectation de la 6. Supprime `excluded_room_ids` et tout ce qui en dépend, chapitre 6. Ajoute le refus sur `avail = 0` dans la garde, et établis d'abord, preuve à l'appui dans `.local/`, si Vik masque déjà ces chambres en présentation avant d'écrire la moindre ligne de ce côté. Traite la règle 7 du chapitre 7. Aligne le jeu d'essai sur le chapitre 8 et ajoute les deux assertions de non-régression. Les six points de recette du chapitre 9 doivent être vérifiables. Ne déploie rien.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-14 | Création, à partir de la revue de la phase 2, commit `6b6b273`. Demandait de coder une exception sur le statut `excluded`. |
| 2.0 | 2026-09-14 | Réécriture après décision de Thomas : le statut `excluded` disparaît, toute chambre active porte une marque, `avail` de Vik devient la source de la désactivation. Ajout de la surface de changement, des groupes de disponibilité vérifiés, et de la contrainte de désactivation d'E5 y compris en cas d'échec. |
