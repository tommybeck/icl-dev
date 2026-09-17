# Plan de marche — réservation Sexcape Room

**Version 2, 17 septembre 2026.** Remplace la version 1 du 9 septembre, devenue fausse sur la moitié de ses lignes.

**À déposer dans `icl-dev/docs/briefs/plan-de-marche.md`, en remplacement complet.** Une fois fait, trois fichiers du dossier Communication n'ont plus de raison d'être : `plan-de-marche-ajout-B4a.md` (déjà inséré), `plan-de-marche-ajout-phase-3.md` (absorbé ici), et ce fichier même.

Documents de référence : `sexcape-room-reservation.md` pour le quoi, `constat-phase-0.md` pour l'établi, `handoff-acces-mysql.md` pour les accès, `revue-tarifs.md` et `convention-tarifs-annuelle.md` pour le chantier A, `constat-reserve-paiement.md` pour la réserve de paiement, `constat-phase-3-emails.md` pour les e-mails.

---

## 1. La ligne de partage

**Claude Code tient le dépôt. Cowork tient tout le reste.**

Ce n'est pas une répartition par difficulté, c'est une répartition par **matière** : Code manipule des fichiers versionnés et une ligne de commande, Cowork manipule des services distants, des arbitrages et des textes. Chacun est mauvais dans le métier de l'autre, et un travail rendu au mauvais endroit se paie en allers-retours.

| | Claude Code | Cowork |
|---|---|---|
| **Matière** | fichiers du dépôt, PHP, SQL, SSH, git | Airtable, Make, Elementor par MCP, Trello, Telegram, Drive |
| **Écrit dans** | `mu-plugins/`, `themes/`, `docs/constat-*.md` | `docs/brief-*.md`, `docs/revue-*.md`, `docs/plan-de-marche.md` |
| **Lit** | tout le dépôt, `.local/` en lecture seule | tout le dépôt, par le miroir en lecture seule |
| **Ne fait jamais** | écrire dans Airtable ou Make, déployer en production, modifier `.local/` | écrire une ligne de code du dépôt |
| **Modèle** | Sonnet 5 par défaut, Opus 5 sur les tâches marquées | Opus 5 |

**Thomas seul** touche aux identifiants, au DNS, à Site Tools, aux comptes Stripe et Google, et à l'administration de Vik. Ni l'un ni l'autre des deux outils ne demande, ne détient ni ne saisit un secret.

**Le compte MySQL est en lecture seule, et le reste.** Décision du 12 septembre 2026. Code lit, vérifie et constate ; il n'écrit jamais dans la base. Quand une correction est nécessaire, la chaîne est toujours la même : Cowork la formule, Thomas l'exécute dans Vik, Code la vérifie par requête.

### Règle du fichier unique

**Un fichier, un auteur.** Code écrit les constats, Cowork écrit les revues et les briefs. `journal-vik.md` fait exception : il appartient à Thomas, et lui seul y écrit.

**Corollaire appris à nos dépens :** un brief écrit par Cowork hors du dépôt se **déplace** dans `docs/briefs/`, il ne se recopie pas. Deux exemplaires ont déjà divergé, et c'est la copie du dépôt qui était la mauvaise.

### Le dépôt est le seul canal

Rien d'important ne vit dans une conversation. Une conclusion qui n'est pas écrite dans `docs/` n'existe pas : la session suivante ne la connaîtra pas. **Corollaire :** un constat écrit dans `.local/`, qui est ignoré par Git, est invisible à tout le monde. C'est arrivé une fois, sur la question de `avail` en présentation.

---

## 2. Les chantiers

| Chantier | Tenu par | État au 17 septembre |
|---|---|---|
| **A. Tarifs** | Code, Thomas, Cowork | en cours, A1 à A4 faits, A5 à A8 ouverts |
| **B. Moteur** | Code | phases 1, 2 et 3 faites ; 4 lançable ; 5, 6, 7 nouvelles |
| **C. Infrastructure** | Thomas | fait, sauf l'observation DMARC en cours |
| **D. Habillage** | Cowork puis Code | D1 et D2 faits, D3 et D4 ouverts |
| **E. Surveillance** | Cowork | pas commencé, attend A5 |
| **F. Contenu des e-mails de Vik** | Thomas | **nouveau, et le plus urgent des ouverts** |
| **G. Verrou d'hôte** | Cowork puis Code | brief écrit, jamais numéroté jusqu'ici |

### A. Tarifs

| # | Quoi | Qui | État |
|---|---|---|---|
| A1 | Anomalie B, saison à `idrooms` vide | Code | **fait** |
| A2 | Six requêtes de grille par SSH | Code | **fait** |
| A3 | Liste des corrections, `revue-tarifs.md` | Cowork | **fait**, version 2 du 12 septembre |
| A4 | Appliquer les corrections dans Vik | Thomas | **fait** pour les trois suppressions du 12 septembre |
| A5 | Valider la grille corrigée comme référence | Thomas et Cowork | **ouvert**, attend A8 ; débloque tout le chantier E |
| A6 | Fiche de saisie des lignes restantes | Code, Sonnet 5, lecture seule | **ouvert**, périmètre à confirmer, voir ci-dessous |
| A7 | Saisir ces lignes dans Vik | Thomas | ouvert |
| A8 | Vérifier par requête | Code, Sonnet 5, lecture seule | ouvert |

**Périmètre de A6, à confirmer avant de lancer.** Relevé en base le 14 septembre : `Fall vacation 2026 (rooms)` **existe** désormais, ligne 148, chambres 8 et 9, 30 CHF par nuit, donc elle sort du périmètre. En revanche **`Fall vacation 2027` n'existe pas du tout, ni villas ni rooms**, alors que `revue-tarifs.md` suppose la villa existante. Et `Carnival vacation 2027` n'a toujours pas son doublet « rooms ». Trancher ces deux points avant A6, sans quoi la fiche décrira des lignes qui n'ont pas de parent.

Reste ouvert sans instruction : l'asymétrie de tarification par occupation entre les chambres 1 et 10 et les cinq autres.

### B. Moteur

| # | Phase | Modèle | État |
|---|---|---|---|
| B1 | Registre, résolution de marque, URL par hôte, santé, journalisation | Sonnet 5, moyen | **fait** |
| B2 | Filtrage des chambres, présentation puis garde | Sonnet 5, moyen | **fait**, deux correctifs compris |
| B3 | E-mails par marque | Opus 5, élevé | **écrite et revue. Déployable** : C2 est fait |
| B4a | Réserve de paiement, lecture seule | Opus 5, élevé | **fait**, et la réponse est mauvaise, voir §6 |
| B4 | Paiement, retour, page de confirmation | Sonnet 5, moyen | **lançable**, C4 est fait |
| B5 | Marquer les rappels avant séjour, par `vikbooking_before_send_mail` | Opus 5, élevé | ouvert, après B4 |
| B6 | Parade de la réserve de paiement | Sonnet 5, moyen | ouvert, attend une décision de Thomas |
| B7 | Identité d'envoi des repas et des bons cadeaux | Sonnet 5, moyen | ouvert, attend une décision de Thomas |

Après chaque phase : **revue par Cowork** avant d'ouvrir la suivante.

**Le piège qui a coûté le plus cher sur ce chantier**, à savoir avant toute recette d'e-mail : `from_email_force` et `from_name_force` de WP Mail SMTP écrasent en silence l'expéditeur composé par le code. Désactivés sur linstantcle.ch le 15 septembre.

### C. Infrastructure

| # | Quoi | État |
|---|---|---|
| C1 | DNS et certificat de `reservation.sexcaperoom.ch` | **fait** |
| C2 | Trois domaines sous un Workspace : MX Google, DKIM propre, SPF, DMARC `p=none` avec rapport, `reservations@` par groupe Google | **fait le 15 septembre** |
| C3 | `~/.my.cnf` en mode 600 | **fait** |
| C4 | Copie du greffon Stripe dans `.local/` | **fait**, VikStripe 2.2.4, la version récente étant buguée |
| C5 | Période d'observation DMARC, une à deux semaines, avant tout durcissement | **en cours**, arrêt franc |

Trois questions sont closes et ne se rouvrent pas : le changement de domaine principal est écarté, `maisonnette-enchantee.ch` reste principal et les deux autres sont des alias ; le relais transactionnel est déclassé, l'envoi passant par l'API Gmail depuis 2023 ; **les sous-domaines d'envoi par marque tombent avec le relais**, incompatibles avec Gmail, et ne se rouvrent qu'avec lui.

### D. Habillage du tunnel

| # | Quoi | Qui | État |
|---|---|---|---|
| D1 | Relever le système visuel de sexcaperoom.ch | Cowork | **fait** |
| D2 | Implémenter dans le thème enfant, sous condition d'hôte | Code, Sonnet 5 | **fait**, par le registre et non par une comparaison de chaîne |
| D3 | Comparer une page du site et une page du tunnel côte à côte | Cowork | ouvert, attend qu'une page du tunnel existe |
| D4 | Copie des e-mails par marque : corps, signature, images, liens | Cowork | ouvert ; tant qu'il manque, l'alerte `mail_brand_leak` reste allumée |

L'en-tête et le pied de page du tunnel restent à construire dans Elementor, par Ultimate Addons, geste Cowork.

### E. Surveillance

| # | Quoi | Attend |
|---|---|---|
| E1 | Table des valeurs attendues dans Airtable | A5 |
| E2 | Scénario Make « oracle tarifaire », 06:30, alerte Telegram | E1 |
| E3 | Scénario « détecteur de silence Stripe » | B4 |
| E4 | Scénario « veille de version Vik » | rien |
| E5 | Réservation factice hebdomadaire de bout en bout | B4 |
| E6 | Casser volontairement un tarif en préproduction et vérifier que l'alerte arrive | E2 |
| E7 | Contrôler les règles `rooms.php` des textes conditionnels contre le registre | E1 |

E6 n'est pas une formalité : une alarme jamais déclenchée n'est pas une alarme. **E5 suppose d'activer une chambre de test dans Vik le temps du test, puis de la désactiver, y compris quand le test échoue.**

### F. Contenu des e-mails de Vik — Thomas, dans l'administration

Trois défauts qui touchent des clients qui paient aujourd'hui, indépendants de toute histoire de marque. À consigner dans `journal-vik.md`.

| # | Quoi | Portée |
|---|---|---|
| F1 | Corriger `{condition:access_map_cinema}` en `{condition: access_map_cinema}` dans le gabarit de la tâche 7 | chambres 1 et 5, plan d'accès jamais envoyé |
| F2 | Donner une règle `rooms.php` aux textes conditionnels 70 à 73, inertes | chambres 8 et 9 |
| F3 | Créer les blocs de check-in manquants pour les chambres 7, 8, 9 et 10 | quatre chambres sans nom, ni horaire, ni code de porte, ni adresse dans leur rappel |

F1 est une minute de travail et le plus ancien des trois. **F3 doit précéder la première vente des chambres 8, 9 et 10**, qui rouvrent le 24 septembre.

**Arbitrage préalable à F3 :** Vik groupe ses textes conditionnels par bâtiment, `maisonnette` (2, 4, 6) et `cinema` (1, 5) ; le brief groupe par calendrier, villa Aparté (2, 4) et villa Entracte (1, 7, 10). Les deux ne se recouvrent pas et la chambre 7 tombe entre les deux. À trancher avant de dupliquer, pas après.

### G. Verrou d'hôte

Le brief `brief-verrou-hote-reservation.md` décrit ce chantier depuis le 12 septembre sans qu'il ait jamais eu de numéro. Il conditionne la phase 6.

| # | Quoi | Qui | État |
|---|---|---|---|
| G1 | Résorber la divergence du brief : la copie du dossier Communication, 125 lignes, est en avance de 31 lignes sur celle du dépôt, 102 lignes | Thomas | **ouvert, et bloquant** |
| G2 | Implémenter la liste blanche et la redirection conditionnées à l'hôte | Code, Sonnet 5, moyen | attend G1 |

**G1 avant G2, sans exception.** La copie du dépôt dit « redirection permanente » là où la bonne dit 302 tant que la recette n'est pas passée, et ignore que le filtre porte sur `get_queried_object_id()` et non sur une chaîne de chemin. Implémenter depuis la mauvaise copie construirait la mauvaise chose.

---

## 3. Le séquencement

```
fait ───── A1  A2  A3  A4 ──── B1  B2  B3 ──── B4a ──── C1 C2 C3 C4 ──── D1 D2

maintenant ─┬─ F1, F2, F3 ─────────────────── Thomas, hors chemin critique, urgent
            ├─ lecture Stripe ──── décision ──── B6      Thomas puis Code
            ├─ G1 ──── G2 ───────────────────┐           Thomas puis Code
            ├─ B4 ──── revue ──── B5 ────────┤           Code
            ├─ A6 ──── A7 ──── A8 ──── A5 ───┤── E1 ─ E2 ─ E7 ─ E6
            ├─ D3, D4 ───────────────────────┤
            └─ C5, observation DMARC ────────┴─ phase 6, bascule
```

**Ce qui doit être vrai avant la bascule :** B4 recetté, B5 livré, G2 en place, D3 validé, D4 livré, E2 et E6 en service, et la réserve de paiement tranchée.

**Ce qui n'est sur le chemin critique de rien, et doit passer en premier quand même :** le chantier F et la lecture Stripe. Le premier touche des clients qui paient aujourd'hui, la seconde porte sur de l'argent déjà encaissé. C'est précisément parce qu'ils ne bloquent rien qu'ils risquent d'attendre indéfiniment.

---

## 4. Ce qui revient à Thomas, par ordre

1. **Interroger Stripe sur les 137 sessions non soldées**, et relever leur `payment_status`. Lecture seule, aucun effet de bord. C'est la seule façon de savoir si un client a été débité sans réservation. Clé secrète Stripe, donc personne d'autre. Parades classées au §8 de `constat-reserve-paiement.md`, à décider ensuite.
2. **F1**, une minute.
3. **G1** : écraser `icl-dev/docs/briefs/brief-verrou-hote-reservation.md` par la copie du dossier Communication, puis supprimer celle-ci.
4. **F3**, avant le 24 septembre, après avoir tranché l'arbitrage des groupements.
5. **F2**.
6. **Compléter `journal-vik.md`** : les enregistrements de shortcode des chambres 7, 8 et 9, la suppression des lignes orphelines 7 et 23, le renommage de la ligne 4, l'ouverture puis la fermeture des chambres 8, 9 et 10, et la création de `Fall vacation 2026 (rooms)`. Les valeurs sont encore lisibles en base ; elles ne le resteront pas.
7. **Trancher** : le périmètre de A6, l'identité d'envoi des repas et des bons cadeaux, le point d'accroche des rappels, la parade de paiement.
8. **Déployer B3** quand la recette d'en-têtes est concluante.
9. **Nettoyer le dossier Communication** des trois fichiers d'insertion devenus inutiles et du pointeur `brief-correctif-phase-2-filtrage.md`.

---

## 5. Prompts de lancement

À coller tels quels dans Claude Code, depuis `~/Documents/icl-dev`. Les prompts des tâches déjà exécutées ne sont pas reproduits ici : ils vivent dans l'historique Git et dans les constats qu'ils ont produits.

### B4 — phase 4, paiement. Sonnet 5, effort moyen

> Lis `CLAUDE.md`, `docs/briefs/sexcape-room-reservation.md`, `docs/briefs/constat-phase-0.md` §Q5, `docs/briefs/constat-perimetre-tunnel.md` §F et `docs/briefs/constat-reserve-paiement.md` en entier. Le greffon Stripe a déjà été lu trois fois : ne le relis pas, appuie-toi sur ces constats. Exécute la phase 4 : métadonnées de marque et suffixe de libellé sur le `PaymentIntent`, URL de retour vers l'hôte appelant, page de confirmation par marque. Le constat établit que le greffon reconstruit l'URL au lieu d'honorer celle du cœur : utilise le levier `payment_before_begin_transaction_vikbooking` documenté en Q5, en te souvenant que le rappel reçoit un tableau dont l'indice 0 est l'objet de paiement. **Ne traite pas la réserve de paiement ici** : c'est B6, et elle attend une décision. Ne déploie rien.

### B5 — rappels avant séjour. Opus 5, effort élevé

> Lis `CLAUDE.md` et `docs/briefs/constat-phase-3-emails.md` §4 en entier. Le point d'accroche y est établi : `vikbooking_before_send_mail`, le seul hook qui voie passer tous les e-mails de Vik, rappels compris, et son défaut connu est qu'il ne transporte aucun contexte métier. Le constat décrit le chemin qui permet de retrouver la réservation sans deviner. Marque la source des rappels avant séjour comme la phase 3 marque les confirmations : expéditeur, nom d'affichage, et rien de plus. **Le contenu du rappel n'est pas de ton ressort** : logo et titre vivent dans le gabarit de la tâche planifiée, côté Vik, chantier F. Marque indéterminée : identité neutre, alerte, jamais la mauvaise marque. Ne déploie rien.

### B6 — parade de la réserve de paiement. Sonnet 5, effort moyen

> **À ne lancer qu'une fois la parade choisie par Thomas.** Lis `CLAUDE.md` et `docs/briefs/constat-reserve-paiement.md` en entier, en particulier le §8. Implémente la parade retenue, dans un mu-plugin, **jamais dans le greffon**. Si c'est un webhook, il rejoue `notifypayment` par son `sid` et son `ts` plutôt que d'en dupliquer les dix-sept effets, le contrôleur étant idempotent. Si c'est une réconciliation planifiée, elle rattrape aussi l'existant. Journalise et alerte chaque rattrapage : un paiement récupéré en silence est un incident qu'on n'apprend jamais. Ne déploie rien.

### B7 — identité d'envoi des repas et des bons cadeaux. Sonnet 5, effort moyen

> **À ne lancer qu'une fois l'identité décidée par Thomas.** Lis `CLAUDE.md`, `docs/briefs/constat-phase-3-emails.md` et `mu-plugins/lme-brands/config/brands.php`. Ces deux flux d'envoi ne sont définis nulle part, ni dans le registre ni dans le brief e-mail. Établis d'abord par quel chemin ils partent aujourd'hui, preuves et lignes citées, puis porte l'identité décidée dans le registre et applique-la par le même mécanisme que la phase 3. Ne déploie rien.

### A6 — fiche de saisie tarifaire, lecture seule. Sonnet 5, effort moyen

> **À ne lancer qu'une fois le périmètre confirmé par Thomas**, voir le chantier A : `Fall vacation 2026 (rooms)` existe déjà, `Fall vacation 2027` n'existe ni en villas ni en rooms, et `Carnival vacation 2027` n'a pas de doublet.
>
> Lis `CLAUDE.md`, `docs/briefs/constat-phase-0.md` §Q6, `docs/briefs/revue-tarifs.md` et `docs/briefs/convention-tarifs-annuelle.md`. **Tu n'écris rien en base : le compte MySQL est en lecture seule et c'est voulu.** Ton livrable est une fiche que Thomas suivra à l'écran dans Vik.
>
> Produis `docs/briefs/constat-fiche-saisie-tarifs.md` : pour chaque ligne à créer ou modifier, **les valeurs telles qu'elles doivent être saisies dans l'écran de Vik**, pas telles qu'elles sont stockées. C'est le point difficile : les dates de saison sont stockées en secondes depuis le 1er janvier, les jours de semaine dans deux formats selon la table, et les délimiteurs de `idrooms` diffèrent entre saisons et restrictions. Donne ce que Thomas voit et tape, avec en regard la valeur attendue en base pour que A8 puisse vérifier. Signale tout écart entre ce que le texte annonce et ce que la base contient.

### A8 — vérification après saisie, lecture seule. Sonnet 5, effort moyen

> Lis `CLAUDE.md`, `docs/briefs/constat-fiche-saisie-tarifs.md` et `docs/briefs/journal-vik.md`. Thomas a saisi les lignes dans Vik. Vérifie par requête, en lecture seule, que chacune existe avec les valeurs attendues, que les saisons rejoignent les bonnes chambres par la jointure sur le jeton `-N-`, que les restrictions rejoignent la chambre 7, et qu'aucune ne dépasse un an de portée. Ajoute les preuves à `constat-phase-0.md` §Q6 sans réécrire les lignes closes. Si un écart apparaît, décris-le et arrête-toi : la correction est un geste de Thomas.

### G2 — verrou d'hôte. Sonnet 5, effort moyen

> **À ne lancer qu'après G1**, c'est-à-dire une fois `docs/briefs/brief-verrou-hote-reservation.md` remplacé par la version à jour. Vérifie d'abord que le fichier que tu lis contient bien la décision du 302 pendant la recette et le filtre par `get_queried_object_id()` ; s'il n'en parle pas, arrête-toi, tu lis la mauvaise copie.
>
> Lis `CLAUDE.md`, ce brief en entier et `docs/briefs/constat-perimetre-tunnel.md`. Implémente dans `mu-plugins/lme-brands/` la liste blanche et la redirection vers `https://sexcaperoom.ch/` de tout ce qui n'y figure pas, conditionnées à l'hôte et inertes sur tout autre hôte. Ne déploie rien.

---

## 6. Deux choses que ce plan ne doit pas laisser oublier

**Un client peut être débité sans réservation confirmée.** `constat-reserve-paiement.md` l'établit : le seul code qui confirme une commande s'exécute dans la requête de retour du navigateur, et rien ne rattrape ce retour s'il n'a pas lieu. 137 commandes en attente portent une session Stripe jamais soldée, pour 42 297.85 CHF cumulés depuis février 2024. La plupart sont sans doute des paniers abandonnés, indiscernables depuis la base. **Seule une lecture chez Stripe tranchera.**

**Le rappel avant séjour fuit la marque, aujourd'hui, en production.** Il part de `L'Instant Clé <info@maisonnette-enchantee.ch>` pour toutes les chambres, Sexcape Room comprise, avec logo et titre L'Instant Clé inconditionnels. Un client du Boudoir du Désir le reçoit déjà. B5 corrige l'expéditeur, F corrige le contenu : les deux sont nécessaires.

---

## 7. Le rythme

Une phase, une session, un commit, une revue. Ne jamais enchaîner deux phases sans revue : c'est en enchaînant qu'on livre une phase 2 bâtie sur une phase 1 fausse.

Entre deux phases, Thomas avance ses gestes. Ils sont courts, mais chacun débloque une recette : les faire tard, c'est découvrir en fin de chantier qu'aucune phase n'est vérifiable.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-09 | Création. Cinq chantiers, séquencement, prompts de lancement. |
| 2.0 | 2026-09-17 | Remise à l'état réel : A1 à A4, B1 à B3, B4a, C1 à C4, D1 et D2 faits. Ajout de B5, B6, B7, C5, D4, E7, du chantier F et du chantier G. Prompts des tâches faites retirés, prompts des tâches ouvertes écrits ou révisés. Ajout du chapitre 4, ce qui revient à Thomas, et du chapitre 6, les deux choses à ne pas oublier. |
