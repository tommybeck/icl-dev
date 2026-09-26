# Constat — correctif de `room-filter.php` et de la vérification 2 (B11)

24 septembre 2026. Tâche B11 de `docs/briefs/plan-de-marche.md`. Sources lues avant
d'écrire une ligne : `CLAUDE.md`, `constat-recette-automatisee.md` §3.1, la section B11
du plan de marche. Les prémisses de ces documents ont été revérifiées contre le code de
Vik sur `staging13.linstantcle.ch` et contre une exécution réelle, jamais reprises telles
quelles.

**Rien n'a été déployé en production. Aucune écriture en base. Aucune clé lue.** Les
seules lectures de base sont structurelles : la table des shortcodes de Vik
(`sir_vikbooking_wpshortcodes`, type, `post_id`, `roomid`) et les noms de page
(`sir_posts.post_name`).

---

## 1. La cause, établie par trace d'exécution

### Ce que disait le constat précédent, et ce qui en tient

`constat-recette-automatisee.md` §3.1 établissait que `room-filter.php` s'exécute et
journalise `foreign_room_param_stripped`, et que la chaîne de références de `JInput`
« devrait » refléter l'`unset()`. Il avançait, sans l'affirmer, qu'une autre voie de
lecture expliquerait le défaut. **Les deux premiers points tiennent, l'hypothèse est
fausse.**

Relu dans le code de Vik :

- la vue lit `VikRequest::getString('roomid', '', 'request')`
  (`site/views/roomdetails/view.html.php:20`) ;
- `VikRequest::getVar()` passe par `JFactory::getApplication()->input->request`
  (`libraries/adapter/input/request.php:69-161`), une instance de `JInput` construite
  sur `$GLOBALS['_REQUEST']` **par référence** (`input.php:88`, `&$source`), qui relit
  son tableau à chaque `get()` sans cache.

Le chemin de lecture est donc bien `$app->input` → `$_REQUEST`. Ni `$_SERVER`, ni un
attribut de shortcode lu ailleurs.

### Ce que la trace a montré

Trace temporaire posée en préproduction, un mu-plugin `zz-lme-trace-b11.php` actif
seulement sur l'hôte `staging13.` et en présence d'un paramètre `lmetrace`. Elle
journalisait `roomid` dans `$_GET`, `$_REQUEST` et `$app->input` à `init` 9, `init` 11,
`vikbooking_before_dispatch` (avec la valeur que lit `VikRequest`), `template_redirect`
-1 et +1, et à l'exécution du shortcode `vikbooking`.

Requête `…/le-boudoir-du-desir/?view=roomdetails&roomid=2`, levier Sexcape Room, fichier
d'origine :

```
#2 init prio 9 (avant Vik)                  GET='2' REQUEST='2' input='2'
#3 vikbooking_before_dispatch               GET='2' REQUEST='2' input='2'
                                            {"VikRequest_request_roomid":"2","did_template_redirect":0}
#4 init prio 11 (après Vik)                 GET='2' REQUEST='2' input='2'
#5 template_redirect prio -1                GET='2' REQUEST='2' input='2'
#6 template_redirect prio 1 (après filtre)  GET=NULL REQUEST=NULL input=NULL
#7 shortcode vikbooking exécuté             GET=NULL REQUEST=NULL input=NULL
```

**Vik dispatche son contrôleur pendant `init`**, entre les priorités 9 et 11, avant tout
`template_redirect`. Le code en cause : sa clôture `init` de priorité 10
(`vikbooking.php:152-197`, active parce que `VIKBOOKING_SITE_PREPROCESS` vaut `true`,
`defines.php:124`). Elle retrouve le shortcode de la page par `url_to_postid()`, injecte
ses attributs par `def()`, puis appelle `VikBookingBody::process()`, qui exécute le
contrôleur et met le HTML en réserve dans `VikBookingBody::$response`
(`libraries/system/body.php:36-84`). Le shortcode ne fait ensuite que restituer cette
réserve (`getHtml()`, `body.php:97`).

`room-filter.php` retirait donc bien le paramètre, et l'`unset()` était bien vu de
`$app->input` : l'étape 6 le montre. Mais il le retirait **une fois la page déjà
rendue**. Le défaut tenait au moment, pas au chemin.

Chaque requête apparaît deux fois dans la trace : WordPress redirige d'abord
`/en/le-boudoir-du-desir/` vers `/en/private-villas/le-boudoir-du-desir/` par un `301`
canonique, en gardant la chaîne de requête. La première passe ne rend rien.

---

## 2. Le correctif, `mu-plugins/lme-brands/includes/room-filter.php`

### L'accrochage passe sur `init`, priorité 1

Avant la clôture de Vik, priorité 10. Le paramètre étranger retiré, c'est le `def()` de
Vik qui injecte le défaut du shortcode, la chambre de la page. Même trace, fichier corrigé :

```
#2 init prio 9 (avant Vik)                  GET=NULL REQUEST=NULL input=NULL
#3 vikbooking_before_dispatch               GET=NULL REQUEST='4' input='4'
                                            {"VikRequest_request_roomid":"4","did_template_redirect":0}
#7 shortcode vikbooking exécuté             GET=NULL REQUEST='4' input='4'
```

Le formulaire de réservation de la page porte alors `roomdetail=4` et `roomid=4`, comme
la page sans paramètre ; avant le correctif, `roomdetail=2` et `roomid=2`.

### Un retrait trop tardif n'est plus silencieux

`lme_brands_strip_request_param()` journalise en erreur, donc alerte,
`foreign_room_param_stripped_too_late` si le contrôleur de Vik a déjà été dispatché
(`did_action('vikbooking_before_dispatch')`). C'est la classe exacte de défaut qu'on
vient de corriger : elle aurait été vue le premier jour. **Ce chemin n'a pas été exercé**
après le correctif, et c'est normal : il ne se déclenche que si le retrait arrive trop
tard.

### Un second vecteur, trouvé en mesurant : le paramètre `view`

La nouvelle mesure (chapitre 3) a montré ce que l'ancienne ne pouvait pas voir. Sur la
page du Boudoir, levier Sexcape Room, **avec ou sans paramètre de chambre** :

- `?view=availability` rend les calendriers des sept chambres actives, les deux marques
  confondues (`room_ids[]` 1, 2, 4, 7, 8, 9, 10) ;
- `?view=roomslist` en rend la liste, avec leurs liens.

Lu dans Vik : sans sélection, les deux vues prennent toutes les chambres actives
(`site/views/availability/view.html.php:37`, `site/views/roomslist/view.html.php:55`),
et aucune n'a de crochet de filtrage. Le `view` de la requête l'emporte sur celui de la
page, par le même `def()` que `roomid`. **Pire, le filtrage d'origine aggravait le cas** :
retirer un `room_ids` ou un `category_id` étranger, c'est justement laisser Vik tout
lister.

Aucune page publiée ne porte nativement l'une de ces vues. Relevé dans
`sir_vikbooking_wpshortcodes` : les seules pages publiées à shortcode Vik sont des
`roomdetails` (chambres 1, 2, 4, 7, pages privées 8, 9, 10), une `booking` et une
`tinyurl`. Ces vues n'apparaissent donc sur une page publique que par un `view` ajouté à
l'URL.

Nouvelle fonction `lme_brands_strip_foreign_listing_view()`, dans le même passage sur
`init`, après les retraits existants : une vue `availability` ou `roomslist` demandée par
la requête n'est gardée que si la sélection restante désigne au moins une chambre, et
uniquement des chambres de la marque de l'hôte. Sinon `view` est retiré, et la page
retombe sur la vue de son shortcode. Journalisé en avertissement,
`foreign_view_param_stripped`, comme `foreign_room_param_stripped`.

**Le `view` est comparé normalisé, pas brut.** Trouvé à la revue, avant commit :
`view=availability%20`, `view=%20availability` et `view=roomslist%09` rendaient bien la
vue de liste, alors que la première version comparait la valeur brute. Les sept
chambres réapparaissaient. Vik nettoie la valeur avant de choisir la vue ; son filtre
`cmd` ne garde que `A-Z 0-9 _ . -` et ôte les points de tête
(`libraries/adapter/input/filter.php:191`). La comparaison applique maintenant le même
nettoyage, puis passe en minuscules, pour ne pas dépendre de la casse du système de
fichiers. Rejoué après correction : `availability%20`, `%20availability`,
`.availability` et `roomslist%09` ne rendent plus aucune liste.

Variantes du paramètre de chambre, rejouées : `roomid[]=2`, `roomid[0]=2`, `roomid=2abc`,
`roomid=%202`, `roomid=2.0` et `roomid=%2B2` ne proposent que la chambre 4. De même,
`room_ids[]=2` et `room_ids[0]=2` avec `view=availability`.

Exemple de sélection gardée : en passe L'Instant Clé, `view=roomslist&category_id=4`
reste une liste, parce que la catégorie 4 ne contient que la chambre 8, de la marque.

### La vue `search`, déjà couverte

Le filtre `vikbooking_apply_search_results_filtering` fonctionne. Une vraie recherche
avec dates, lancée depuis la page du Boudoir, ne rend que 4, 9 et 10 sous le levier
Sexcape Room, et 1, 2, 7 et 8 sous l'autre. Un `roomdetail` étranger dans la soumission
ne mène pas à la chambre étrangère : Vik ne redirige vers `showprc` que si la chambre
figure dans les résultats déjà filtrés (`site/views/search/view.html.php:1339`).

---

## 3. La vérification 2 de `recetter-moteur.sh`

### Ce qui était faux

Elle cherchait le nom de la chambre étrangère dans toute la page. Le menu de la
préproduction nomme toutes les chambres sur chaque page : **KO garanti par construction**.
Et sa vue `search` était une requête `GET ?view=search` sans dates, qui ne rend que le
formulaire : rien de mesuré.

### Ce qu'elle mesure désormais

Les identifiants de chambre que propose le composant de Vik, relevés dans son seul
conteneur, `div.plugin-container`. On en trouve un par page, et le menu est en dehors.
L'extraction se fait par `DOMDocument` et `php` en local, déjà requis par le script.

| Identifiant | Où Vik le rend |
|---|---|
| `roomdetail`, `roomid` | formulaire de réservation de `roomdetails` |
| `room_ids[]` | formulaire de mois de `availability`, un par chambre affichée |
| `roomopt[]` | sélection de chambre, `showprc` |
| `vbSelectRoom('n', 'idroom')` | chaque résultat de `search` |
| `roomid` / `roomdetail` d'un lien | liens internes du conteneur |
| lien de chaque `li.room_result` | `roomslist`. Le lien ne porte pas d'identifiant : il est rapporté à sa chambre par la table des shortcodes de Vik (`post_id` ou nom de page vers `roomid`), la correspondance même que Vik suit pour le construire |

La marque de chaque identifiant est résolue **sur le serveur, par le registre**
(`lme_brands_resolve_room()`, nouveau mode distant `room-brands`), jamais par une liste
tenue dans le script. Un identifiant absent du registre compte comme étranger. Un
résultat de `roomslist` qu'aucune page ne rapporte rend la vue « non mesurée », jamais OK.

**Témoin d'abord.** La page propre, sans paramètre, doit proposer sa chambre et elle
seule. Sinon la mesure n'est pas jugée : `2 ??`, jamais `2 OK`.

Vecteurs, lus deux fois chacun (règle absolue n°4) : `roomdetails` avec `roomid`
étranger ; `availability` avec `room_ids` étranger, puis sans sélection ; `roomslist`
avec `category_id`, puis sans sélection ; `search` en `POST` avec dates, à 75 jours, hors
des dates des vérifications 3 et 5-6, et `roomdetail` étranger. Le `POST` part de l'URL
canonique de la page : `curl -L` suivrait le `301` en `GET` et perdrait la recherche.

### Preuve qu'elle mesure la bonne chose

Même préproduction, même levier Sexcape Room, même vérification :

| Vecteur | fichier d'origine | fichier corrigé |
|---|---|---|
| témoin, page sans paramètre | OK, #4 seule | OK, #4 seule |
| `roomdetails`, `roomid=2` | **KO**, #2 | OK, #4 |
| `availability`, `room_ids=2` | **KO**, #2 | OK, #4 |
| `availability`, sans sélection | **KO**, étrangères #1 2 7 8, avec 4 9 10 | OK, #4 |
| `roomslist`, `category_id=2` | **KO**, sept chambres | OK, #4 |
| `roomslist`, sans sélection | **KO**, sept chambres | OK, #4 |
| `search`, `roomdetail=2` | OK, #9 4 10 | OK, #9 4 10 |

Verte sur la page saine, rouge sur le défaut, verte sur le correctif. Rejouée dans les
deux sens après la normalisation de `view` (chapitre 2) : toujours verte.

---

## 4. La recette complète, les deux sens

`./recetter-moteur.sh --hote staging13.linstantcle.ch`, sans `--appliquer` : aucune
réservation créée. Levier restauré à sa valeur d'entrée, `reservation.sexcaperoom.ch`.
Cette exécution complète a précédé la normalisation de `view` trouvée à la revue. La
vérification 2 a ensuite été rejouée seule, dans les deux sens, sur le fichier final :
verte. Les vérifications 1, 3 et 4 ne dépendent pas de ce changement.

| # | Sexcape Room | L'Instant Clé |
|---|---|---|
| 1a, 1b | OK | OK |
| 2 | **OK**, les six vecteurs, étrangère #2 | **OK**, les six vecteurs, étrangère #4 |
| 3a | OK, `403` | OK, `403` |
| 3b | non concluant, connu | non concluant, connu |
| 4 | OK | OK |
| 8 | KO annoncé, G2 absent | — |

`debug.log` pendant l'exécution : 8 `foreign_room_param_stripped`, 14
`foreign_view_param_stripped`, 2 `foreign_room_booking_attempt` (la vérification 3a),
aucun `foreign_room_param_stripped_too_late`, aucune erreur PHP venue de `lme-brands`.

Les réservations 1828 et 1829 restent signalées en attente de reprise. Elles datent de
B9g et ne viennent pas de cette tâche.

---

## 5. Ce que ce correctif ne fait pas

**Suite du 26 septembre 2026 : `constat-vues-vik-par-view.md`.** Les autres vues ont été
recensées et mesurées. Trois points de ce chapitre et du chapitre 2 en sortent corrigés :
l'AJAX du site rendait aussi `roomdetails`, `availability` et `roomslist` avec les chambres
de l'autre marque ; `room_ids[]=4&room_ids[]=2abc` passait le filtrage (Vik lit `2abc`
comme 2) ; le crochet `vikbooking_before_display_<vue>` cité plus bas existe, et
`constat-phase-0.md` Q4 est corrigée en conséquence.

- **Il n'est toujours pas opposable.** C'est une couche de présentation ; la garantie
  reste `booking-guard.php`, dont la vérification 3a confirme le `403` dans les deux sens.
- **Il ne corrige pas le défaut natif d'une page.** Une page dont le shortcode viserait
  une chambre étrangère, ou une future page `roomslist` ou `availability` sans
  sélection, la montrerait encore : c'est le périmètre du verrou d'hôte
  (`brief-verrou-hote-reservation.md`), inchangé. Aucune page publiée n'est dans ce cas
  aujourd'hui.
- **Autres vues de Vik non couvertes**, hors des quatre vues publiques du brief. Le
  contrôleur du site en accepte d'autres par `view` (`site/controller.php:17-44`) :
  `searchdetails`, `searchsuggestions`, `promotions`, `packageslist`, `packagedetails`,
  `quote`. S'y ajoutent la tâche `route_listing_details` (`site/controller.php:5087`),
  qui rend une chambre par `listing_id`, et les étapes du tunnel (`showprc`,
  `oconfirm`). Aucune n'a été mesurée ici. Les étapes du tunnel sont gardées en aval par
  `booking-guard.php`. Les autres sont à recenser avant B10, si l'on veut que
  « jamais proposée » vaille au-delà des quatre vues.
- **Une piste pour la suite, non utilisée.** Contrairement à ce qu'affirment
  `room-filter.php` et `constat-phase-0.md` Q4 (« aucun hook n'existe »), le contrôleur
  générique de Vik déclenche `vikbooking_before_display_<vue>` juste avant chaque vue,
  avec l'objet vue en référence (`libraries/adapter/mvc/controller.php:263`). Il ne
  filtre pas une liste déjà construite par la vue, mais c'est un point d'accroche réel,
  par vue. Il n'a pas été retenu ici : l'accrochage sur `init` suffit et reste le plus
  simple.
- **Les requêtes AJAX de Vik** (`admin-ajax.php`) restent hors du filtrage, comme avant :
  `is_admin()` y vaut vrai.

---

## 6. État de la préproduction

- `room-filter.php` et `registry.php` corrigés sont déployés sur `staging13`, empreintes
  identiques au dépôt (`5ba5fb0f…` et `7ed4b799…`). Rien d'autre n'a changé sur le serveur.
- La trace `zz-lme-trace-b11.php` et son journal sont retirés. Une requête qui porte
  `lmetrace` ne recrée plus rien. Les copies de sauvegarde prises avant déploiement sont
  supprimées : l'état d'origine est dans Git, commit `5d498dc`.
- Le levier est à sa valeur d'entrée.

## 7. Fichiers écrits ou modifiés

| Fichier | Changement |
|---|---|
| `mu-plugins/lme-brands/includes/room-filter.php` | accrochage sur `init` 1 ; vues de liste ; alerte de retrait tardif |
| `mu-plugins/lme-brands/includes/registry.php` | commentaire : `init` au lieu de `template_redirect` |
| `mu-plugins/lme-brands/README.md` | couche de présentation mise à jour |
| `recetter-moteur.sh` | vérification 2 réécrite ; modes distants `room-brands` et `room-pages` |
| `docs/briefs/constat-recette-automatisee.md` | renvoi en tête du §3.1, version 1.2 |
| `docs/briefs/constat-signatures-crochets.md` | note : crochet `template_redirect` remplacé par `init` |

Tests unitaires de `lme-brands` : 135, aucun échec. `plan-de-marche.md` n'est pas touché :
il appartient à Cowork, qui porte B11 à « fait » après revue.
