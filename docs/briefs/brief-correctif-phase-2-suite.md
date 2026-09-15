# Brief — correctif de la phase 2, suite : la garde ne doit pas tomber avec l'hôte

Version 1, 15 septembre 2026. Rédaction Cowork, implémentation Code.

**Destination : `icl-dev/docs/briefs/brief-correctif-phase-2-suite.md`.** Écrit hors du dépôt faute d'accès en écriture depuis Cowork. **À déplacer, pas à recopier** : une fois dans `docs/briefs/`, cette copie disparaît.

Porte sur le commit `ddefdd8`, revue du 15 septembre 2026. Trois points, dont un seul demande de la réflexion.

---

## 1. Ce qui est acquis et ne se rouvre pas

Le correctif `ddefdd8` est bon. Le statut `excluded` a disparu, les chambres 5 et 6 sont dans le registre avec la raison de l'affectation de la 6, `avail` est lu dans Vik sans être dupliqué, la catégorie 1 n'est plus neutralisée, et la langue du message de refus se résout depuis `languages` du registre. **Rien de tout cela ne bouge.**

---

## 2. Le refus sur `avail = 0` doit précéder la résolution de marque

**Décision de Thomas, 15 septembre 2026.**

`lme_brands_guard_booking_record()` sort par un `return` quand l'hôte ne résout vers aucune marque, et ce `return` précède la boucle sur les chambres. Le refus sur `avail = 0` ne s'exécute donc jamais sur un tel hôte, alors que le README annonce un refus « sans condition, quelle que soit sa marque ». Les deux se contredisent.

**Pourquoi c'est la bonne correction.** Une chambre désactivée l'est partout : cette décision ne demande aucune marque pour être prise. Laisser le refus derrière la résolution de marque veut dire que le jour où l'hôte cesse de se résoudre, faute de frappe dans le registre, en-tête `Host` inattendu, nouveau sous-domaine ajouté sans être déclaré, la seule couche opposable disparaît sans bruit. C'est la règle absolue n°6, aucun échec silencieux.

**Attendu.** Parcourir les chambres d'abord, refuser celles à `avail = 0`, et n'appliquer les règles de marque qu'ensuite, quand une marque existe. Un hôte non reconnu refuse donc les chambres désactivées et laisse passer les autres, faute de contexte de marque, ce qui reste le comportement voulu pour la préproduction.

**Détail vérifié le 15 septembre, qui limite la portée du changement :** `lme_brands_evaluate_booking_room()` teste déjà `false === $available` avant toute comparaison de marque, et retourne `refuse_unavailable` sans lire `$expected_brand`. La fonction pure n'a donc pas à changer. **Seul l'ordre dans l'appelant est à revoir.**

**Conséquence assumée sur la préproduction :** une chambre désactivée n'y sera plus réservable non plus. C'est voulu.

Le message de refus se repliera sur le français sur un hôte sans marque, `lme_brands_rejection_strings()` le prévoit déjà. Rien à faire de ce côté.

---

## 3. Établir ce que Vik fait d'une chambre `avail = 0` en présentation, et l'écrire

**Décision de Thomas, 15 septembre 2026 : à valider.**

La décision du 14 septembre a deux moitiés, « une chambre désactivée dans Vik n'est servie nulle part » et « n'est pas réservable ». La garde démontre la seconde. **La première n'est démontrée nulle part.** La question avait été posée au chapitre 5 du brief précédent et n'a reçu de réponse écrite ni dans le brief, ni dans le README, ni dans un constat.

**Attendu, dans cet ordre :**

1. Établir dans `.local/`, preuve à l'appui et lignes citées, ce que Vik fait d'une chambre à `avail = 0` sur chacune des quatre vues publiques : `search`, `roomslist`, `availability`, `roomdetails`.
2. **Écrire la réponse dans ce brief même**, avant d'écrire la moindre ligne de code. Une conclusion qui ne vit que dans une session est perdue à la session suivante : c'est déjà arrivé une fois sur ce point précis.
3. N'ajouter du code à `includes/room-filter.php` que pour les vues où Vik ne masque pas déjà la chambre. Ne pas dupliquer un comportement natif.

**Cas limite à traiter explicitement :** la fenêtre de test d'E5, pendant laquelle une chambre de test est activée puis désactivée. Pendant la fenêtre elle est active, donc visible et réservable, et c'est voulu. Le code ne doit pas chercher à distinguer une chambre de test d'une autre chambre : il n'y a plus de chambre de test aux yeux du registre, c'est tout l'objet du changement précédent.

**Réponse, établie le 15 septembre 2026 : oui, sur les quatre vues, par une clause SQL en dur, pas par un filtre contournable.**

Preuve relue directement dans `.local/vikbooking/` (Vik Booking 1.8.14, copie SFTP, voir `.local/README.md`) le 15 septembre 2026 — reprise et confirmée, ligne par ligne, du constat déjà établi le 14 septembre dans `.local/q4bis_avail_masque_en_presentation.md` pour le chapitre 5 du brief précédent, qui n'avait jamais été recopié dans un brief, un README ou un constat :

```
site/views/search/view.html.php:458
    ... FROM `#__vikbooking_dispcost` AS `p`, `#__vikbooking_rooms` AS `r`, `#__vikbooking_prices` AS `rp`
    WHERE `p`.`days`=... AND `p`.`idroom`=`r`.`id` AND `p`.`idprice`=`rp`.`id` AND `r`.`avail`='1' AND (...)

site/views/roomslist/view.html.php:45
    SELECT * FROM `#__vikbooking_rooms` WHERE `avail`='1' AND (`idcat`='...;' OR ...) ...

site/views/roomslist/view.html.php:47
    SELECT * FROM `#__vikbooking_rooms` WHERE `avail`='1' ...   -- sans category_id

site/views/availability/view.html.php:35
    SELECT * FROM `#__vikbooking_rooms` WHERE (`id` IN (...) AND )? `avail`='1' ORDER BY ...

site/views/roomdetails/view.html.php:23
    SELECT * FROM `#__vikbooking_rooms` WHERE `id`=... AND `avail`='1';
```

Les quatre vues excluent `avail = 0` **dans la requête SQL elle-même**, avant tout filtrage par marque et avant que `includes/room-filter.php` n'ait la moindre occasion d'intervenir. Une chambre désactivée n'atteint jamais le tableau de résultats sur aucune des quatre vues : il n'y a rien à retirer en aval, ni par un hook, ni par un paramètre de requête retiré de `$_REQUEST`.

**Conséquence pour le code :** `includes/room-filter.php` ne reçoit aucun ajout pour `avail`. Le filtrage par marque que ce fichier applique (paramètres `roomid`, `room_ids`, `category_id`, et le filtre natif de `search`) reste la seule chose que cette couche fait ; `avail` est un attribut fixe de la requête SQL de Vik, pas un attribut de shortcode réécrivable comme `idcat` (constat-phase-0.md Q4), donc rien à dupliquer. La seule couche qui reste à corriger pour `avail = 0` est la garde de réservation, chapitre 2 ci-dessus.

---

## 4. Hygiène, non faite au tour précédent

Deux fichiers `.DS_Store` sont toujours suivis par Git, à la racine et sous `docs/`, malgré la règle `.gitignore` qui les couvre : l'ignorance ne dépiste pas ce qui est déjà suivi. `git rm --cached` sur les deux.

**Vérifié le 15 septembre 2026 : prémisse fausse, rien à faire.** `git ls-files | grep -i ds_store` ne retourne rien, `git log --all` ne montre aucune trace d'un suivi ni d'un retrait de `.DS_Store` à aucun moment de l'historique de ce dépôt, et `git rm --cached .DS_Store docs/.DS_Store` échoue avec « did not match any files ». Les deux fichiers existent bien sur le disque (`find . -name .DS_Store` les montre à la racine et sous `docs/`) mais `.gitignore:34` les couvre déjà et ils n'ont jamais été indexés. Le critère de recette n°5 (`git status` ne montre plus aucun `.DS_Store` suivi) est donc déjà vrai avant toute intervention — vérifié par `git status --porcelain`, qui n'en liste aucun.

---

## 5. Recette

1. Sur un hôte qui ne résout vers aucune marque, préproduction ou accès direct par IP, une tentative de réservation d'une chambre à `avail = 0` est refusée et journalisée.
2. Sur ce même hôte, une chambre active se réserve toujours normalement : l'absence de marque ne bloque rien d'autre.
3. Sur les deux hôtes de marque, le comportement du commit `ddefdd8` est inchangé, vérifié par le jeu d'essai existant qui doit continuer de passer sans modification.
4. Le chapitre 3 de ce brief porte une réponse écrite, avec ses lignes citées, pour les quatre vues.
5. `git status` ne montre plus aucun `.DS_Store` suivi.

---

## 6. Prompt de lancement

À coller dans Claude Code depuis `~/Documents/icl-dev`. **Sonnet 5, effort moyen.**

> Lis `CLAUDE.md`, puis `docs/briefs/brief-correctif-phase-2-suite.md` en entier. Commence par le chapitre 3 : établis dans `.local/`, preuve à l'appui et lignes citées, ce que Vik fait d'une chambre à `avail = 0` sur les vues `search`, `roomslist`, `availability` et `roomdetails`, puis **écris ta réponse dans le brief avant d'écrire une ligne de code**. Applique ensuite le chapitre 2, en ne touchant qu'à l'ordre dans `lme_brands_guard_booking_record()` : la fonction pure `lme_brands_evaluate_booking_room()` traite déjà le cas et ne doit pas changer. N'ajoute du code de présentation que pour les vues où Vik ne masque pas déjà la chambre. Fais le chapitre 4. Les cinq points de recette du chapitre 5 doivent être vérifiables. Ne déploie rien.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-15 | Création, à partir de la revue du commit `ddefdd8`. Inversion de l'ordre dans la garde, validation de la présentation des chambres désactivées, hygiène `.DS_Store`. |
| 1.1 | 2026-09-15 | Exécution. Chapitre 3 répondu et vérifié ligne par ligne dans `.local/vikbooking/` : aucun code de présentation ajouté. Chapitre 2 appliqué, uniquement l'ordre dans `lme_brands_guard_booking_record()` ; `lme_brands_evaluate_booking_room()` inchangée. Chapitre 4 : prémisse fausse, rien à `git rm --cached`, constaté et journalisé au lieu d'être passé sous silence. README de `lme-brands` mis à jour en conséquence (ordre de la garde, procédure de vérification manuelle n°7). |
