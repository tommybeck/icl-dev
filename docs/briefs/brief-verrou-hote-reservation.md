# Brief — verrou d'hôte sur reservation.sexcaperoom.ch

Version 1, 12 septembre 2026. Rédaction Cowork, implémentation Code, dépôt sur le serveur par Thomas.

**Destination : `icl-dev/docs/briefs/brief-verrou-hote-reservation.md`.** Ce fichier est écrit hors du dépôt faute d'accès depuis la session Cowork. À déplacer, pas à recopier : une fois dans `docs/briefs/`, cette copie disparaît.

Documents liés : `plan-de-marche.md` (chantiers B et C), `sexcape-room-reservation.md` §4.1, `brief-habillage-tunnel.md`.

---

## Pourquoi ce brief existe

Le plan de marche prévoit en C1 que le domaine soit « garé sur le site linstantcle.ch ». C'est fait, et c'est bien l'effet attendu du parking. Mais personne n'a écrit ce que l'hôte a le droit d'exposer une fois garé. B1 a livré la réécriture d'URL consciente de l'hôte, pas un verrou de périmètre. Ce brief comble ce trou.

## Le constat, mesuré le 12 septembre 2026

`https://reservation.sexcaperoom.ch/` redirige vers `/fr` et sert la page d'accueil complète de L'Instant Clé : le logotype, le menu des villas privées, les avis Airbnb, le pied de page avec l'adresse de Prez-vers-Siviriez et les réseaux sociaux de l'autre marque.

Trois valeurs relevées sur cette réponse :

| Élément | Valeur servie sur l'hôte de réservation |
|---|---|
| `meta robots` | `follow, index, max-snippet:-1, max-image-preview:large` |
| `link canonical` | `https://linstantcle.ch/fr/` |
| `/robots.txt` | aucune interdiction générale, `Sitemap: https://linstantcle.ch/sitemap_index.xml` |

## Ce que ça coûte, aujourd'hui

**Risque de marque.** Un visiteur Sexcape Room qui ouvre ce lien atterrit sur la marque concurrente. C'est l'inverse exact de ce que le chantier D cherche à construire, et ça ne demande aucune erreur de sa part pour arriver : c'est l'état par défaut de l'hôte.

**Risque de référencement.** Un hôte entier duplique linstantcle.ch et rien n'interdit son exploration. Le `canonical` limite le risque sans le supprimer : les moteurs le traitent comme un signal, pas comme une directive, et un hôte explorable qui sert des milliers d'URL dupliquées consomme du budget d'exploration sur le site qui vend.

Ces deux risques courent maintenant, alors que D2 et B2 demanderont plusieurs jours. C'est ce qui justifie de traiter le verrou avant eux plutôt qu'avec eux.

## Ce que le verrou doit garantir

1. Sur l'hôte de réservation, seules les pages du tunnel répondent. Tout le reste part en redirection vers `https://sexcaperoom.ch/`.

   **La redirection est temporaire (302) pendant toute la recette, et ne passe en permanente (301) qu'une fois les six points validés.** Une 301 est mise en cache durablement par le navigateur du visiteur. Si la règle est mal conditionnée ne serait-ce qu'une heure, les visiteurs de linstantcle.ch garderont en mémoire locale une redirection vers sexcaperoom.ch, que ni une purge serveur ni un correctif ne rattrapent. C'est le seul geste du chantier dont l'erreur survit à sa correction.

   **Le filtre porte sur l'identifiant de page résolu (`get_queried_object_id()`), jamais sur une chaîne de chemin.** TranslatePress publie trois préfixes de langue avec slugs traduits, et Vik émet deux formes du même écran dans une seule page (`/fr/reserver/` en action de formulaire, `/fr/book-now/` en lien `searchdetails`), la seconde redirigeant vers la première après le point où le verrou s'exécute. Une poignée d'identifiants numériques se maintient, douze chaînes instables non.
2. L'hôte de réservation n'est jamais indexable, quelle que soit l'URL et quel que soit le type de réponse.
3. Le `robots.txt` de cet hôte interdit tout et ne déclare aucun plan de site.
4. **L'hôte linstantcle.ch ne change en rien.** Ni son indexation, ni ses redirections, ni son `robots.txt`.

Le point 4 est le vrai risque de ce chantier. Une règle mal conditionnée à l'hôte désindexe le site qui encaisse les réservations, et le dégât ne se voit pas le jour même.

## Le partage, et pourquoi

| Couche | Ce qu'elle porte | Pourquoi elle |
|---|---|---|
| `.htaccess` | `X-Robots-Tag: noindex, nofollow` conditionné à l'hôte, et le `robots.txt` propre à cet hôte | S'exécute avant PHP et avant NitroPack, donc tient même si WordPress tombe ou si une page sort du cache. Statique, aucune liste à maintenir. |
| `mu-plugins/lme-brands` | La liste blanche des URL du tunnel et la redirection du reste | La liste dépend du registre des marques, qui vit déjà là. La dupliquer dans Apache créerait deux sources de vérité qui divergeraient à la première évolution du tunnel. |
| Site Tools et NitroPack | Exclusion de l'hôte du cache | Sans elle, une page mise en cache pour un hôte finit servie pour l'autre, et la redirection PHP est court-circuitée. |

L'en-tête HTTP vaut mieux que la balise `meta` ici, pour deux raisons : il couvre aussi les réponses non HTML, et il ne dépend pas de Rank Math, donc il survit à une désactivation du greffon.

**La liste blanche reste à établir par Code**, pas par ce brief. Elle doit couvrir les vues que B2 traite déjà (`search`, `roomslist`, `availability`, `roomdetails`), les ressources statiques, `admin-ajax.php`, le retour de paiement, et les préfixes de langue de TranslatePress. Une liste devinée ici casserait le tunnel au premier écran non prévu.

## Pièges, tous déjà rencontrés sur cette pile

- Les règles doivent être placées **avant** le bloc `# BEGIN WordPress`. Une régénération des permaliens réécrit ce bloc et avale ce qui s'y trouve.
- NitroPack et SiteGround Optimizer touchent au `.htaccess`. Relire le fichier après toute manipulation de cache, et après toute mise à jour de ces deux greffons.
- ~~`mod_headers` doit être confirmé~~ **Levé le 12 septembre 2026** : le `.htaccess` en place porte déjà un `Header unset Vary` sans garde `<IfModule>` et le serveur ne renvoie pas d'erreur 500, donc le module est chargé.
- **`/robots.txt` est un fichier physique de 571 octets sur cette installation**, pas une réponse dynamique de WordPress. La conclusion tient, servir un fichier propre à l'hôte demande bien une règle de réécriture, mais vers un **second fichier physique** et non vers une sortie de WordPress.
- **Les quatre hôtes partagent une seule racine.** `linstantcle.ch`, `reservation.sexcaperoom.ch`, `maisonnette-enchantee.ch` et `api.linstantcle.ch` renvoient le même inode pour `index.php` et pour `.htaccess` : un seul fichier, un seul `mu-plugins/`. Toute règle non conditionnée à l'hôte frappe les quatre. `api-host.php` verrouille déjà l'hôte d'API avec ce montage et sert de patron.
- **`redirect_canonical` doit être neutralisé sur l'hôte**, sans quoi WordPress redirige les formes non canoniques vers linstantcle.ch avant que `lme-brands` n'ait réécrit `option_home`.
- **Le verrou ne voit pas les fichiers PHP servis directement.** `template_redirect` ne se déclenche ni pour `/wp-login.php`, ni pour `/wp-admin/`, ni pour `trp-ajax.php` : ce sont des fichiers physiques. Le `X-Robots-Tag` du `.htaccess` les couvre pour l'indexation, leur accessibilité est une décision séparée. Voir la dette assumée.

## Recette

1. `curl -I https://reservation.sexcaperoom.ch/` porte `X-Robots-Tag: noindex, nofollow`.
2. **`curl -I https://linstantcle.ch/` ne le porte pas.** Test le plus important des six.
3. `https://reservation.sexcaperoom.ch/robots.txt` interdit tout et ne déclare aucun plan de site.
4. `https://linstantcle.ch/robots.txt` est inchangé, mot pour mot.
5. Une URL hors tunnel sur l'hôte de réservation renvoie une 301 vers sexcaperoom.ch.
6. Le tunnel fonctionne de bout en bout sur l'hôte de réservation, y compris le retour de paiement.

Les six se rejouent après purge des deux caches, sur Safari et sur iPhone.

## Prérequis, deux gestes de Thomas avant V2

**1. ~~Enregistrer la page 6287~~ Fait le 13 septembre 2026 à 10 h 22.** Vérifié en lecture seule dans `sir_vikbooking_wpshortcodes` : enregistrement 19, `type=roomdetails`, `roomid=10`, `post_id=6287`, `lang=*`, `parent_id=3`. Il concorde désormais sur `view` et sur `roomid`, donc il l'emporte à deux points contre un sur tout autre enregistrement. Reste à porter la ligne dans `journal-vik.md`.

**Ce que la même lecture révèle, et qui n'était pas dans le constat.** Aucun enregistrement n'existe pour les chambres 7, 8 et 9. `matchShortcode` n'exigeant aucun score minimal, un lien `roomdetails` vers l'une de ces trois chambres tombe aujourd'hui sur le premier enregistrement concordant sur `view` seul, c'est-à-dire l'enregistrement 4, page 1019, `l-entracte`. Pour la chambre 7 le résultat est presque juste, le forfait étant une variante de L'Entracte. Pour La Parenthèse et L'Indécent il est faux. **Ces deux enregistrements sont donc à créer avant l'ouverture des chambres 8 et 9**, et celui de L'Indécent suppose d'abord que sa fiche existe, ce que le constat signale déjà au chantier D.

Vérification, à rejouer après tout ajout : `SELECT id, type, json, post_id FROM sir_vikbooking_wpshortcodes WHERE type = 'roomdetails' ORDER BY id`. Un enregistrement utile porte le bon `roomid` dans `json` et un `post_id` non nul.

**2. Exclure `reservation.sexcaperoom.ch` du cache dynamique SiteGround et de NitroPack.** Les deux hôtes renvoient le même `host-header`, qui identifie la racine partagée. Si la clé de cache ne comprend pas le `Host`, une réponse mise en cache pour un hôte est servie pour l'autre, et un verrou PHP est sans effet sur une réponse qui ne passe pas par PHP. La question de savoir si la clé inclut le `Host` n'est pas mesurable sans provoquer une mise en cache en production : plutôt que de la mesurer, on la rend sans objet. Un tunnel de réservation n'a de toute façon aucune raison d'être mis en cache, prix et disponibilité changeant à chaque appel. NitroPack ne traite pas encore cet hôte, ce qui laisse le temps de poser l'exclusion avant qu'il ne s'y mette.

## Dette assumée

**`/wp-login.php` et `/wp-admin/` restent joignables sur l'hôte de réservation**, et c'est un choix. Les fermer demande une règle conditionnée à l'hôte dont l'erreur ferme l'administration de linstantcle.ch, soit exactement le danger que la garantie 4 désigne. Le bénéfice serait mince : ces URL sont déjà publiques sur linstantcle.ch, donc les exposer sur un second hôte n'ouvre pas une porte de plus, seulement un chemin de plus vers la même. Le `X-Robots-Tag` les couvre pour l'indexation, qui était le problème réel. À revoir seulement si une raison nouvelle apparaît, et jamais dans le même geste que la pose du verrou.

## Retrait

Supprimer les lignes du `.htaccess` et désactiver le verrou dans le registre des marques. Aucun réglage de base de données n'est touché, donc le retour arrière est immédiat et complet. Prendre une copie du `.htaccess` avant modification.

---

## Prompts de lancement pour Claude Code

Deux sessions, pas une. Le périmètre est un travail de constat dans du code tiers, où l'oubli est silencieux ; l'implémentation est mécanique une fois le périmètre établi. Les mêler ferait écrire du code à partir d'une liste encore en train de se construire.

### V1 — périmètre du tunnel, lecture seule. **Opus 5, effort élevé.**

> Lis `CLAUDE.md`, `docs/briefs/sexcape-room-reservation.md` §4.1, `docs/briefs/constat-phase-0.md` et `docs/briefs/brief-verrou-hote-reservation.md`. **Tu n'écris aucun code et ne modifies aucun fichier hors de ton livrable.**
>
> Établis la liste exacte des URL que le tunnel de réservation doit servir sur l'hôte `reservation.sexcaperoom.ch`. Livrable : `docs/briefs/constat-perimetre-tunnel.md`. Pour chaque entrée, donne le motif, ce qui l'exige, et la preuve dans le code ou la configuration. Ne devine aucune URL : une incertitude déclarée vaut mieux qu'une entrée devinée, parce qu'une liste blanche trop étroite casse le tunnel et une liste trop large laisse une fuite.
>
> Traite explicitement les vues `search`, `roomslist`, `availability` et `roomdetails` ; `admin-ajax.php` ; le retour de paiement Stripe ; les préfixes de langue de TranslatePress ; les ressources statiques ; et les URL que Vik construit lui-même dans ses propres formulaires, qui sont celles qu'on oublie.
>
> Termine par ce que tu n'as pas pu établir.

### V2 — implémentation. **Sonnet 5, effort moyen.**

> Lis `CLAUDE.md`, `docs/briefs/brief-verrou-hote-reservation.md` et `docs/briefs/constat-perimetre-tunnel.md`.
>
> Implémente dans `mu-plugins/lme-brands/` la liste blanche du constat et la redirection vers `https://sexcaperoom.ch/` de tout ce qui n'y figure pas, conditionnées à l'hôte et inertes sur tout autre hôte. Le filtre porte sur l'identifiant de page résolu, jamais sur une chaîne de chemin, et la liste d'identifiants vit dans `config/brands.php` sous la clé `sexcaperoom`, au même titre que `host` et `rooms`. Suis le montage de `api-host.php`, y compris la neutralisation de `redirect_canonical`.
>
> **La redirection est un 302 tant que la recette n'est pas passée.** Le passage en 301 est un second geste, après validation des six points. Décisions de Thomas déjà prises, à appliquer sans les rouvrir : les pages 844, 845, 6287, 4209, 3654 et 1965 sont ouvertes ; les trois préfixes de langue sont ouverts ; `/wp-login.php` et `/wp-admin/` restent joignables.
>
> Produis séparément, sans le déposer, le fragment de `.htaccess` portant le `X-Robots-Tag` conditionné à l'hôte et la règle servant un `robots.txt` propre à cet hôte. Le dépôt sur le serveur est un geste de Thomas. Indique où le fragment s'insère par rapport au bloc `# BEGIN WordPress`, et ce qu'il faut vérifier après.
>
> Le comportement de l'hôte linstantcle.ch ne change en rien : c'est la garantie principale. Écris la procédure de vérification des six points de recette du brief. Ne déploie rien.
