# Constat — périmètre du tunnel sur `reservation.sexcaperoom.ch`

Version 1, 12 septembre 2026. Lecture seule : aucun fichier de plugin, aucune option, aucune
ligne de base n'a été modifiée. Réponse au prompt V1 de `brief-verrou-hote-reservation.md`.

Documents liés : `sexcape-room-reservation.md` §4.1, §4.3, §4.5, §4.6 ; `constat-phase-0.md`
Q4 et Q5 ; `brief-habillage-tunnel.md` (les sept écrans) ; `brief-verrou-hote-reservation.md`.

**Ce que ce document établit :** la liste des URL que le verrou d'hôte doit laisser passer, et
la preuve de chacune. **Ce qu'il n'établit pas :** l'étanchéité de marque. Le verrou est un
périmètre d'hôte, pas un filtre de chambre — voir « Ce que le verrou ne fera pas » plus bas.

---

## Sources et méthode

| Source | Ce qui en a été tiré |
|---|---|
| `.local/vikbooking/` (Vik Booking 1.8.14) | construction des URL, routage, points d'entrée AJAX |
| `.local/wp-vikstripe/` (VikStripe 2.2.4) | `success_url`, `cancel_url`, chemin de retour |
| MySQL `dbvkhvlostfyua`, lecture seule via `ssh sg-linstantcle` | `sir_vikbooking_wpshortcodes`, `sir_posts`, `sir_postmeta`, `sir_options`, `sir_trp_slug_*`, `sir_vikbooking_shortenurls`, `sir_vikbooking_orders`, `sir_vikbooking_cronjobs` |
| SSH, lecture de l'arborescence et du `.htaccess` | découverte du docroot partagé, du `robots.txt` physique, du précédent `api-host.php` |
| `curl` sur linstantcle.ch et reservation.sexcaperoom.ch | vérification de chaque chemin, codes de retour relevés le 12 septembre 2026 |

Une requête HTTP publique a été exécutée sur le moteur (`task=search`, dates 20→22 octobre 2026)
pour relever les URL réellement émises par Vik dans sa page de résultats. Aucune réservation n'a
été créée : le parcours a été arrêté avant `task=saveorder`. Le lien court `tinyurl` n'a pas été
ouvert, sa visite incrémentant un compteur en base.

---

## 1. Le fait qui change la lecture du chantier

**Les quatre hôtes ne sont pas quatre installations : c'est un seul répertoire, monté sous quatre
noms.** `stat` renvoie le même inode pour le même fichier dans les quatre racines :

```
119038289  www/linstantcle.ch/public_html/index.php
119038289  www/reservation.sexcaperoom.ch/public_html/index.php
119038289  www/maisonnette-enchantee.ch/public_html/index.php
119038289  www/api.linstantcle.ch/public_html/index.php
121904617  www/linstantcle.ch/public_html/.htaccess
121904617  www/reservation.sexcaperoom.ch/public_html/.htaccess
```

`staging10.linstantcle.ch` a un inode distinct (`119030530`) : c'est bien une installation à part.

Trois conséquences, toutes structurantes :

1. **Un seul `.htaccess` pour les quatre hôtes.** Toute règle y est vue par linstantcle.ch. Le
   point 4 du brief (« l'hôte linstantcle.ch ne change en rien ») n'est donc pas une précaution
   théorique : c'est le mode de défaillance par défaut. Chaque directive doit porter sa
   `RewriteCond %{HTTP_HOST}`.
2. **Un seul `wp-content/mu-plugins/`.** Le mu-plugin `lme-brands` déployé se chargera sur les
   quatre hôtes. Sa conditionnalité d'hôte est sa seule protection.
3. **Il existe déjà un précédent qui fonctionne**, dans ce même dossier :
   `wp-content/mu-plugins/api-host.php` verrouille `api.linstantcle.ch` avec exactement le
   montage que le brief décrit — `redirect_canonical` neutralisé, `X-Robots-Tag` sur
   `send_headers`, liste blanche sur `template_redirect` priorité 0, `status_header(404)` sinon.
   C'est le patron à reprendre, pas à réinventer.

Le `.htaccess` actuel porte déjà une redirection conditionnée à l'hôte, pour
`maisonnette-enchantee.ch` → `linstantcle.ch`, placée **avant** le bloc `# BEGIN WordPress`. La
forme attendue par le brief est donc déjà en usage sur ce serveur.

**État du déploiement au 12 septembre 2026 :** `mu-plugins/` ne contient que `api-host.php` et
`vre-paid-autoconfirm.php`. **`lme-brands` n'est pas déployé.** Vérifié : sur
`https://reservation.sexcaperoom.ch/fr/reserver/`, le formulaire de recherche porte encore
`action="https://linstantcle.ch/fr/reserver/"`. Le tunnel ne tient donc pas encore sur cet hôte,
indépendamment du verrou.

---

## 2. La forme réelle des URL du tunnel

Vik ne sert **aucune** URL de la forme `index.php?option=com_vikbooking&view=…` sur cette
installation. Tout passe par le permalien d'une page WordPress portant le shortcode `[vikbooking]`.

Chaîne établie dans le code :

```
libraries/adapter/application/route.php:42   JRoute::_()
libraries/adapter/application/route.php:145  $url = static::getPermalink($post_id)
libraries/adapter/application/route.php:210  return get_permalink($post_id)
```

`JRoute::_()` résout la vue demandée vers un identifiant de page par
`matchShortcode()` (`route.php:301`), lu dans `sir_vikbooking_wpshortcodes`, puis rend le
permalien de cette page avec le reste des paramètres en chaîne de requête
(`withQueryString()`, `route.php:383`).

Conséquence décisive pour la liste blanche : **une URL du tunnel se reconnaît à la page qu'elle
désigne, pas à la chaîne de son chemin.** La chaîne varie selon la langue, selon le slug traduit,
selon le parent, et selon le chemin de code qui l'a fabriquée ; l'identifiant de page, non.

### Le registre des shortcodes, tel qu'il est en base

`SELECT s.id, s.type, s.lang, s.post_id, p.post_name FROM sir_vikbooking_wpshortcodes s LEFT JOIN sir_posts p ON p.ID = s.post_id`

| id | vue | lang | post_id | page | remarque |
|---|---|---|---|---|---|
| 1 | `vikbooking` | `*` | 0 | — | non assigné |
| 2 | `booking` | `*` | **845** | `your-booking-detail` | |
| 3 | `roomslist` | `*` | 0 | — | non assigné |
| 4 | `roomdetails` | `*` | **1019** | `l-entracte` | `roomid=1` |
| 5, 6 | `availability` | `*`, `en-US` | 0 | — | non assignés |
| 7, 15 | `roomdetails` | `*` | 0 | — | `roomid=1` |
| 8 | `packageslist` | `*` | 0 | — | non assigné |
| 9 | `tinyurl` | `*` | **3654** | `tiny-url-2` | |
| 10 | `tinyurl` | `*` | 0 | — | |
| 14 | `roomdetails` | `*` | **146** | `l-aparte` | `roomid=2` |
| 16 | `roomdetails` | `*` | **4209** | `le-boudoir-du-desir` | `roomid=4` |
| 17, 18 | `roomdetails` | `*` | 0 | — | `roomid=6`, `roomid=5` |

**Ce registre est en retard sur les pages.** Les pages qui portent effectivement un shortcode
`[vikbooking]` sont au nombre de quatorze, relevées deux fois — dans `post_content` et dans
`_elementor_data`, les deux balayages donnant le même ensemble :

| post_id | slug natif (en) | shortcode | statut |
|---|---|---|---|
| 146 | `l-aparte` | `roomdetails roomid="2"` | publié |
| **844** | `book-now` | `vikbooking` | publié |
| **845** | `your-booking-detail` | `booking` | publié |
| 1019 | `l-entracte` | `roomdetails roomid="1"` | publié |
| 1261 | `rooms-availability-l-aparte` | `availability room_ids="2"` | publié |
| 1280 | `rooms-rooms-availability-maisonnette-cinema` | `availability room_ids="1"` | publié |
| **1965** | `check-availability` | `vikbooking` | publié |
| 2235 | `test-calendar` | `roomdetails roomid="1"` | brouillon |
| 3653 | `tiny-url` | `tinyurl` | publié |
| **3654** | `tiny-url-2` | `tinyurl` | publié |
| **4209** | `le-boudoir-du-desir` | `roomdetails roomid="4"` | publié |
| 6062 | `l-entracte-all-inclusive-…` | `roomdetails roomid="7"` | publié |
| 6270 | *(sans slug)* | `roomdetails roomid="4"` | brouillon |
| **6287** | `a-huis-clos` | `roomdetails roomid="10"` | publié |

Les pages en gras sont celles du tunnel Sexcape Room. Les pages **6287** et **6062** portent un
shortcode mais **ne figurent pas** dans `sir_vikbooking_wpshortcodes` : conséquence détaillée au
§7, piège 3.

---

## 3. Les préfixes de langue et les slugs traduits

Relevé dans `sir_options`, option `trp_settings` :

| Réglage | Valeur |
|---|---|
| `default-language` | `en_US` |
| `translation-languages` | `fr_FR`, `en_US`, `de_DE` |
| `publish-languages` | les trois |
| `url-slugs` | `fr_FR` → `fr`, `en_US` → `en`, `de_DE` → `de` |
| `add-subdirectory-to-default-language` | **`yes`** |
| `force-language-to-custom-links` | `yes` |

`add-subdirectory-to-default-language = yes` veut dire qu'**il n'existe aucune URL canonique sans
préfixe** : `/en/…`, `/fr/…`, `/de/…`, et rien d'autre. Le `.htaccess` complète, avant le bloc
WordPress, par une redirection de la racine selon `Accept-Language` — `^$ → /de/`, `→ /en/`,
défaut `→ /fr/`. C'est ce qui explique le `301` vers `/fr/` relevé par le brief.

**Les trois langues sont en service, pas seulement le français.** `sir_vikbooking_orders`, par
langue : `fr-FR` 1052, `de-CH` 392, `NULL` 154, `en-US` 90, `de-DE` 60. Restreindre la liste
blanche au seul préfixe `/fr/` casserait le tunnel pour un client germanophone, alors même que
`de-CH` — qui n'est pas dans les langues publiées par TranslatePress — est la deuxième langue
réelle du moteur.

**Les slugs sont traduits, et deux magasins coexistent.** `sir_postmeta`
(`_trp_translated_slug_fr_FR`, `_trp_translated_slug_de_DE`) et `sir_trp_slug_originals` /
`sir_trp_slug_translations`. Les deux ne concordent pas toujours : pour la page 846
(`private-villas`), le postmeta allemand dit `unsere-zimmer`, la table de slugs dit
`private-villen`, et c'est la seconde qui gagne — `/de/unsere-zimmer/das-boudoir-der-begierde/`
renvoie `301` vers `/de/private-villas/le-boudoir-du-desir/`.

**Et Vik émet les deux formes, slug natif et slug traduit, dans la même page.** Mesuré sur la page
de résultats du 12 septembre :

```
<form action="https://linstantcle.ch/fr/reserver/"                     ← slug traduit
      data-trp-original-action="https://linstantcle.ch/fr/book-now/">  ← slug natif
<a href="https://linstantcle.ch/fr/book-now/?view=searchdetails&…">    ← slug natif, non réécrit
```

`https://linstantcle.ch/fr/book-now/?view=searchdetails&roomid=4&…` renvoie `301` vers
`/fr/reserver/?view=searchdetails&roomid=4&…`. Cette redirection est produite **par WordPress**,
donc **après** le point où le verrou s'exécutera. Une liste blanche qui ne connaîtrait que le slug
traduit renverrait ce lien vers sexcaperoom.ch avant que la redirection n'ait lieu.

**D'où la recommandation de mise en œuvre, qui vaut plus que la liste elle-même :**

> Le verrou doit décider sur **l'identifiant de page résolu par WordPress**
> (`get_queried_object_id()` au `template_redirect`), pas sur une comparaison de chemin. À ce
> point du cycle, `/fr/book-now/`, `/fr/reserver/`, `/en/book-now/` et `/de/buchen/` ont tous
> résolu vers la page 844. Un seul nombre à maintenir dans le registre des marques, au lieu de
> douze chaînes qui changeront au prochain enregistrement de slug par TranslatePress.

La liste littérale qui suit reste nécessaire : elle sert de recette, et elle sert de repli pour
les URL qui n'atteignent jamais `template_redirect`.

---

## 4. La liste

### A. Pages WordPress du tunnel

Colonne « exige » : ce qui casse si l'entrée manque.

#### A1 — `844`, entrée du tunnel : recherche, résultats, options, coordonnées

| | |
|---|---|
| **Vue(s) servies** | `vikbooking` (formulaire), `search`, `searchdetails`, `showprc`, `oconfirm`, `searchsuggestions` |
| **Écrans du brief habillage** | 1, 2, 4, 5 |
| **Chemins vérifiés** | `/en/book-now/` 200 · `/fr/reserver/` 200 · `/de/buchen/` 200 · `/fr/book-now/` 301 → `/fr/reserver/` |
| **Motif** | c'est la page qui porte le shortcode `[vikbooking view="vikbooking"]`, et **tout le parcours avant paiement s'y déroule sans changer d'URL** |
| **Preuve** | `site/views/vikbooking/tmpl/default.php:51` — `$form_method = VBOPlatformDetection::isWordPress() ? 'post' : 'get'` ; `:54` l'action est `JRoute::_('index.php?option=com_vikbooking'…)`, sans `view`, donc `replace()` n'est pas appelé (`route.php:55`) et l'URL reste celle de la page courante. `site/controller.php:47` `search()` fait `VikRequest::setVar('view','search')` puis `parent::display()` : la vue `search` se rend sur la même URL. Idem `showprc()` `:53` et `oconfirm()` `:59`. Enchaînement des formulaires : `showprc/tmpl/default.php:854` → `task=oconfirm` ; `oconfirm/tmpl/default.php:1213` → `task=saveorder`. |
| **Mesure** | le formulaire relevé porte `<input type="hidden" name="Itemid" value="844">`, ce qui fixe la page pour tout le reste du parcours |
| **Exige** | sans elle, il n'y a pas de tunnel du tout |

#### A2 — `1965`, seconde entrée

| | |
|---|---|
| **Vue** | `vikbooking` |
| **Chemins vérifiés** | `/en/check-availability/` 200 · `/fr/verifier-la-disponibilite/` 200 · `/de/verfugbarkeit-prufen/` 200 |
| **Motif** | seconde page portant le même shortcode. `JRoute` ne la choisit jamais de lui-même (`best()` retient le premier enregistrement, `admin/models/shortcodes.php:127-160`), mais si un lien depuis sexcaperoom.ch pointe dessus, le parcours s'y déroule entièrement avec `Itemid=1965` |
| **Exige** | rien aujourd'hui. **À trancher :** si le tunnel Sexcape Room n'entre que par 844, cette page est hors périmètre et son absence de la liste blanche est voulue ; si elle sert d'entrée, elle est indispensable. Voir §8. |

#### A3 — `845`, détail de réservation, paiement, retour de paiement, confirmation

| | |
|---|---|
| **Vue(s) servies** | `booking`, `precheckin`, `revstay`, et la tâche `notifypayment` |
| **Écrans du brief habillage** | 6 et 7 |
| **Chemins vérifiés** | `/en/your-booking-detail/` 200 · `/fr/les-details-de-votre-reservation/` 200 · `/de/ihre-buchungsdetails/` 200 · `/your-booking-detail/` 301 → `/les-details-de-votre-reservation/` |
| **Motif** | c'est la page de la vue `booking`, seul enregistrement de shortcode assigné pour cette vue, donc la cible systématique de `best(['booking'])` |
| **Preuve** | `site/controller.php:2176-2177` : `$model->best(array('booking'), …)` puis `JRoute::_($return_url."&Itemid={$itemid}")`. Même construction dans `site/views/booking/tmpl/default.php:934-942`. Les redirections d'après enregistrement y aboutissent aussi : `controller.php:1260`, `:1477`, `:1652`. |
| **Exige** | **le retour de paiement, la page de confirmation et la page d'erreur.** C'est l'entrée dont l'absence ne se verrait qu'après un paiement encaissé. |

#### A4 — `6287`, fiche À Huis Clos (chambre 10)

| | |
|---|---|
| **Vue** | `roomdetails`, `roomid=10` |
| **Chemins vérifiés** | `/en/a-huis-clos/` 200 · `/fr/a-huis-clos/` 200 · `/de/a-huis-clos/` 200 (slug identique dans les trois langues, `sir_trp_slug_translations`) |
| **Motif** | écran 3 du brief habillage, pour l'une des trois chambres Sexcape Room |
| **Preuve** | `post_content` et `_elementor_data` de 6287 portent `[vikbooking roomid="10" view="roomdetails" lang="*"]`. Le registre des marques donne chambre 10 → marque `sexcaperoom`. |
| **Réserve** | cette page **n'est pas** dans `sir_vikbooking_wpshortcodes` : Vik ne construira jamais de lien vers elle. Voir §7, piège 3. |

#### A5 — `4209`, fiche Le Boudoir du Désir (chambre 4)

| | |
|---|---|
| **Vue** | `roomdetails`, `roomid=4` |
| **Chemins vérifiés** | `/en/private-villas/le-boudoir-du-desir/` 200 · `/fr/villas-privees/le-boudoir-du-desir/` 200 · `/de/private-villen/das-boudoir-der-begierde/` 200 |
| **Motif** | écran 3, pour la chambre 4 |
| **Preuve** | enregistrement 16 de `sir_vikbooking_wpshortcodes`, `post_id=4209`, `{"roomid":"4"}`. C'est la seule fiche Sexcape Room que `JRoute` sait atteindre. |
| **Réserve** | page **enfant de `private-villas` (846)**, une arborescence L'Instant Clé. Le chemin lui-même porte le nom de l'autre marque en français comme en anglais, ce qui contredit le §3 du brief principal (« ni dans une URL »). À traiter au chantier D, pas ici. |

#### A6 — `3654`, redirecteur de liens courts

| | |
|---|---|
| **Vue** | `tinyurl` |
| **Chemin** | `/{en\|fr\|de}/tiny-url-2/?to=<code>` — le slug n'est traduit dans aucune langue (`sir_trp_slug_translations` : `tiny-url-2` → `tiny-url-2` en `fr_FR`) |
| **Motif** | `best(['tinyurl'])` retient l'enregistrement 9, donc la page 3654 |
| **Preuve** | `admin/helpers/src/model/shortenurl.php:219-225` ; `site/views/tinyurl/view.html.php:21` lit `$app->input->getAlnum('to')` et ferme en erreur si le code est absent — d'où le `404` mesuré sur `/fr/tiny-url-2/` sans paramètre, qui est le comportement normal et non une panne |
| **Exige** | tout lien court déjà envoyé à un client. `sir_vikbooking_shortenurls` compte **759 lignes**, la plus récente du 11 septembre 2026 : le mécanisme est actif. |
| **Réserve mesurée** | aucun gabarit d'e-mail actuel ne contient `{booking_link}` — balayage de `sir_vikbooking_texts`, `sir_vikbooking_config` et `sir_vikbooking_cronjobs`, zéro occurrence. Les 759 lignes sont donc produites par un autre appelant (`admin/helpers/widgets/booking_details.php`, `conditionalrules/qrcode.php`, `cronjobs/customer_discounts.php`). **L'entrée coûte une ligne et évite un lien mort dans un e-mail ou un SMS : la garder.** |

### B. `/wp-admin/admin-ajax.php`

| | |
|---|---|
| **Chemin** | `/wp-admin/admin-ajax.php` — jamais préfixé de langue, jamais traduit |
| **Motif** | **tous** les appels AJAX du tunnel y passent |
| **Preuve** | `vikbooking.php:200-201` enregistre `wp_ajax_vikbooking` et `wp_ajax_nopriv_vikbooking`. `admin/helpers/src/platform/org/wordpress/uri.php:211` : `$uri = admin_url('admin-ajax.php') . '?' . $path->getQuery();`, avec `action=vikbooking` (`:202`) et `vik_ajax_client=site` (`:207`). |
| **Appelants relevés dans les vues du tunnel** | `oconfirm` → `task=validatepin` (`oconfirm/tmpl/default.php:810`), `task=states_load_from_country` (`:944`) ; `roomdetails` → `task=get_avcalendars_data` ; `booking` → `task=upgrade_room` ; `site/helpers/error_form.php:799` → `view=searchsuggestions` |
| **Exige** | la saisie du pays et du canton, la validation du code, le calendrier de disponibilité, les suggestions quand aucune chambre n'est libre |
| **Note** | `admin-ajax.php` n'atteint **jamais** `template_redirect`. Une liste blanche greffée là ne le voit pas passer, donc ne peut pas le bloquer par accident — mais une règle `.htaccess` de redirection, elle, le verrait. Le `robots.txt` en place l'autorise déjà explicitement (`Allow: /wp-admin/admin-ajax.php`). |

### C. `/wp-content/plugins/translatepress-multilingual/includes/trp-ajax.php`

| | |
|---|---|
| **Motif** | traduction dynamique du contenu produit par Vik, qui n'existe pas dans le dictionnaire au moment du rendu |
| **Preuve** | relevé dans la page servie : `trp_data = {"trp_custom_ajax_url":"https://linstantcle.ch/wp-content/plugins/translatepress-multilingual/includes/trp-ajax.php", …}` ; `trp_advanced_settings` porte `show_dynamic_content_before_translation: yes` et `disable_dynamic_translation: no` |
| **Exige** | sans lui, les libellés produits par Vik s'affichent en anglais dans le tunnel français |
| **Note** | fichier physique sous `wp-content` : servi directement par Apache (`RewriteCond %{REQUEST_FILENAME} !-f`), donc hors de portée d'une liste blanche PHP. À ne pas oublier dans une règle `.htaccess`. |

### D. Ressources statiques

Aucune n'a besoin d'être listée dans la liste blanche PHP, et la raison est mécanique : le bloc
`# BEGIN WordPress` du `.htaccess` ne passe la main à `index.php` que pour ce qui n'est **ni un
fichier ni un répertoire** existant.

```
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

WordPress n'est donc jamais chargé pour un fichier statique, et `template_redirect` ne se
déclenche jamais. Répertoires effectivement sollicités par les pages du tunnel, relevés sur les
deux pages servies :

| Chemin | Contenu |
|---|---|
| `/wp-content/plugins/vikbooking/site/resources/` | CSS et JS du moteur côté public |
| `/wp-content/plugins/vikbooking/admin/resources/` | images et icônes utilisées par les vues publiques |
| `/wp-content/plugins/{elementor, elementor-pro, ultimate-elementor, header-footer-elementor, translatepress-multilingual, wp-consent-api, official-facebook-pixel, google-site-kit, woocommerce, woocommerce-payments, wpforms, wp-airbnb-review-slider}/` | assets chargés par le gabarit de page |
| `/wp-content/themes/astra/` et `/wp-content/themes/astra-child/` | thème |
| `/wp-content/uploads/` | photos des chambres |
| `/wp-content/astra-local-fonts/` | polices hébergées |
| `/wp-includes/js/` et `/wp-includes/css/` | cœur WordPress |

**Ce qu'il faut en retenir pour le `.htaccess` :** l'en-tête `X-Robots-Tag` conditionné à l'hôte
s'appliquera à ces fichiers, ce qui est souhaitable. Une **redirection** `.htaccess` de tout ce
qui n'est pas le tunnel, elle, les casserait toutes. Le partage du brief est donc juste :
l'en-tête et le `robots.txt` dans Apache, la redirection dans PHP.

### E. `/.well-known/`

| | |
|---|---|
| **Motif** | renouvellement du certificat Let's Encrypt de `reservation.sexcaperoom.ch` |
| **Preuve** | `www/linstantcle.ch/public_html/.well-known/acme-challenge/` existe et a été modifié le 12 septembre 2026 à 14:23 ; il contient plusieurs dizaines de jetons. Le dossier porte aussi `apple-developer-merchantid-domain-association`, utilisé par Apple Pay. |
| **Exige** | le certificat de l'hôte de réservation, et le paiement Apple Pay si Stripe l'expose |
| **Note** | les jetons sont des fichiers physiques, donc servis par Apache sans passer par WordPress : la liste blanche PHP ne peut pas les casser. **Une règle `.htaccess` de redirection, si elle est un jour ajoutée, doit exclure `/.well-known/` explicitement.** C'est la façon classique dont un verrou d'hôte tue le renouvellement d'un certificat, trois mois après sa pose. |

### F. Le retour de paiement Stripe

Le retour n'introduit **aucune URL nouvelle** : il atterrit sur la page 845, déjà listée en A3.
La chaîne, établie dans le code et déjà tranchée en Q5 du constat de phase 0 :

| Étape | Valeur | Preuve |
|---|---|---|
| Construction | `notify_url` = `JUri::root()."index.php?option=com_vikbooking&task=notifypayment&sid=…&ts=…&tmpl=component"`, puis routé avec `Itemid=845` | `site/controller.php:2175-2182` ; `views/booking/tmpl/default.php:930-942` |
| Session Stripe | `'success_url' => $this->get('notify_url')` | `.local/wp-vikstripe/stripe.php:400` et `:449` |
| Annulation | `'cancel_url' => $this->get('return_url') . "&payment=canceled"` | `.local/wp-vikstripe/stripe.php:401` |
| Retour effectif | le greffon se greffe sur `payment_on_after_validation_vikbooking` et redirige lui-même vers `view=booking&sid=…&ts=…`, avec `exit`, avant que `complete()` ne lise `return_url` | `.local/wp-vikstripe/vikbooking/stripe.php:223-242` |

Les trois chemins — succès, annulation, échec — aboutissent donc à la page 845. **L'URL de retour
porte l'hôte de la marque parce que `JUri::root()` descend sur `home_url()`**
(`libraries/adapter/uri/uri.php:119-125`), que `lme-brands` filtre par `option_home`.

Deux conséquences pour la recette du verrou :

1. la page 845 doit accepter `?task=notifypayment&…&tmpl=component` **et** `?view=booking&…` **et**
   `?view=booking&…&payment=canceled`. Un verrou par identifiant de page les accepte toutes les
   trois sans rien déclarer ; un verrou par chaîne exacte n'en accepterait qu'une ;
2. **aucun point de terminaison de webhook Stripe n'existe** sur cette installation. Recherche de
   `webhook` dans `.local/wp-vikstripe/` : uniquement la bibliothèque officielle Stripe
   (`Stripe/lib/WebhookEndpoint.php`), jamais appelée par le greffon. Le retour est donc
   entièrement porté par le navigateur du client. Rien à ouvrir de plus.

### G. Les URL que Vik construit lui-même

C'est la catégorie que le brief signale comme oubliée. Voici ce que le moteur a réellement émis,
relevé dans la page de résultats du 12 septembre 2026 :

| URL émise | Vue atteinte | Page |
|---|---|---|
| `/fr/reserver/?view=vikbooking` (action de formulaire) | retour au formulaire | 844 |
| `/fr/book-now/?view=searchdetails&roomid=N&checkin=…&tmpl=component` | `searchdetails`, ouvert en fenêtre | 844 |
| `/fr/book-now/?view=vikbooking&checkin=…&category_id=` | changement de dates | 844 |
| `/fr/les-details-de-votre-reservation/?task=search` | page `booking` | 845 |
| `/de/buchen/`, `/en/book-now/` (sélecteur de langue) | formulaire | 844 |

Trois enseignements :

1. **`searchdetails` n'a ni page ni enregistrement de shortcode, et pourtant il est servi.** Le
   gestionnaire de shortcode injecte la vue par `def()` (`vikbooking.php:265`), et `def()` cède
   devant un paramètre de requête de même nom (`libraries/adapter/input/input.php:259-265`). La
   vue demandée dans l'URL l'emporte donc sur l'attribut du shortcode. **Toutes les vues sans page
   dédiée — `searchdetails`, `precheckin`, `revstay`, `loginregister`, `packagedetails` — se
   rendent sur l'URL d'une page existante.** Aucune ne demande d'entrée supplémentaire.
2. **Vik émet le slug natif là où TranslatePress ne réécrit pas**, donc `/fr/book-now/` cohabite
   avec `/fr/reserver/`. Voir §3.
3. **Les liens stockés en base portent l'hôte figé au moment de leur création, sans préfixe ni
   slug traduit.** `sir_vikbooking_shortenurls`, ligne la plus récente :
   `https://linstantcle.ch/your-booking-detail/?sid=1247929427&ts=1788295193&lang=fr`. Cette forme
   nue renvoie `301` vers la forme préfixée et traduite ; le verrou doit donc l'accepter aussi.
   Sur l'hôte de réservation, la même construction donnerait
   `https://reservation.sexcaperoom.ch/your-booking-detail/?sid=…`.

**Et une alerte qui dépasse ce constat.** Ces liens sont fabriqués par
`admin/helpers/src/booking/registry.php:1265`, appelé depuis des contextes d'administration et de
tâche planifiée. Or `lme_brands_should_rewrite_host()` retourne `false` en administration et en
cron (`mu-plugins/lme-brands/includes/url-rewrite.php:27-40`), volontairement et à juste titre.
**Un lien de réservation Sexcape Room fabriqué par un cron portera donc `linstantcle.ch`.** Le
verrou d'hôte n'y peut rien : c'est un défaut de résolution de marque, à traiter en phase 3 avec
l'expéditeur des e-mails, en résolvant la marque depuis la chambre de la réservation et non depuis
l'hôte de la requête — ce que le §4.4 du brief prescrit déjà pour l'expéditeur.

---

## 5. Ce qui n'est pas dans le périmètre, et pourquoi

| Vue | Statut | Preuve |
|---|---|---|
| `roomslist` | **aucune page.** Enregistrement 3, `post_id=0` ; aucune page ne porte le shortcode | balayage de `post_content` et de `_elementor_data` |
| `availability` | **deux pages, toutes deux L'Instant Clé** : 1261 (`room_ids="2"`, chambre 2) et 1280 (`room_ids="1"`, chambre 1). Aucune chambre Sexcape Room. Les enregistrements 5 et 6 ont `post_id=0` | `sir_posts`, `sir_vikbooking_wpshortcodes` |
| `packageslist`, `packagedetails` | enregistrement 8 à `post_id=0` ; aucune page | idem |
| `precheckin`, `revstay`, `loginregister`, `quote`, `promotions`, `chat`, `orderslist`, `operators`, `signature`, `tableaux` | aucune page dédiée ; servies sur l'URL de 845 ou de 844 par surcharge de `view` | `vikbooking.php:265` + `input.php:259-265` |
| `1019`, `146`, `6062` (fiches chambres 1, 2, 7) | pages L'Instant Clé. **Hors liste blanche, volontairement** : leur exclusion est l'objet même du verrou | registre des marques, `config/brands.php` |
| `1261`, `1280` | pages de disponibilité L'Instant Clé. Hors liste blanche | idem |
| `2235`, `6270` | brouillons. Hors liste blanche | `post_status = draft` |
| `3653` (`tiny-url`) | doublon non retenu par `best()`, qui s'arrête sur l'enregistrement 9 → 3654 | `admin/models/shortcodes.php:127-160` |

**La chambre 9, `L'Indécent`, n'a aucune page.** Aucun shortcode `roomdetails roomid="9"` n'existe
nulle part. C'est cohérent avec la carte de vérité, qui la donne « ouverte à la vente au
lancement », mais cela veut dire qu'**une entrée devra être ajoutée à la liste blanche le jour où
sa fiche sera créée**. À inscrire dans la recette du chantier D plutôt que de le découvrir en
production.

---

## 6. Ce que le verrou ne fera pas

Deux limites, à écrire noir sur blanc pour qu'on ne compte pas sur lui pour ce qu'il ne fait pas.

**Il ne filtre pas les chambres.** Autoriser la page 6287 sur l'hôte de réservation n'empêche
personne d'y afficher une chambre L'Instant Clé. Vérifié en direct le 12 septembre :

```
https://linstantcle.ch/fr/a-huis-clos/            → rend roomid=10
https://linstantcle.ch/fr/a-huis-clos/?roomid=2   → rend roomid=2   (L'Aparté)
```

C'est la démonstration en production de ce que Q4 du constat de phase 0 avait établi dans le code :
`def()` cède devant un paramètre de requête. L'étanchéité de marque reste entièrement portée par la
couche 2 du §4.3 — la garde sur `vikbooking_before_create_booking_record`. Le verrou d'hôte est un
périmètre, pas un filtre.

**Il ne couvre pas les fichiers PHP servis directement.** `template_redirect` ne se déclenche ni
pour `/wp-login.php`, ni pour `/wp-admin/`, ni pour `/xmlrpc.php`, ni pour `/wp-cron.php`, ni pour
`trp-ajax.php` : ce sont des fichiers physiques, donc `RewriteCond !-f` les sert sans passer par
`index.php`. Ils resteront joignables sur `reservation.sexcaperoom.ch` même après la pose du
verrou PHP. `X-Robots-Tag` posé dans le `.htaccess` les couvrira pour l'indexation ; leur
accessibilité, elle, demande une décision séparée. Voir §8.

---

## 7. Pièges mesurés sur cette installation

**1. `mod_headers`.** Le `.htaccess` en place porte déjà `Header unset Vary` dans un bloc
SiteGround Optimizer, sans garde `<IfModule>`, et le serveur ne renvoie pas d'erreur 500 : le
module est donc chargé. La réserve du brief est levée.

**2. `robots.txt` est un fichier physique, pas une réponse dynamique.**
`www/linstantcle.ch/public_html/robots.txt`, 571 octets, daté du 6 mai. WordPress ne sert donc
**jamais** `/robots.txt` sur cette installation, contrairement à ce que suppose le quatrième piège
du brief. Le fichier actuel autorise tout, sauf quelques chemins WooCommerce et WPForms, et
déclare `Sitemap: https://linstantcle.ch/sitemap_index.xml`. Servir un `robots.txt` propre à
l'hôte de réservation demande donc une règle de réécriture vers un **second fichier physique**,
et la conclusion du brief tient — pour une autre raison que celle qu'il donne.

**3. Le registre des shortcodes est en retard sur les pages, et `matchShortcode` ne le dit pas.**
La page 6287 (À Huis Clos, chambre 10) n'est pas enregistrée. Or `matchShortcode`
(`route.php:301-400`) attribue un point par paramètre concordant et retient le meilleur score,
sans jamais exiger un score minimal. Pour `view=roomdetails&roomid=10`, aucun enregistrement ne
concorde sur `roomid` ; tous concordent sur `view` ; le premier examiné gagne — l'enregistrement 4,
**page 1019, `l-entracte`**. Un lien `roomdetails` vers la chambre 10 construit par Vik pointe donc
vers la fiche L'Instant Clé, avec `?roomid=10` en suffixe. Ce n'est pas le verrou qui crée ce
défaut et ce n'est pas lui qui le corrigera, mais il en change le symptôme : sur l'hôte de
réservation, ce lien tombera sur une page hors liste blanche et partira en 301 vers
sexcaperoom.ch. **Le correctif propre est d'enregistrer la page 6287 dans l'écran Shortcodes de
Vik** — un geste de Thomas, à porter dans `journal-vik.md`.

**4. Le cache dynamique SiteGround partage une clé entre les deux hôtes — à vérifier avant
d'ouvrir.** Les deux hôtes renvoient le même en-tête `host-header:
6b7412fb82ca5edfd0917e3957f05d89`. Cette valeur identifie la racine, que les deux hôtes partagent.
Si la clé de cache NGINX ne comprend pas le `Host`, une page mise en cache pour un hôte peut être
servie pour l'autre — exactement le scénario que le §4.6 du brief redoute. Les deux réponses
mesurées portaient `x-proxy-cache: MISS` et `Cache-Control: no-store`, donc rien n'était en cache
au moment du relevé : **la question reste ouverte et se vérifie en forçant une mise en cache, pas
en relisant des en-têtes.** À noter par ailleurs : `x-nitro-cache` n'apparaît **que** sur
linstantcle.ch. NitroPack ne traite pas encore l'hôte de réservation.

**5. `redirect_canonical` doit être neutralisé sur l'hôte, comme il l'est pour l'hôte d'API.**
Sans cela, WordPress redirige les formes non canoniques vers la forme canonique — construite sur
`option_home`, donc sur le bon hôte une fois `lme-brands` en place, mais sur linstantcle.ch avant.
`api-host.php` donne le patron exact.

---

## 8. Ce que je n'ai pas pu établir

**1. La page 1965 fait-elle partie du tunnel Sexcape Room ?** Elle porte le même shortcode que 844
et fonctionne aussi bien. Rien dans le code ni dans les données ne dit laquelle des deux sera
l'entrée. Le choix appartient au chantier D, qui construira le lien depuis sexcaperoom.ch.
**Recommandation :** ouvrir les deux. Le coût d'une entrée de trop est nul ici, les deux pages
étant des vues du même moteur ; le coût d'une entrée manquante est un tunnel cassé.

**2. Faut-il ouvrir les trois préfixes de langue, ou seulement `/fr/` ?** Le registre des marques
donne `'languages' => array('fr')` pour Sexcape Room, mais ce champ décrit la langue du contenu,
pas le routage. TranslatePress publie les trois, et `de-CH` est la deuxième langue réelle du
moteur avec 392 réservations. **Recommandation : ouvrir les trois préfixes**, et traiter la
restriction linguistique comme un sujet de contenu, au chantier D. Décision de Thomas.

**3. `/wp-login.php` et `/wp-admin/` doivent-ils rester joignables sur l'hôte de réservation ?**
Techniquement ils le resteront, le verrou PHP ne pouvant pas les voir (§6). Les fermer demande une
règle `.htaccess` conditionnée à l'hôte, avec le risque de la mal conditionner et de fermer
l'administration de linstantcle.ch — précisément le risque que le point 4 du brief désigne comme
le vrai danger du chantier. **Je ne tranche pas** : c'est un arbitrage entre une fuite de surface
et une panne d'administration. Si la décision est de les fermer, le faire **après** les six points
de recette, jamais dans le même geste.

**4. Le comportement exact du parcours au-delà de `task=showprc`.** Le relevé s'est arrêté avant
`task=saveorder` pour ne créer aucune réservation. La conclusion « tout le parcours reste sur
l'URL de 844 » repose sur la lecture du code (`controller.php:47-72`, actions des formulaires dans
`showprc` et `oconfirm`), pas sur une mesure de bout en bout. **Elle se vérifie au point 6 de la
recette du brief**, sur la chambre de test 5 et en paiement hors ligne, comme le prévoit déjà le
§5 du brief principal.

**5. Qui produit les 759 liens courts.** Le mécanisme est actif et récent, mais aucun gabarit
d'e-mail ne porte `{booking_link}`. Trois appelants possibles ont été identifiés dans le code sans
que je détermine lequel s'exécute. Sans effet sur la liste — l'entrée A6 est retenue de toute
façon — mais à éclaircir en phase 3, parce que le même appelant fabriquera les liens Sexcape Room
et les fabriquera, aujourd'hui, avec l'hôte L'Instant Clé (§4.G).

**6. L'effet réel du cache partagé.** Voir §7, piège 4. Mesurable, non mesuré, et non mesurable
sans provoquer une mise en cache sur un site en production.

---

## Récapitulatif, pour le registre des marques

Ce que la clé `sexcaperoom` du registre doit porter, sous une forme qui reste à arrêter par V2 :

| Identifiant de page | Rôle | Écrans |
|---|---|---|
| `844` | entrée, recherche, résultats, options, coordonnées | 1, 2, 4, 5 |
| `845` | détail, paiement, retour Stripe, confirmation, erreur | 6, 7 |
| `6287` | fiche À Huis Clos (chambre 10) | 3 |
| `4209` | fiche Le Boudoir du Désir (chambre 4) | 3 |
| `3654` | redirecteur de liens courts | — |
| `1965` | seconde entrée | 1 — **à confirmer, §8.1** |
| *(à créer)* | fiche L'Indécent (chambre 9) | 3 — **n'existe pas encore, §5** |

Hors registre, parce que le mécanisme ne les voit pas passer : `/wp-admin/admin-ajax.php`,
`trp-ajax.php`, les ressources statiques, `/.well-known/`.

Ces identifiants sont des données de configuration, pas du code : leur place est
`mu-plugins/lme-brands/config/brands.php`, au même titre que `host` et `rooms`.
