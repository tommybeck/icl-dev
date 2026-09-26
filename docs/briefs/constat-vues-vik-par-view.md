# Constat : les vues de Vik joignables par `view`, et leur fermeture

26 septembre 2026. Suite de B11 (`constat-correctif-room-filter.md` §5, « autres vues
de Vik non couvertes »). B11 avait fermé `roomdetails`, `availability` et `roomslist`.
Cette tâche recense toutes les autres vues que le paramètre `view` permet d'atteindre,
mesure pour chacune si elle sert une chambre de l'autre marque, et ferme celles qui
fuient.

Sources lues avant d'écrire une ligne : `CLAUDE.md`, `constat-correctif-room-filter.md`,
`constat-phase-0.md` Q4. **Le code de Vik a été lu à la source, sur
`staging13.linstantcle.ch`, Vik Booking 1.8.15** (`vikbooking.php:6`), et non dans
`.local/`, qui est en 1.8.14. Tous les chemins de Vik ci-dessous sont relatifs à
`wp-content/plugins/vikbooking/` de cette préproduction.

**Rien n'a été déployé, ni en préproduction ni en production. Aucune écriture en base.
Aucune clé lue.** Seul changement sur le serveur : le levier `LME_BRANDS_HOST_OVERRIDE`,
posé tour à tour sur chaque marque le temps des mesures, par le script distant de
`recetter-moteur.sh`, puis restauré à sa valeur d'entrée (voir §8). Les lectures de base
sont structurelles et nommées colonne par colonne :

- `sir_vikbooking_rooms` : `id`, `avail`, `img`, `idcat` ;
- `sir_vikbooking_seasons` : `id`, `promo`, `year`, `from`, `to`, `idrooms`, `promodaysadv`, pour `promo = 1` ;
- `sir_vikbooking_packages` et `sir_vikbooking_packages_rooms` : `id`, `dfrom`, `dto`, `idroom` ;
- `sir_vikbooking_wpshortcodes` et `sir_posts.post_name`, par le mode distant `room-pages`.

Les valeurs de décision sont lues en hexadécimal, comme partout dans ce chantier
(TranslatePress traduit jusqu'à la sortie de `wp eval`) :

| Valeur | Empreinte relevée | Lecture |
|---|---|---|
| `wp_get_environment_type()` | `73746167696e67` | `staging` |
| levier à l'entrée et en sortie | `7265736572766174696f6e2e73657863617065726f6f6d2e6368` | `reservation.sexcaperoom.ch` |
| marque résolue, passe Sexcape Room | `73657863617065726f6f6d` | `sexcaperoom` |
| marque résolue, passe L'Instant Clé | `6c696e7374616e74636c65` | `linstantcle` |
| marque de chaque chambre | mode distant `room-brands` | 1, 2, 5, 7, 8 : `linstantcle` ; 4, 6, 9, 10 : `sexcaperoom` |

---

## 1. L'inventaire, lu dans le contrôleur

`VikBookingController::display()` (`site/controller.php:17-44`) ne garde que les vues
d'une liste fermée. Toute autre valeur de `view` devient `vikbooking`, le formulaire de
recherche :

```
case 'roomslist':      case 'roomdetails':     case 'searchdetails':
case 'loginregister':  case 'orderslist':      case 'promotions':
case 'availability':   case 'packageslist':    case 'packagedetails':
case 'searchsuggestions': case 'booking':      case 'operators':
case 'tableaux':       case 'precheckin':      case 'revstay':
case 'chat':           case 'tinyurl':         case 'quote':
    VikRequest::setVar('view', $view);
default:
    VikRequest::setVar('view', 'vikbooking');
```

Hors des trois vues de B11, **seize vues sont joignables par `view`** : quinze dans la
liste, et `vikbooking` par défaut. Les dossiers `search`, `showprc`, `oconfirm` et
`signature` de `site/views/` ne sont atteints que par `task`, pas par `view` : ils sont
hors du périmètre de cette tâche (`search` est filtrée par son crochet natif, les étapes
du tunnel sont gardées par `booking-guard.php`).

Ce que chaque vue lit, relu dans son `view.html.php` :

| Vue | Chambres rendues | Selon |
|---|---|---|
| `searchdetails` | une chambre, `WHERE id = roomid AND avail = 1` (`:28`) | `roomid`, filtre `int` |
| `loginregister` | `roomid[i]` pour `i < roomsnum`, par `intval()` (`:18-35`) | `roomid[]`, `roomsnum` |
| `orderslist` | réservations d'un client connecté ou d'un numéro de confirmation | session, `confirmnumber` |
| `promotions` | chambres des promotions en cours (`:27-148`) | aucune restriction par la requête |
| `packageslist`, `packagedetails` | chambres des forfaits non échus (`dto >= now`) | `pkgid` |
| `searchsuggestions` | toutes les chambres actives, sauf celles qui ont une catégorie et pas celle de `categories` (`:68-88`) | `categories` |
| `booking`, `precheckin`, `revstay` | chambres d'une réservation | `sid` et `ts` de cette réservation |
| `quote` | chambres d'un devis | `ref`, l'identifiant du devis |
| `operators`, `tableaux` | chambres des permissions d'un opérateur connecté | session d'opérateur |
| `chat`, `tinyurl` | aucune | jeton, séquence courte |
| `vikbooking` | aucune (liste de catégories, pas de chambres) | — |

### Un chemin que B11 ne voyait pas : l'AJAX du site

`handle_vikbooking_ajax()` restitue la réserve de `VikBookingBody` (`vikbooking.php:205-211`),
et la clôture `init` de Vik la remplit dès que `option=com_vikbooking` est présent
(`vikbooking.php:195-198`). Avec `vik_ajax_client=site`, l'application est celle du site
(`libraries/adapter/application/application.php:101-110`). Donc :

```
/wp-admin/admin-ajax.php?action=vikbooking&vik_ajax_client=site&option=com_vikbooking&view=<vue>
```

rend **n'importe laquelle des dix-neuf vues**, sans shortcode donc sans aucun défaut, et
`room-filter.php` n'y passait pas : `is_admin()` y vaut vrai. B11 l'avait noté comme hors
filtrage (`constat-correctif-room-filter.md` §5), sans le mesurer. C'est par ce chemin que
Vik appelle lui-même `searchsuggestions`, depuis le formulaire « aucun résultat »
(`site/helpers/error_form.php:799`). Sans `vik_ajax_client`, le client est l'administration :
`view=rooms`, `view=orders` et `view=dashboard` y répondent `303` sans session.

---

## 2. La méthode

**Des identifiants, jamais des noms.** Chaque réponse est lue deux fois, à une seconde
d'écart, avec un paramètre anti-cache différent (règle absolue n°4). Les identifiants de
chambre sont relevés dans le seul conteneur du composant, `div.plugin-container`, par
`DOMDocument`, avec l'extracteur de la vérification 2 étendu :

- champs `roomdetail`, `roomid`, `roomid[]`, `room_ids[]`, `roomopt[]` ;
- `vbSelectRoom('n', 'idroom')`, `vboToggleRoomBooking('idroom')`, `data-room` ;
- paramètres `roomid` et `roomdetail` des liens, et liens vers la page d'une chambre,
  rapportés à elle par `sir_vikbooking_wpshortcodes` (la correspondance que Vik suit pour
  les construire) ;
- **la variable JavaScript `vbo_suggestions_<code>`** de `searchsuggestions`, qui porte la
  fiche complète de chaque chambre suggérée, identifiant compris. Un premier passage l'a
  manquée et rendait `searchsuggestions` propre à tort : corrigé avant toute conclusion ;
- en AJAX, faute de conteneur, le corps entier, ou le HTML que porte la réponse JSON de
  `getjson=1`.

La marque de chaque identifiant vient du registre, sur le serveur (mode distant
`room-brands`). Un identifiant absent du registre compte comme étranger.

**`searchdetails` ne rend aucun identifiant** : ni champ ni lien, seulement le nom, une
image vide et le prix. Pour rester sur les identifiants, sa réponse est comparée, par
empreinte du conteneur, à la même vue demandée avec la chambre de la page, et le
`debug.log` est relu : `lme-brands` y journalise l'identifiant qu'il retire.

Pages mesurées : `/en/private-villas/le-boudoir-du-desir/` (chambre 4) sous le levier
Sexcape Room, chambre étrangère 2 ; `/en/private-villas/l-aparte/` (chambre 2) sous le
levier L'Instant Clé, chambre étrangère 4. Dates : arrivée à J+75, une nuit.

Un premier passage contenait des lignes invalides : `curl` lisait `roomid[0]` et
`party[0][adults]` comme des motifs, n'envoyait rien, et l'extracteur relisait la page
précédente. Toutes les mesures ci-dessous viennent du passage corrigé (`curl -g`).

---

## 3. Ce qui a été mesuré, fichier de B11 en place

### Sur les pages

Même résultat aux deux lectures pour chaque vecteur. Le témoin, page sans paramètre, est
propre dans les deux passes (#4 seule, #2 seule).

| Vue, vecteur | Sexcape Room, étrangère #2 | L'Instant Clé, étrangère #4 |
|---|---|---|
| `searchdetails`, `roomid` étranger | conteneur identique à celui de la chambre #4 : **sert #4** | identique à celui de #2 : **sert #2** |
| `searchdetails`, `roomid[]` étranger | redirigée vers `roomslist`, qui retombe sur #4 | idem, #2 |
| `loginregister`, `roomsnum=1&roomid[]` étranger | **KO**, champ `roomid[]` = 2 | **KO**, `roomid[]` = 4 |
| `loginregister`, `roomid[0]` étranger | **KO**, 2 | **KO**, 4 |
| `loginregister`, chambre propre | propre, #4 | propre, #2 |
| `orderslist` | aucune chambre, formulaire de recherche de commande | aucune |
| `promotions`, et `showrooms=1&maxdate=24` | **KO**, étrangères #1 2 5 7 8, avec 4 6 9 10 | **KO**, étrangères #4 6 9 10, avec 1 2 5 7 8 |
| `packageslist`, `packagedetails&pkgid=3` | aucune : les trois forfaits sont échus | aucune |
| `searchsuggestions`, sans catégorie | **KO**, étrangères #1 2 7 8, avec 4 9 10 | **KO**, étrangères #4 9 10, avec 1 2 7 8 |
| `searchsuggestions`, `categories=1` | **KO**, étrangères #1 2 7, avec 10 | **KO**, étrangère #10, avec 1 2 7 |
| `booking`, `precheckin`, `revstay` sans clé | aucune | aucune |
| `operators`, `tableaux` | aucune, formulaire de connexion d'opérateur | aucune |
| `chat`, `quote` sans jeton | aucune, page d'erreur | aucune |
| `tinyurl`, séquence inconnue | `404` | `404` |
| `vikbooking` | aucune | aucune |
| `availability`, `room_ids[]=4&room_ids[]=2abc` | **KO**, #2 avec #4 | **KO**, `room_ids[]=2&room_ids[]=4abc` : #4 avec #2 |

`debug.log` pendant ce passage : 8 `foreign_room_param_stripped` (le `roomid` de
`searchdetails`, 4 par passe), 4 `foreign_view_param_stripped` (`roomslist`, après la
redirection de `searchdetails`), aucune erreur de `lme-brands`.

La promotion en cause est la saison 119 (`promo = 1`, année 2026, du jour 54 au jour 364),
dont `idrooms` couvre les chambres 1, 2, 4, 5, 6, 7, 8, 9 et 10. Elle liste aussi les
chambres de test désactivées 5 et 6 : la vue ne filtre pas `avail`.

### Par l'AJAX du site

Mêmes chambres aux deux lectures.

| Vue | Sexcape Room | L'Instant Clé |
|---|---|---|
| `roomdetails&roomid` étranger | **KO**, `200`, #2 | **KO**, `200`, #4 |
| `roomdetails` sans chambre | `303`, aucune vue | `303` |
| `availability`, sans sélection | **KO**, les sept chambres actives | **KO**, les sept |
| `roomslist`, sans sélection | **KO**, les sept | **KO**, les sept |
| `promotions` | **KO**, les neuf chambres | **KO**, les neuf |
| `searchsuggestions&getjson=1` | **KO**, 1 2 4 7 8 9 10 | **KO**, les mêmes |
| `searchdetails&roomid` étranger | `200`, rien ne retire `roomid` : sert la chambre demandée (`:28`) | idem |
| `loginregister&roomid[]` étranger | `500` | `500` |

**Les trois vues que B11 a fermées sur les pages restaient ouvertes par ce chemin.**

### Un trou dans le filtrage de B11 : la lecture de `room_ids`

Vik lit `room_ids` par son filtre `int` (`site/views/availability/view.html.php:18`), qui
garde le premier nombre de chaque élément (`libraries/adapter/input/filter.php:63-84`) :
`2abc` et `x2` valent 2. B11 le lisait par `lme_brands_parse_id_list()`, qui écarte tout
élément non entier. Résultat : `room_ids[]=4&room_ids[]=2abc` était vu comme `[4]`,
propre, et gardé ; Vik affichait 4 **et** 2. Reproduit aux deux lectures, dans les deux
sens. Les variantes que B11 avait rejouées (`room_ids[]=2`, `room_ids[0]=2`) ne pouvaient
pas le montrer : elles ne mêlaient pas une chambre propre et un élément mal formé.

---

## 4. Le correctif, `mu-plugins/lme-brands/includes/room-filter.php`

### Les vues fermées

La décision est sortie dans une fonction pure, `lme_brands_view_room_selection()`
(`includes/core.php`), qui dit quelles chambres une vue afficherait pour une requête
donnée, en reproduisant la lecture de Vik vue par vue :

| Vue | Sélection lue comme Vik | Gardée si |
|---|---|---|
| `availability` | `room_ids`, filtre `int`, zéros retirés | au moins une chambre, toutes de la marque |
| `roomslist` | chambres de `category_id`, filtre `int` | idem |
| `loginregister` | `roomid[i]` pour `i < roomsnum`, `intval()` | idem |
| `searchsuggestions` | chambres de `categories` et chambres sans catégorie ; seule une catégorie numérique est lue | idem |
| `promotions` | aucune restriction possible | jamais |

`availability` et `roomslist` étaient déjà fermées par B11 ; elles passent par la même
fonction, avec la lecture `int` corrigée. Le retrait de `room_ids` et de `category_id` suit
la même lecture (`lme_brands_vik_filter_int()`).

Une vue non gardée est fermée :

- **sur une page**, `view` est retiré et la page retombe sur la vue de son shortcode,
  comme en B11 (`foreign_view_param_stripped`, avertissement) ;
- **en AJAX du site**, il n'y a pas de vue de repli : la requête est **refusée en 403**
  (`foreign_view_ajax_refused`, avertissement), avec un message dans la première langue de
  la marque de l'hôte, par le registre, comme `booking-guard.php`.

Le filtrage passe désormais sur l'AJAX du site (`lme_brands_is_vik_site_ajax_request()` :
`wp_doing_ajax()` et `vik_ajax_client` nettoyé comme Vik le nettoie vaut `site`). Les
retraits de paramètre de B11 (`roomid`, `room_ids`, `category_id`) s'y appliquent aussi :
un `roomid` étranger retiré, `roomdetails` et `searchdetails` n'ont plus de chambre et
Vik redirige.

Dans le doute, on ferme : une forme que Vik lirait autrement que la fonction (tableau à la
place d'un scalaire, catégorie non numérique), une carte des catégories illisible, toute
sélection vide. La fonction compte toutes les chambres, actives ou non : sa liste est un
sur-ensemble de celle de Vik, qui peut faire fermer une vue que Vik aurait rendue propre,
jamais en laisser passer une qui ne l'est pas.

### Ce que ces fermetures changent pour un visiteur

- **`loginregister`** n'est atteinte légitimement que par la redirection du contrôleur
  quand une connexion est exigée (`site/controller.php:215` et `:220`), avec les chambres
  de la réservation en cours : celles de la marque. Elle reste ouverte dans ce cas.
- **`promotions`** n'est portée par aucune page publiée. Fermée, elle ne manque à personne.
- **`searchsuggestions`** est appelée par Vik lui-même quand une recherche ne trouve rien.
  Sans catégorie propre à la marque dans la recherche, l'appel reçoit désormais un 403 et
  **aucune suggestion ne s'affiche**, au lieu de suggérer les chambres de l'autre marque.
  Avec les catégories d'aujourd'hui, une recherche de catégorie 3 sur Sexcape Room garde
  ses suggestions (4, 9 et les chambres sans catégorie, 6 et 10) ; sur L'Instant Clé,
  aucune catégorie ne les garde, parce que la chambre 10, sans catégorie, est suggérée
  avec toutes. Rendre les suggestions à L'Instant Clé demanderait de ranger la chambre 10
  dans une catégorie : c'est une écriture dans Vik, donc un geste de Thomas, à décider.

### Ce qui n'est pas fermé, et pourquoi

- **`searchdetails`** ne fuit pas sur les pages : le retrait de `roomid` de B11 la couvre,
  et le défaut du shortcode ramène la chambre de la page. En AJAX, le même retrait
  s'applique désormais.
- **`packageslist` et `packagedetails`** ne fuient pas aujourd'hui, parce que les trois
  forfaits sont échus (`dto` en février 2024 pour 1 et 2, à la fin d'avril 2026 pour 3).
  **Elles fuiraient dès qu'un forfait couvrant des chambres des deux marques serait
  ouvert** : `packagedetails` en rend chaque chambre (`vboToggleRoomBooking('idroom')`).
  Non fermées, puisque rien ne fuit à ce jour ; à fermer avant d'ouvrir un forfait.
- **`booking`, `precheckin`, `revstay`, `quote`, `chat`** ne rendent une chambre qu'avec la
  clé d'une réservation, d'un devis ou d'une conversation existants. Ils ne proposent pas
  de chambre : ils montrent une réservation à celui qui en détient la clé. Mesurés sans clé
  seulement. **`booking` n'a pas été ouvert avec la clé d'une réservation d'essai, et ne
  doit pas l'être à la légère** : cette vue écrit à l'affichage. Elle annule une
  réservation en attente dont la chambre n'est plus libre, supprime ses occupations et
  appelle le channel manager (`site/views/booking/view.html.php:176-202`). Les
  réservations 1828 et 1829 sont en attente de reprise.
- **`operators`, `tableaux`, `orderslist`** exigent une session. Mesurés sans session.
- **`tinyurl`** redirige vers une adresse enregistrée par l'administration.

---

## 5. Les tests

### Tests unitaires

`php mu-plugins/lme-brands/tests/test-core.php` : **166 tests, aucun échec**, 141 avant
cette tâche. Rejoués aussi sous PHP 8.2.34, celui du serveur, par l'entrée standard, sans
rien écrire sur le disque distant : 166, aucun échec. `test-mail-source.php` : 22, aucun
échec.

Les 25 tests ajoutés couvrent les filtres `cmd` et `int` de Vik, la sélection de chaque
vue (y compris `room_ids[]=2abc`, `x2`, un tableau imbriqué, une chaîne lue caractère par
caractère par `loginregister`, une catégorie non numérique), et la décision complète contre
le registre réel et la carte `idcat` relevée sur staging13 : fermée dans les deux sens pour
les vues qui fuyaient, ouverte pour `loginregister` avec la chambre de la marque, pour
`searchsuggestions` en catégorie 3 sur Sexcape Room, et pour `roomslist` en catégorie 4
sur L'Instant Clé (le cas gardé de B11).

### La vérification 2 de `recetter-moteur.sh`

Étendue aux neuf vecteurs de ce constat : `availability` avec `room_ids[]=…abc`,
`loginregister`, `promotions`, `searchsuggestions`, et cinq vecteurs AJAX (`roomdetails`,
`availability`, `roomslist`, `promotions`, `searchsuggestions`). En AJAX, un `403` ou une
redirection comptent comme « aucune vue rendue » ; `curl` n'y suit pas les redirections,
dont la cible est l'administration et pas la vue.

Exécution sur staging13 le 26 septembre 2026, sans `--appliquer`, **fichier de B11 en place** :

| Vecteurs | Sexcape Room | L'Instant Clé |
|---|---|---|
| témoin et six vecteurs de B11 | OK | OK |
| les neuf vecteurs nouveaux | **KO**, chacun, aux chambres étrangères du §3 | **KO**, chacun |

Rouge sur le défaut, dans les deux sens, avec les identifiants mesurés au §3. **Le vert sur
le correctif n'est pas montré** : il demande de déployer `room-filter.php` et `core.php` sur
la préproduction, ce que cette tâche ne fait pas. C'est la preuve qui manque avant de porter
la tâche à « fait ». Les autres vérifications de cette exécution : 1a, 1b, 3a et 4 OK dans
les deux passes, 3b non concluant (connu), 8 KO annoncé (G2 absent). Levier restauré.

---

## 6. La correction de `constat-phase-0.md` Q4

Q4 affirmait qu'aucun crochet n'existe dans `roomslist`, `availability`, `roomdetails` et
`searchsuggestions`. **C'est faux** : `vikbooking_before_display_<vue>` part avant chaque
vue, depuis le contrôleur générique (`libraries/adapter/mvc/controller.php:263`), avec
l'objet vue. Le paragraphe fautif est gardé, entre guillemets, et désigné comme faux, comme
celui de Q5 ; la correction suit. Le crochet était déjà là, à la même ligne, en 1.8.14 :
l'erreur venait d'une recherche limitée aux fichiers des vues.

Ce que le crochet ne permet pas, et qui laisse la conclusion pratique de Q4 debout : filtrer
une liste de chambres. Il part avant `$view->display()`, où chaque vue lit la requête et
interroge la base. `room-filter.php` n'en fait pas usage. Son en-tête et le `README.md` de
`lme-brands` répétaient l'erreur de Q4 : corrigés.

---

## 7. Ce qui reste

- **Déployer sur la préproduction** `mu-plugins/lme-brands/includes/core.php` et
  `includes/room-filter.php`, puis rejouer `./recetter-moteur.sh --hote staging13.linstantcle.ch` :
  la vérification 2 doit passer au vert dans les deux sens. Rien ne vaut preuve avant.
- **Forfaits** : fermer `packageslist` et `packagedetails` avant d'ouvrir un forfait (§4).
- **Suggestions sur L'Instant Clé** : décider si la chambre 10 doit recevoir une catégorie
  dans Vik (§4). Écriture dans Vik, donc par Thomas.
- **Vik Channel Manager** : son action AJAX propre (`action=vikchannelmanager`) n'a pas été
  lue. Il est absent de la préproduction ; il est présent en production.
- La couche de présentation **n'est toujours pas opposable**. La garantie reste
  `booking-guard.php`, dont la vérification 3a confirme le `403` dans les deux sens.

## 8. État de la préproduction

- Aucun fichier déployé, aucun fichier temporaire posé. Les scripts de mesure vivent hors du
  dépôt, dans le répertoire de travail de la session.
- Levier : `reservation.sexcaperoom.ch` à l'entrée, relu en sortie de chaque passage
  (`7265736572766174696f6e2e73657863617065726f6f6d2e6368`).
- `debug.log` : les lignes `lme-brands` des mesures (retraits, emplois du levier), aucune
  erreur.

## 9. Fichiers écrits ou modifiés

| Fichier | Changement |
|---|---|
| `mu-plugins/lme-brands/includes/core.php` | `lme_brands_normalize_vik_cmd()`, `lme_brands_vik_filter_int()`, `lme_brands_room_category_tokens_from_idcat()`, `lme_brands_view_room_selection()`, `lme_brands_first_foreign_room()` |
| `mu-plugins/lme-brands/includes/room-filter.php` | AJAX du site filtré ; `loginregister`, `promotions`, `searchsuggestions` fermées ; lecture `int` de `room_ids` et `category_id` ; refus 403 en AJAX ; en-tête corrigé |
| `mu-plugins/lme-brands/tests/test-core.php` | 25 tests |
| `mu-plugins/lme-brands/README.md` | couche de présentation et tests mis à jour |
| `recetter-moteur.sh` | vérification 2 : neuf vecteurs, extracteur étendu, AJAX |
| `docs/briefs/constat-phase-0.md` | Q4 corrigée, paragraphe fautif gardé et désigné comme faux |
| `docs/briefs/constat-correctif-room-filter.md` | renvoi vers ce constat au §5 |

`plan-de-marche.md` n'est pas touché : il appartient à Cowork.
