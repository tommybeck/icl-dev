# lme-brands

Phase 1 du brief `docs/briefs/sexcape-room-reservation.md` : le registre des marques, la résolution de marque depuis l'identifiant de chambre, la réécriture d'URL consciente de l'hôte, l'écran de santé, et la journalisation. Rien d'autre — ni filtrage de chambres (phase 2), ni e-mails (phase 3), ni paiement (phase 4).

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
- `excluded_room_ids` : identifiants des chambres de test (5 et 6), jamais résolues vers une marque, mais reconnues par l'écran de santé pour ne pas être signalées comme anomalie.

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
    case 'excluded':
        // Chambre de test (5 ou 6). Journalisé en avertissement, jamais en erreur.
        break;
    case 'unknown':
        // Chambre absente du registre. Toujours journalisé en erreur et alerté.
        break;
}
```

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

## Écran de santé, chapitre 4.2

Administration → Outils → **Santé lme-brands**. Compare les identifiants du registre (`rooms` + `excluded_room_ids`) à `{prefixe}vikbooking_rooms`. Trois sections : chambres présentes dans Vik et absentes du registre, chambres présentes dans le registre et absentes de Vik, chambres synchronisées.

Purement visuel : ne journalise rien à chaque affichage. La journalisation en erreur a lieu au moment d'une résolution réelle, pas à la lecture de cet écran.

---

## Tests

`includes/core.php` est pur : aucun appel WordPress. Les tests s'exécutent avec `php` seul, sans site WordPress, sans base de données, sans PHPUnit :

```bash
php mu-plugins/lme-brands/tests/test-core.php
```

Sortie : une ligne par test, un total, et un code de sortie non nul si un test échoue. Le dernier bloc de tests recharge `config/brands.php` lui-même et vérifie qu'il est valide et fidèle à la carte de vérité du chapitre 2 — une régression dans le fichier de configuration réel casse ces tests, pas seulement les tests sur un registre d'exemple.

**Aucun `php` n'était disponible sur ce poste au moment d'écrire ce plugin** : ces tests n'ont donc pas pu être exécutés ici. Les faire tourner une première fois avant la phase 2 fait partie de la recette de cette phase 1.

`includes/logger.php`, `includes/registry.php`, `includes/url-rewrite.php` et `includes/health-screen.php` appellent des fonctions WordPress (`get_option`, `add_filter`, `$wpdb`, `is_admin`...) et n'ont pas d'équivalent testable hors WordPress dans ce dépôt. Les vérifier suit les procédures manuelles ci-dessous, sur `staging10.linstantcle.ch` de préférence, jamais en production.

---

## Procédures de vérification manuelle

### 1. Le registre se charge et résout correctement

Dans une console WP-CLI (`wp eval '...'`) ou un extrait temporaire non versionné chargé en administration :

```php
var_dump( lme_brands_resolve_room_or_log( 10 ) );  // attendu : status 'ok', brand_key 'sexcaperoom'
var_dump( lme_brands_resolve_room_or_log( 7 ) );   // attendu : experience "L'Entracte", forfait 'all inclusive'
var_dump( lme_brands_resolve_room_or_log( 5 ) );   // attendu : status 'excluded'
var_dump( lme_brands_resolve_room_or_log( 3 ) );   // attendu : status 'unknown' (chambre 3 n'existe pas, cf. constat Q3)
```

Vérifier dans `wp-content/debug.log` (avec `WP_DEBUG_LOG` actif) :
- rien pour la chambre 10 ;
- une ligne `[lme-brands] [WARNING] [excluded_room]` pour la chambre 5 ;
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

---

## Retour arrière

Retirer `mu-plugins/lme-brands.php` (ou tout le dossier `mu-plugins/lme-brands/`) du serveur restaure le comportement d'avant ce plugin : aucun fichier de Vik, du thème parent ou d'un plugin tiers n'est modifié.
