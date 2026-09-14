# Plan de marche — réservation Sexcape Room

Version 1, 9 septembre 2026. Qui fait quoi, dans quel ordre, et comment les deux outils se passent le relais.
Documents de référence : `sexcape-room-reservation.md` pour le quoi, `constat-phase-0.md` pour l'établi, `handoff-acces-mysql.md` pour les accès, `revue-tarifs.md` et `convention-tarifs-annuelle.md` pour le chantier A.

---

## 1. La ligne de partage

**Claude Code tient le dépôt. Cowork tient tout le reste.**

Ce n'est pas une répartition par difficulté, c'est une répartition par **matière** : Code manipule des fichiers versionnés et une ligne de commande, Cowork manipule des services distants, des arbitrages et des textes. Chacun est mauvais dans le métier de l'autre, et un travail rendu au mauvais endroit se paie en allers-retours.

| | Claude Code | Cowork |
|---|---|---|
| **Matière** | fichiers du dépôt, PHP, SQL, SSH, git | Airtable, Make, Elementor par MCP, Trello, Telegram, Drive |
| **Écrit dans** | `mu-plugins/`, `themes/`, `docs/constat-*.md` | `docs/brief-*.md`, `docs/revue-*.md`, `docs/plan-de-marche.md` |
| **Lit** | tout le dépôt, `.local/` en lecture seule | tout le dépôt |
| **Ne fait jamais** | écrire dans Airtable ou Make, déployer en production, modifier `.local/` | écrire une ligne de code du dépôt |
| **Modèle** | Sonnet 5 par défaut, Opus 5 sur les phases marquées | Opus 5 |

**Thomas seul** touche aux identifiants, au DNS, à Site Tools, aux comptes Stripe et à l'administration de Vik. Ni l'un ni l'autre des deux outils ne demande, ne détient ni ne saisit un secret.

**Le compte MySQL est en lecture seule, et le reste.** Décision du 12 septembre 2026, après avoir envisagé puis écarté deux voies d'écriture : une écriture SQL directe par Code, puis un plugin maison qui aurait géré saisons et restrictions. Les deux sont abandonnées. Les écrans natifs de Vik sont mal faits et pénibles, mais ils fonctionnent, et ils déclenchent les effets de bord du moteur que toute écriture parallèle risquerait de manquer, à commencer par la poussée des tarifs vers le channel manager. Voir `convention-tarifs-annuelle.md`.

Conséquence sur le partage du travail : **Code lit, vérifie et constate. Il n'écrit jamais dans la base.** Quand une correction tarifaire est nécessaire, la chaîne est toujours la même : Cowork la formule dans une revue, Thomas l'exécute dans Vik, Code la vérifie par requête et met à jour le constat.

### Règle du fichier unique

**Un fichier, un auteur.** Code écrit les constats, Cowork écrit les revues et les briefs. Aucun fichier n'est écrit par les deux : c'est ce qui évite qu'une revue soit écrasée par une exécution, et inversement.

`journal-vik.md` fait exception : il appartient à Thomas, et lui seul y écrit, parce qu'il consigne des gestes faits à la main dans une interface.

### Le dépôt est le seul canal

Rien d'important ne vit dans une conversation. Une conclusion qui n'est pas écrite dans `docs/` et validée n'existe pas : la session suivante ne la connaîtra pas, quel que soit l'outil. Code commit à la fin de chaque phase, avec un message qui dit ce que la phase établit.

---

## 2. Les cinq chantiers

Ils ne s'enchaînent pas tous : deux avancent en parallèle, trois attendent.

| Chantier | Tenu par | Bloque | Attend |
|---|---|---|---|
| **A. Tarifs** | Code puis Thomas | l'ouverture à la vente | rien, commence maintenant |
| **B. Moteur** (phases 1 à 4) | Code | la bascule | rien pour la phase 1 |
| **C. Infrastructure** | Thomas | les phases 3, 4 et 6 | rien, à faire au fil de l'eau |
| **D. Habillage du tunnel** | Cowork puis Code | la bascule | rien, peut commencer |
| **E. Surveillance** (phase 5) | Cowork | rien | la grille validée du chantier A |

### A. Tarifs — le plus urgent, et le seul qui porte sur de l'argent déjà perdu

| # | Quoi | Qui | Sortie |
|---|---|---|---|
| A1 | Trancher l'anomalie B : que fait Vik d'une saison à `idrooms` vide ? | Code, Sonnet 5 | complément dans `constat-phase-0.md` |
| A2 | Exécuter les six requêtes de grille par SSH | Code, Sonnet 5 | tableaux dans `constat-phase-0.md`, sorties brutes dans `.local/` |
| A3 | Lire la grille, dresser la liste des corrections à faire dans Vik | Cowork | `docs/revue-tarifs.md` |
| A4 | Appliquer les corrections dans l'administration de Vik | Thomas | lignes dans `journal-vik.md` |
| A5 | Valider la grille corrigée comme référence | Thomas et Cowork | tableau clos dans `constat-phase-0.md` |
| A6 | Lire les lignes sources et produire la fiche de saisie : valeurs exactes à taper dans Vik pour `Fall vacation 2026/2027 (rooms)` et pour les deux restrictions | Code, Sonnet 5, lecture seule | `constat-fiche-saisie-tarifs.md` |
| A7 | Saisir ces quatre lignes dans les écrans de Vik | Thomas | lignes dans `journal-vik.md` |
| A8 | Vérifier par requête que les quatre lignes rejoignent les bonnes chambres sur les bonnes fenêtres | Code, Sonnet 5, lecture seule | preuves ajoutées à `constat-phase-0.md` §Q6 |

A5 est ce qui débloque le chantier E. Et A4 est ce qui doit être fait **avant d'ouvrir les chambres 8 et 9 à la vente**, faute de quoi chaque vendredi et samedi se vend au tarif semaine. A6 à A8 sont un cas particulier de A4, dans la forme désormais standard : Code prépare, Thomas saisit, Code vérifie.

Reste ouvert, sans instruction d'exécution pour l'instant : le doublet « rooms » de `Carnival vacation 2027`, et l'asymétrie de tarification par occupation entre les chambres 1/10 et le reste. Voir `revue-tarifs.md`.

### B. Moteur — le gros du travail, entièrement dans Code

| # | Phase | Modèle | Attend |
|---|---|---|---|
| B1 | Phase 1, mu-plugin : registre, résolution de marque, URL par hôte, écran de santé, journalisation | Sonnet 5, moyen | rien |
| B2 | Phase 2, filtrage des chambres, présentation puis garde | Sonnet 5, moyen | B1 |
| B3 | Phase 3, e-mails par marque | **Opus 5, élevé** | B1, et C2 pour la recette |
| B4 | Phase 4, paiement, retour, page de confirmation | Sonnet 5, moyen | C4, la copie du greffon Stripe |

Après chaque phase : **revue par Cowork**, `docs/revue-phase-N.md`, avant d'ouvrir la suivante. C'est le seul garde-fou contre une phase qui a l'air finie et ne l'est pas.

### C. Infrastructure — Thomas, par petits gestes

| # | Quoi | Débloque |
|---|---|---|
| C1 | Enregistrement `A` chez name.com vers l'IP de `gfram1004`, domaine garé sur le site linstantcle.ch, certificat Let's Encrypt | la recette de B1 sous le vrai hôte, et la phase 6 |
| C2 | SPF, DKIM et DMARC sur sexcaperoom.ch, plus un service d'envoi transactionnel authentifié pour les deux domaines | B3 |
| C3 | `~/.my.cnf` en mode 600 sur le serveur linstantcle.ch | A2 |
| C4 | Copie SFTP du greffon de paiement Stripe dans `.local/` | B4 |

C3 est le plus urgent des quatre : sans lui, A2 n'avance pas.

### D. Habillage du tunnel — le chantier révélé par les deux serveurs

Les pages du tunnel vivent sur l'installation linstantcle.ch et ne partagent rien avec le site sexcaperoom.ch. Il faut donc y reconstruire l'apparence de la marque.

| # | Quoi | Qui | Sortie |
|---|---|---|---|
| D1 | Relever le système visuel de sexcaperoom.ch par les outils Elementor : palette, typographie, en-tête, pied de page, logo | Cowork | `docs/brief-habillage-tunnel.md` |
| D2 | Implémenter dans le thème enfant de linstantcle.ch, sous condition d'hôte | Code, Sonnet 5 | code plus capture de comparaison |
| D3 | Comparer une page du site et une page du tunnel côte à côte | Cowork | verdict dans la revue de phase 6 |

D1 peut commencer tout de suite et n'attend personne. Il faut seulement que sexcaperoom.ch ait déjà son apparence arrêtée : si le site est encore en construction, D1 se fait sur la maquette validée plutôt que sur le site.

### E. Surveillance — phase 5, entièrement dans Cowork

| # | Quoi | Attend |
|---|---|---|
| E1 | Table des valeurs attendues dans Airtable, une ligne par scénario tarifaire | A5 |
| E2 | Scénario Make « oracle tarifaire », créneau 06:30, alerte Telegram | E1 |
| E3 | Scénario « détecteur de silence Stripe » | B4 |
| E4 | Scénario « veille de version Vik » | rien |
| E5 | Réservation factice hebdomadaire de bout en bout | B4 |
| E6 | **Casser volontairement un tarif en préproduction et vérifier que l'alerte arrive** | E2 |

E6 n'est pas une formalité : une alarme jamais déclenchée n'est pas une alarme.

Prérequis à vérifier avant E2 : que le connecteur Make soit disponible dans la session Cowork.

---

## 3. Le séquencement

```
maintenant ─┬─ A1 ──── A2 ──── A3 ──── A4 ──── A5 ─────────────────┐
            │   (C3 avant A2)                                      │
            ├─ B1 ──── B2 ──── B3 ──── B4 ──────────────┐          │
            │   revue  revue   revue   revue            │          │
            │   (C1)           (C2)    (C4)             │          │
            ├─ D1 ─────────────────── D2 ──── D3 ───────┤          │
            │                                           │          │
            └─ E4 ──────────────────────────────────────┴─ E1 ─ E2 ─ E3 ─ E5 ─ E6
                                                                   │
                                                        phase 6 ───┘
                                                        bascule
```

Trois choses avancent dès aujourd'hui sans se gêner : le chantier A dans Code, le chantier D dans Cowork, et les gestes d'infrastructure de Thomas. Le chantier B démarre en parallèle et occupe le plus de temps.

**Ce qui doit être vrai avant d'ouvrir les chambres 8 et 9 à la vente :** A4 fait. C'est indépendant de tout le reste, et c'est la seule échéance qui a une conséquence financière immédiate.

**Ce qui doit être vrai avant la bascule de la phase 6 :** B4 recetté, D3 validé, C1 et C2 en place, E2 et E6 en service.

---

## 4. Prompts de lancement

À coller tels quels dans Claude Code, depuis `~/Documents/icl-dev`. Chacun commence par la même lecture, qui n'est pas une politesse : un modèle qui n'a pas lu le constat refera les erreurs qu'il documente.

### A1 — anomalie B

> Lis `CLAUDE.md`, puis `docs/briefs/constat-phase-0.md`. Dans `.local/vikbooking/site/helpers/lib.vikbooking.php`, relis les lignes 7560 à 7640 et établis, preuve à l'appui, ce que Vik fait d'une saison dont `idrooms` est une chaîne vide : s'applique-t-elle à toutes les chambres, ou à aucune ? Cite les lignes. Écris la réponse sous l'anomalie B du constat. Rien d'autre.

### A2 — grille tarifaire

> Lis `CLAUDE.md`, `docs/briefs/constat-phase-0.md` et `docs/briefs/handoff-acces-mysql.md`. Exécute les six requêtes de la section Q6 par SSH, en écrivant les sorties brutes dans `.local/`. Reporte les résultats dans le tableau à valider de Q6 et dans les tableaux nécessaires. Signale toute anomalie supplémentaire du même genre que les trois déjà relevées : portée de chambres incohérente, saison sans bornes, restriction inerte. Ne corrige rien dans Vik, ne propose pas de correction : constate. Clos Q6 et mets à jour le verdict de phase.

### A6 — fiche de saisie, lecture seule

> Lis `CLAUDE.md`, `docs/briefs/constat-phase-0.md` §Q6, `docs/briefs/revue-tarifs.md` et `docs/briefs/convention-tarifs-annuelle.md`. **Tu n'écris rien en base : le compte MySQL est en lecture seule et c'est voulu.** Ton livrable est une fiche que Thomas suivra à l'écran dans Vik.
>
> Relis les lignes sources : `Fall vacation 2026` et `Fall vacation 2027` (villas) dans `sir_vikbooking_seasons`, les lignes 146 et 147 du supplément weekend « rooms » comme gabarit de référence, et la restriction id 2 dans `sir_vikbooking_restrictions`.
>
> Produis `docs/briefs/constat-fiche-saisie-tarifs.md`, contenant, pour chacune des quatre lignes à créer ou modifier, **les valeurs telles qu'elles doivent être saisies dans l'écran de Vik**, pas telles qu'elles sont stockées en base. C'est le point difficile : les dates de saison sont stockées en secondes depuis le 1er janvier, les jours de semaine dans deux formats différents selon la table, et les délimiteurs de `idrooms` ne sont pas les mêmes entre saisons et restrictions. L'écran, lui, attend des dates civiles et des cases à cocher. La fiche doit donc donner ce que Thomas voit et tape, avec en regard la valeur attendue en base pour que A8 puisse vérifier.
>
> Les quatre lignes : `Fall vacation 2026 (rooms)` et `Fall vacation 2027 (rooms)`, chambres 8 et 9 seules, mêmes fenêtres de dates que les saisons villas correspondantes, majoration d'un montant absolu de 30 CHF par nuit ; la restriction id 2 rebornée au 2026-12-31 et renommée `L'Entracte All-inclusive - no check-in Fri/Sat 2026` ; une nouvelle restriction `L'Entracte All-inclusive - no check-in Fri/Sat 2027`, copie conforme de l'id 2 sur la fenêtre 2027-01-01 au 2027-12-31.
>
> Signale tout écart entre ce que ce texte annonce et ce que la base contient réellement, plutôt que de l'absorber en silence.

### A8 — vérification après saisie, lecture seule

> Lis `CLAUDE.md`, `docs/briefs/constat-fiche-saisie-tarifs.md` et `docs/briefs/journal-vik.md`. Thomas a saisi les quatre lignes dans Vik. Vérifie par requête, en lecture seule, que chacune existe avec les valeurs attendues de la fiche, que les deux saisons rejoignent bien les chambres 8 et 9 par la jointure sur le jeton `-N-`, que les deux restrictions rejoignent la chambre 7, et qu'aucune des deux restrictions ne dépasse un an de portée. Ajoute les preuves à `constat-phase-0.md` §Q6, à la suite du tableau, sans réécrire les lignes déjà closes. Si un écart apparaît, décris-le précisément et arrête-toi : la correction est un geste de Thomas, pas le tien.

### B1 — phase 1

> Lis `CLAUDE.md`, `docs/briefs/sexcape-room-reservation.md` et `docs/briefs/constat-phase-0.md`. Exécute la phase 1 : le mu-plugin `mu-plugins/lme-brands/`, portant le registre de configuration du §4.2, la résolution de marque depuis l'identifiant de chambre, la réécriture d'URL consciente de l'hôte du §4.1, l'écran de santé qui compare le registre à `sir_vikbooking_rooms`, et la journalisation du §6. Rien d'autre : ni filtrage de chambres, ni e-mails, ni paiement. Aucune correspondance en dur hors du fichier de configuration. Toute chambre absente du registre est une erreur journalisée, jamais un cas par défaut. Écris pour chaque comportement soit un test, soit une procédure de vérification manuelle. Ne déploie rien.

### B2 — phase 2

> Lis `CLAUDE.md`, le brief et le constat. Exécute la phase 2 : filtrage des chambres par marque, couche de présentation par `vikbooking_apply_search_results_filtering`, puis couche de garde sur `vikbooking_before_create_booking_record`. Rappel du constat : un attribut de shortcode cède devant un paramètre d'URL, donc la garde est le seul mécanisme opposable, et le filtre natif ne couvre que la vue `search`. Traite explicitement le cas des vues `roomslist`, `availability` et `roomdetails`. Le critère de recette n° 3 doit être vérifiable. Ne déploie rien.

### B3 — phase 3, en Opus 5, effort élevé

> Lis `CLAUDE.md`, le brief et le constat. Exécute la phase 3 : e-mails par marque, par réécriture en vol sur `vikbooking_before_send_booking_mail`, jamais par un second envoi. Relis `sir_vikbooking_ordersrooms` par `idorder` pour obtenir la chambre, puis résous la marque par le registre. Marque indéterminée : expéditeur neutre, avertissement journalisé, alerte, jamais la mauvaise marque. Périmètre : `$who` égal à `guest` et `$booking['channel']` nul. Vérifie en outre si les rappels avant séjour, produits par la tâche planifiée de Vik, empruntent le même chemin d'envoi : s'ils l'évitent, dis-le et propose le point d'accroche. Ne déploie rien.

### B4 — phase 4

> Lis `CLAUDE.md`, le brief et le constat. Relis d'abord le greffon Stripe déposé dans `.local/` et établis s'il honore la `return_url` que le coeur lui remet, ou s'il la reconstruit. Puis exécute la phase 4 : métadonnées de marque et suffixe de libellé sur le `PaymentIntent`, URL de retour vers l'hôte appelant, page de confirmation par marque. Si Stripe reconstruit l'URL, utilise le levier `payment_before_begin_transaction_vikbooking` documenté en Q5, en te souvenant que le rappel reçoit un tableau dont l'indice 0 est l'objet de paiement. Ne déploie rien.

### D2 — habillage

> Lis `CLAUDE.md`, `docs/briefs/sexcape-room-reservation.md` §4.1 et `docs/briefs/brief-habillage-tunnel.md`. Implémente l'apparence Sexcape Room des pages du tunnel dans le thème enfant de linstantcle.ch, conditionnée à l'hôte, sans toucher à l'apparence des pages L'Instant Clé. Aucune valeur de couleur, de police ou d'URL de logo en dur hors du fichier de configuration des marques. Ne déploie rien.

---

## 5. Le rythme

Une phase, une session, un commit, une revue. Ne jamais enchaîner deux phases sans revue : c'est en enchaînant qu'on livre une phase 2 bâtie sur une phase 1 fausse.

Entre deux phases, Thomas avance ses gestes d'infrastructure. Ils sont courts, mais chacun débloque une recette : les faire tard, c'est découvrir en fin de chantier qu'aucune phase n'est vérifiable.
