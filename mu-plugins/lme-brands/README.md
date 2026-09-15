# lme-brands

Phase 1 du brief `docs/briefs/sexcape-room-reservation.md` : le registre des marques, la résolution de marque depuis l'identifiant de chambre, la réécriture d'URL consciente de l'hôte, l'écran de santé, et la journalisation.

Phase 2 : le filtrage des chambres par marque — chapitre 4.3 du brief, couche de présentation puis couche de garde. Rien d'autre — ni e-mails (phase 3), ni paiement (phase 4).

Déployé sous `wp-content/mu-plugins/`. Ce dossier même n'est pas chargé automatiquement par WordPress : c'est `mu-plugins/lme-brands.php`, à la racine de `mu-plugins/`, qui pointe dessus.

---

## Fichiers

```
mu-plugins/
  lme-brands.php                  point d'entrée chargé par WordPress
  lme-brands/
    lme-brands.php                bootstrap : ordre de chargement
    config/
      brands.php                  le registre — données seulement
    includes/
      core.php                    logique pure, sans WordPress, testable en ligne de commande
      logger.php                  journalisation et alerte, chapitre 6
      registry.php                chargement du registre, résolution avec journalisation
      url-rewrite.php             réécriture d'URL par hôte, chapitre 4.1
      room-filter.php             filtrage des chambres, couche de présentation, chapitre 4.3
      booking-guard.php           filtrage des chambres, couche de garde, chapitre 4.3
      health-screen.php           écran de santé en administration, chapitre 4.2
    tests/
      test-core.php               tests automatisés de includes/core.php
    README.md                     ce fichier
```

`includes/core.php` n'appelle aucune fonction WordPress. C'est délibéré : c'est le seul moyen de tester la résolution de marque et la réécriture d'hôte sans base de données ni site WordPress, avec `php` seul.

---

## Le registre, `config/brands.php`

Données seulement, filtrable sans éditer le fichier :

```php
add_filter( 'lme_brands', function ( array $config ) {
    // ... modifier $config ...
    return $config;
} );
```

Forme exacte documentée en tête de `config/brands.php`. Résumé :

- `brands[clé]` : `label`, `host`, `sender_email`, `sender_name`, `reply_to`, `signature`, `confirmation_page_id`, `languages`.
- `rooms[id Vik]` : `brand`, `name`, `experience`, `forfait` (`null` ou chaîne), `availability_group` (`null` ou chaîne).

**Décision de Thomas, 14 septembre 2026** (`docs/briefs/brief-correctif-phase-2-filtrage.md`, chapitre 2) : toute chambre active dans Vik porte une marque, sans exception ; une chambre désactivée dans Vik (`avail = 0`) n'est servie nulle part. Il n'y a plus de statut « exclue » ni de seconde liste à tenir à la main — `avail` vit dans Vik (`sir_vikbooking_rooms.avail`), pas dans le registre. Les chambres de test 5 et 6 sont donc des entrées `rooms` ordinaires, désactivées côté Vik.

**Le point délicat, la chambre 7.** `experience` vaut `"L'Entracte"` pour les chambres 1 et 7 — c'est ce qui permet à l'ingestion Airtable de les rattacher au même enregistrement `Expériences`. `name` distingue la chambre elle-même : `"L'Entracte"` pour la 1, `"L'Entracte all inclusive"` pour la 7. `forfait` porte `'all inclusive'` sur la 7 seule. Ne jamais fusionner ces trois champs.

**Pourquoi un `host` approximatif pour `linstantcle` ne casse rien.** La réécriture d'URL (`url-rewrite.php`) ne s'active que si l'hôte de la requête correspond *exactement* à un `host` du registre, et `lme_brands_swap_url_host()` ne fait rien si l'URL est déjà sur le bon hôte. Si `linstantcle.ch` dans le registre ne correspond pas exactement à l'option `home` réelle du site (ex. `www.linstantcle.ch`), le pire résultat est : aucune réécriture ne se déclenche sur cet hôte, ce qui est déjà le comportement normal de WordPress. Aucun risque de rediriger le site principal vers le mauvais hôte.

---

## Résolution de marque

```php
$resolved = lme_brands_resolve_room_or_log( $room_id );

switch ( $resolved['status'] ) {
    case 'ok':
        // $resolved['brand_key'], $resolved['brand'], $resolved['name'],
        // $resolved['experience'], $resolved['forfait'], $resolved['availability_group'].
        break;
    case 'unknown':
        // Chambre absente du registre. Toujours journalisé en erreur et alerté.
        break;
}
```

Deux issues seulement, plus de statut « exclue » (chapitre 2 du brief-correctif-phase-2-filtrage.md). Le fait qu'une chambre soit désactivée dans Vik (`avail = 0`) est une question distincte de sa marque : voir `lme_brands_room_is_available()` dans `booking-guard.php`.

Toujours passer par `lme_brands_resolve_room_or_log()` (dans `registry.php`), jamais directement par `lme_brands_resolve_room()` (dans `core.php`, pur, sans journalisation) hors des tests : c'est ce qui garantit que le chapitre 6 du brief est respecté à chaque appel.

---

## Journalisation et alerte, chapitre 6

`lme_brands_log( $level, $code, $message, $context )` :

- `$level` : `'warning'` ou `'error'` ; toute autre valeur est journalisée comme erreur, avec le niveau reçu conservé dans le contexte — aucun échec silencieux, y compris ici.
- Toujours écrit dans `error_log()`, préfixe `[lme-brands]`.
- En plus, si `$level === 'error'` : déclenche l'action `lme_brands_alert( $level, $code, $message, $context )`, avec une limitation de débit par `$code` (`LME_BRANDS_ALERT_WINDOW`, 15 minutes par défaut, stockée dans l'option `lme_brands_alert_state`).

**Le transport de l'alerte n'est pas dans ce plugin.** Câbler `lme_brands_alert` vers Telegram (ou autre chose) demande un jeton, donc un secret : ce geste se fait sur le serveur, par Thomas, jamais versionné (règle absolue n°2 de `CLAUDE.md`). Exemple, à poser ailleurs (mu-plugin séparé, ou `wp-config.php`) :

```php
add_action( 'lme_brands_alert', function ( $level, $code, $message, $context ) {
    // Envoyer vers Telegram avec le jeton défini côté serveur.
} );
```

---

## Réécriture d'URL par hôte, chapitre 4.1

Filtres posés au chargement du mu-plugin (donc avant tout thème ou plugin tiers) sur `option_home`, `option_siteurl`, `content_url`, `upload_dir`, `wp_get_attachment_url`. Chaque filtre vérifie, à l'exécution, que :

1. la requête n'est ni en administration, ni en API REST, ni en cron ;
2. l'hôte de la requête correspond exactement à un `host` du registre.

Si l'une des deux conditions manque, la valeur d'origine ressort inchangée.

---

## Filtrage des chambres par marque, chapitre 4.3

Deux couches, parce que Vik n'expose qu'un seul vrai point d'accroche de présentation (`constat-phase-0.md` Q4 : « la couche 1 n'est donc opposable sur aucun écran »).

### Couche de présentation, `includes/room-filter.php`

- **Vue `search`** : filtre natif `vikbooking_apply_search_results_filtering`. Retire une annonce des résultats dès que la chambre n'appartient pas à la marque de l'hôte courant. C'est ce qui fait que la recherche sur `reservation.sexcaperoom.ch` ne retourne que les chambres 4, 9 et 10, et sur `linstantcle.ch` seulement 1, 2, 7 et 8 (critère de recette n°2).
- **Vues `roomdetails`, `availability`, `roomslist`** : aucun hook n'existe (vérifié dans le code). Leur filtrage tient à un attribut de shortcode (`roomid`, `room_ids`, `category_id`) qui est un *défaut* au sens de `JInput::def()` — il cède devant un paramètre GET ou POST de même nom, démontré en production dans `constat-perimetre-tunnel.md` §6 (`linstantcle.ch/fr/a-huis-clos/?roomid=2` rend L'Aparté). La parade : retirer de la requête, avant que le shortcode ne la lise, tout paramètre qui viserait une chambre étrangère à la marque de l'hôte. Une fois retiré, `def()` réapplique le véritable défaut de la page.
  - `roomid` (roomdetails) : comparé directement au registre.
  - `room_ids` (availability) : chaque identifiant de la liste comparé au registre ; un seul étranger fait retirer la liste entière (chapitre 6, aucun échec silencieux — pas de réduction silencieuse).
  - `category_id` (roomslist) : aucune marque n'est attachée à une catégorie Vik dans le registre — on descend au niveau des chambres que `sir_vikbooking_rooms.idcat` range dans cette catégorie, et on applique la même règle.

**Cette couche est un confort, pas une garantie.** Elle rend une page cohérente avec la marque de son hôte quand un paramètre est fabriqué à la main ; elle ne corrige pas le défaut *natif* d'une page (un shortcode `roomid="10"` reste `roomid="10"` sur les quatre hôtes qui partagent la même installation — un sujet distinct, celui du verrou d'hôte, `brief-verrou-hote-reservation.md`). Elle journalise en avertissement (`foreign_room_param_stripped`), jamais en erreur : ce n'est pas l'un des trois cas que le chapitre 6 exige d'alerter sans exception.

### Couche de garde, `includes/booking-guard.php`

**Le seul mécanisme réellement opposable.** Greffée sur `vikbooking_before_create_booking_record`, qui se déclenche juste avant l'insertion de la commande. Pour chaque chambre de la réservation en cours de création, dans cet ordre (chapitre 2 du brief-correctif-phase-2-suite.md) :

- chambre désactivée dans Vik (`avail = 0`) : **refusée sans condition, quelle que soit sa marque, y compris sur un hôte qui ne résout vers aucune marque du registre** — journalisée en erreur (`unavailable_room_booking_attempt`). C'est le cas des chambres de test 5 et 6 en dehors de leur fenêtre de test (chapitre 5 du brief-correctif-phase-2-filtrage.md). Ce refus précède la résolution de marque : une chambre désactivée l'est partout, cette décision ne demande aucune marque pour être prise ;
- chambre active, marque de l'hôte : autorisée ;
- chambre active, autre marque : **refusée**, journalisée en erreur et alertée (`foreign_room_booking_attempt` — le troisième cas que le chapitre 6 exige d'alerter sans exception) ;
- chambre absente du registre : **refusée**, déjà journalisée en erreur et alertée par `lme_brands_resolve_room_or_log()` (`unknown_room` — le premier cas du chapitre 6).

Ces trois derniers cas ne s'appliquent que si l'hôte résout vers une marque : sur un hôte qui n'est celui d'aucune marque du registre (staging, accès direct par IP), seul le refus sur `avail = 0` reste opposable, faute de contexte de marque à faire respecter — et on ne devine jamais une marque.

Le message et le titre de refus sortent dans la langue de la marque de l'hôte courant (première langue déclarée dans `languages`), pas systématiquement en français : voir `lme_brands_rejection_strings()`, chapitre 7 du brief-correctif-phase-2-filtrage.md. Sur un hôte sans marque, le message se replie sur le français.

Refuser arrête la requête sur place (`wp_die()`, réponse 403) : `vikbooking_before_create_booking_record` est un `do_action_ref_array` ordinaire (`constat-phase-0.md` Q2), il ne peut pas annuler l'insertion par une valeur de retour. Sans cet arrêt immédiat, le contrôleur de Vik atteindrait `$dbo->insertObject()` quelques lignes plus bas, qu'on le veuille ou non.

---

## Écran de santé, chapitre 4.2

Administration → Outils → **Santé lme-brands**. Compare les identifiants du registre (`rooms`) à `{prefixe}vikbooking_rooms`. Trois sections : chambres présentes dans Vik et absentes du registre, chambres présentes dans le registre et absentes de Vik, chambres synchronisées.

Purement visuel : ne journalise rien à chaque affichage. La journalisation en erreur a lieu au moment d'une résolution réelle, pas à la lecture de cet écran.

---

## Tests

`includes/core.php` est pur : aucun appel WordPress. Les tests s'exécutent avec `php` seul, sans site WordPress, sans base de données, sans PHPUnit :

```bash
php mu-plugins/lme-brands/tests/test-core.php
```

Sortie : une ligne par test, un total, et un code de sortie non nul si un test échoue. Le dernier bloc de tests recharge `config/brands.php` lui-même et vérifie qu'il est valide et fidèle à la carte de vérité du chapitre 2 — une régression dans le fichier de configuration réel casse ces tests, pas seulement les tests sur un registre d'exemple.

**Aucun `php` n'était disponible sur ce poste au moment d'écrire ce plugin, ni encore au moment d'écrire la phase 2** : ces tests n'ont donc pas pu être exécutés ici, sur aucune des deux phases. La phase 2 ajoute `lme_brands_parse_id_list()`, `lme_brands_extract_room_ids()` et `lme_brands_room_ids_matching_category()` à `includes/core.php`, chacune couverte par de nouveaux tests, et n'avait pas pu vérifier davantage que l'équilibrage des accolades et des parenthèses (`grep -o` de chaque fichier, à défaut d'un interpréteur).

`php` est disponible depuis le correctif de phase 2, suite (15 septembre 2026) : les 52 tests passent, y compris ceux d'`evaluate_booking_room()`, laissée inchangée par ce correctif — seul l'ordre dans `lme_brands_guard_booking_record()`, non couverte par ces tests car elle appelle des fonctions WordPress, a changé.

`includes/logger.php`, `includes/registry.php`, `includes/url-rewrite.php`, `includes/room-filter.php`, `includes/booking-guard.php` et `includes/health-screen.php` appellent des fonctions WordPress (`get_option`, `add_filter`, `add_action`, `$wpdb`, `is_admin`, `wp_die`...) et n'ont pas d'équivalent testable hors WordPress dans ce dépôt. Les vérifier suit les procédures manuelles ci-dessous, sur `staging10.linstantcle.ch` de préférence, jamais en production.

---

## Procédures de vérification manuelle

### 1. Le registre se charge et résout correctement

Dans une console WP-CLI (`wp eval '...'`) ou un extrait temporaire non versionné chargé en administration :

```php
var_dump( lme_brands_resolve_room_or_log( 10 ) );  // attendu : status 'ok', brand_key 'sexcaperoom'
var_dump( lme_brands_resolve_room_or_log( 7 ) );   // attendu : experience "L'Entracte", forfait 'all inclusive'
var_dump( lme_brands_resolve_room_or_log( 5 ) );   // attendu : status 'ok', brand_key 'linstantcle' (chambre de test, désactivée dans Vik mais déclarée dans le registre)
var_dump( lme_brands_resolve_room_or_log( 3 ) );   // attendu : status 'unknown' (chambre 3 n'existe pas, cf. constat Q3)
```

Vérifier dans `wp-content/debug.log` (avec `WP_DEBUG_LOG` actif) :
- rien pour la chambre 10, ni pour la chambre 5 (`resolve_room_or_log` ne journalise plus que le cas `unknown`) ;
- une ligne `[lme-brands] [ERROR] [unknown_room]` pour la chambre 3.

### 2. Un registre cassé ne plante pas le site

Modifier temporairement `config/brands.php` pour faire pointer une chambre vers une marque inexistante (`'brand' => 'x'`). Recharger n'importe quelle page front. Vérifier :
- le site continue de fonctionner (aucune erreur fatale) ;
- une ligne `[lme-brands] [ERROR] [config_invalid]` apparaît dans `debug.log` ;
- `lme_brands_get_config()` retourne un registre vide (`brands => []`, `rooms => []`) tant que l'erreur n'est pas corrigée.

Remettre le fichier en état, vérifier que l'erreur disparaît.

### 3. Réécriture d'URL par hôte

À faire une fois `reservation.sexcaperoom.ch` en place (chantier C1) ou via une entrée `/etc/hosts` de test pointant vers `gfram1004.siteground.biz` :

- Visiter une page du tunnel sous `reservation.sexcaperoom.ch`, afficher le code source : aucune URL ne doit porter `linstantcle.ch`.
- Visiter `/wp-admin/` sous ce même hôte : les liens d'administration doivent rester cohérents, pas de redirection cassée. C'est le test du garde-fou « jamais en administration ».
- Vérifier l'URL d'une image de la médiathèque sur une page du tunnel : elle doit porter l'hôte de la marque active.
- Revenir sur `linstantcle.ch` : vérifier qu'aucune URL n'a changé par rapport au comportement avant l'installation du plugin.

### 4. Écran de santé

- Outils → Santé lme-brands : les 7 chambres vendues doivent apparaître en « synchronisées ».
- Retirer temporairement la chambre 9 de `config/brands.php`, recharger l'écran : elle doit apparaître sous « présentes dans Vik, absentes du registre ». Remettre la ligne.
- Ajouter temporairement dans `rooms` une chambre fictive (ex. `999`) qui n'existe pas dans `sir_vikbooking_rooms`, recharger : elle doit apparaître sous « présentes dans le registre, absentes de Vik ». Retirer ensuite.

### 5. Journalisation et limitation de débit

Dans un extrait temporaire non versionné :

```php
add_action( 'lme_brands_alert', function ( $level, $code, $message, $context ) {
    error_log( "ALERTE TEST : {$level} {$code} {$message}" );
} );
```

- Appeler `lme_brands_resolve_room_or_log( 3 )` deux fois de suite : une seule ligne `ALERTE TEST` doit apparaître (limitation de débit), mais `debug.log` doit contenir deux lignes `[lme-brands] [ERROR]`.
- Réduire temporairement `LME_BRANDS_ALERT_WINDOW` (ou attendre 15 minutes), rappeler la fonction : une nouvelle `ALERTE TEST` doit partir.

### 6. Couche de présentation, vue `search` (critère de recette n°2)

À faire une fois `reservation.sexcaperoom.ch` en place, sur `staging10.linstantcle.ch` de préférence :

- Sur `reservation.sexcaperoom.ch`, effectuer une recherche de disponibilité depuis la page 844 (`book-now` / `reserver`) : les résultats doivent porter exactement les chambres 4, 9 et 10 (une fois la fiche de la chambre 9 créée, chantier D) — jamais 1, 2, 7 ni 8.
- Sur `linstantcle.ch`, même recherche, mêmes dates : les résultats doivent porter exactement 1, 2, 7 et 8 — jamais 4, 9 ni 10.
- Vérifier `debug.log` : rien de nouveau ne doit apparaître pour ces chambres retirées. Une chambre d'une autre marque n'est pas une anomalie, ce n'est pas journalisé.

### 7. Couche de présentation, vues sans hook natif

Sur `reservation.sexcaperoom.ch` :

- Visiter une page `roomdetails` de la marque (ex. la fiche du Boudoir du Désir, chambre 4) en ajoutant `?roomid=1` à l'URL : la page doit continuer à montrer la chambre 4, jamais la chambre 1. Vérifier une ligne `[lme-brands] [WARNING] [foreign_room_param_stripped]` dans `debug.log`, portant `room_id: 1`.
- Même test avec `?room_ids[]=1&room_ids[]=4` sur une page `availability` : le paramètre entier doit être ignoré (retombée sur le défaut de la page), pas seulement l'identifiant 1.
- Même test avec `?category_id=<identifiant d'une catégorie Vik qui contient une chambre L'Instant Clé>` sur une page `roomslist`.
- Sur `linstantcle.ch`, refaire les trois essais avec des identifiants Sexcape Room (4, 9, 10) : même comportement, dans l'autre sens.
- Contre-épreuve : sur chacun des deux hôtes, un paramètre qui vise une chambre de la **bonne** marque ne doit rien retirer et ne rien journaliser.

### 8. Couche de garde — critère de recette n°3, celui qui doit être vérifiable de bout en bout

Sur `staging10.linstantcle.ch`, jamais en production, avec un mode de paiement hors ligne pour ne rien encaisser :

1. Depuis l'hôte `reservation.sexcaperoom.ch`, construire à la main une requête de réservation (`task=saveorder`) portant la chambre 1 — par exemple en modifiant le champ caché `roomid` du formulaire dans les outils de développement du navigateur avant l'envoi, ou en rejouant la requête `oconfirm` → `saveorder` avec un client HTTP.
2. Vérifier :
   - la réponse est l'écran « Réservation refusée » (403), en français — c'est la langue de Sexcape Room (`languages => array( 'fr' )`) —, pas la page de confirmation ;
   - `debug.log` porte une ligne `[lme-brands] [ERROR] [foreign_room_booking_attempt]`, avec `resolved_brand: linstantcle` et `expected_brand: sexcaperoom` dans le contexte ;
   - l'action `lme_brands_alert` s'est déclenchée (crochet du point 5 ci-dessus) ;
   - **aucune ligne n'a été ajoutée dans `sir_vikbooking_orders`** pour cette tentative — vérifiable par Code, en lecture seule, par requête MySQL sur les commandes les plus récentes.
3. Répéter dans l'autre sens : depuis `linstantcle.ch`, tenter la chambre 10. Même vérifications, sauf la langue : l'écran de refus sort cette fois en anglais, « Booking refused » — c'est la première langue déclarée pour L'Instant Clé (`languages => array( 'en', 'fr' )`), critère de recette n°6 du brief-correctif-phase-2-filtrage.md.
4. Sans rien changer dans Vik, tenter de réserver la chambre de test 5 (ou 6) depuis son propre hôte de marque (linstantcle.ch pour la 5, reservation.sexcaperoom.ch pour la 6) : la réservation doit être **refusée**, `debug.log` doit porter une ligne `[lme-brands] [ERROR] [unavailable_room_booking_attempt]`, et l'alerte doit se déclencher — la chambre est désactivée (`avail = 0`) en dehors de sa fenêtre de test E5.
5. Activer temporairement la chambre 5 dans Vik (`avail = 1`), réserver depuis `linstantcle.ch` : la réservation doit aboutir normalement, sans refus. Désactiver la chambre aussitôt après, **y compris si le test précédent avait échoué** (chapitre 5 du brief-correctif-phase-2-filtrage.md : aucun chemin d'échec ne doit laisser la chambre active).
6. Réserver une chambre légitime pour chaque hôte (ex. chambre 8 sur `linstantcle.ch`, chambre 4 sur `reservation.sexcaperoom.ch`) : la réservation doit aboutir normalement, sans qu'aucune ligne `lme-brands` n'apparaisse dans `debug.log`.
7. Sur `staging10.linstantcle.ch` lui-même — hôte qui ne résout vers aucune marque du registre (chapitre 2 du brief-correctif-phase-2-suite.md) — tenter de réserver la chambre de test 5 (ou 6), désactivée hors de sa fenêtre de test : la réservation doit être **refusée**, `debug.log` doit porter une ligne `[lme-brands] [ERROR] [unavailable_room_booking_attempt]`, et le message de refus sort en français (repli, faute de marque à résoudre). Réserver ensuite une chambre active quelconque depuis ce même hôte : la réservation doit aboutir normalement — l'absence de marque ne bloque que les chambres désactivées, rien d'autre.

---

## Retour arrière

Retirer `mu-plugins/lme-brands.php` (ou tout le dossier `mu-plugins/lme-brands/`) du serveur restaure le comportement d'avant ce plugin : aucun fichier de Vik, du thème parent ou d'un plugin tiers n'est modifié.
