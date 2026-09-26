# lme-brands

Phase 1 du brief `docs/briefs/sexcape-room-reservation.md` : le registre des marques, la résolution de marque depuis l'identifiant de chambre, la réécriture d'URL consciente de l'hôte, l'écran de santé, et la journalisation.

Phase 2 : le filtrage des chambres par marque — chapitre 4.3 du brief, couche de présentation puis couche de garde.

Phase 3 : les e-mails par marque — chapitre 4.4, réécriture en vol du message client et de sa pièce jointe iCal.

Chantier B5 : la source des rappels avant séjour — second point d'accroche, `vikbooking_before_send_mail`, qui voit passer tous les e-mails de Vik. Il pose l'expéditeur, le nom affiché et, depuis la décision de Thomas du 26 septembre 2026, l'adresse de réponse de la marque — **et rien d'autre** : le contenu du rappel vit dans le gabarit de la tâche planifiée, côté Vik, et c'est le chantier F. Détail dans `docs/briefs/constat-b5-rappels.md`.

Phase 4 : le paiement par marque — chapitre 4.5, métadonnée de marque sur la session Stripe, correction défensive de l'URL de retour, page de confirmation partagée. **Ne couvre pas** la réserve de paiement (commande restée en `standby` après un encaissement, `docs/briefs/constat-reserve-paiement.md`) : c'est le chantier B6, elle attend une décision de Thomas.

Chantier B8 : le levier de préproduction — `LME_BRANDS_HOST_OVERRIDE`, `includes/core.php` et `includes/registry.php`. Sans lui, `staging10.linstantcle.ch` ne résout vers aucune marque et la préproduction ne peut exercer aucun chemin Sexcape Room. Voir la section dédiée plus bas et `docs/briefs/constat-deploiement-moteur.md` pour l'inventaire de déploiement.

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
      mail-brand.php              e-mails par marque, chapitre 4.4, et source des rappels, chantier B5
      payment-brand.php           paiement par marque, chapitre 4.5
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

- `neutral` : `label`, `sender_name`, `sender_email` (ou `null`), `reply_to` (ou `null`), `subject` par langue. **Obligatoire** depuis la phase 3 : sans identité neutre, une marque indéterminée n'aurait rien à porter et le message partirait sous l'expéditeur global de l'installation, donc peut-être sous la mauvaise marque.
- `brands[clé]` : `label`, `host`, `sender_email`, `sender_name`, `reply_to`, `signature`, `confirmation_page_id`, `languages`, plus les blocs optionnels `appearance` (chantier D) et `mail` (phase 3 : `subject` par langue, `replacements`, `signature_html`).
- `rooms[id Vik]` : `brand`, `name`, `experience`, `forfait` (`null` ou chaîne), `availability_group` (`null` ou chaîne).

**Décision de Thomas, 14 septembre 2026** (`docs/briefs/brief-correctif-phase-2-filtrage.md`, chapitre 2) : toute chambre active dans Vik porte une marque, sans exception ; une chambre désactivée dans Vik (`avail = 0`) n'est servie nulle part. Il n'y a plus de statut « exclue » ni de seconde liste à tenir à la main — `avail` vit dans Vik (`sir_vikbooking_rooms.avail`), pas dans le registre. Les chambres de test 5 et 6 sont donc des entrées `rooms` ordinaires, désactivées côté Vik.

**Le point délicat, la chambre 7.** `experience` vaut `"L'Entracte"` pour les chambres 1 et 7 — c'est ce qui permet à l'ingestion Airtable de les rattacher au même enregistrement `Expériences`. `name` distingue la chambre elle-même : `"L'Entracte"` pour la 1, `"L'Entracte all inclusive"` pour la 7. `forfait` porte `'all inclusive'` sur la 7 seule. Ne jamais fusionner ces trois champs.

**Le `host` de `linstantcle` est vérifié depuis le 15 septembre 2026** : `sir_options` porte `home` = `siteurl` = `https://linstantcle.ch`, la valeur du registre est donc exacte. Le paragraphe ci-dessous garde sa valeur pour toute marque ajoutée plus tard.

**Pourquoi un `host` approximatif ne casserait de toute façon rien.** La réécriture d'URL (`url-rewrite.php`) ne s'active que si l'hôte de la requête correspond *exactement* à un `host` du registre, et `lme_brands_swap_url_host()` ne fait rien si l'URL est déjà sur le bon hôte. Si `linstantcle.ch` dans le registre ne correspond pas exactement à l'option `home` réelle du site (ex. `www.linstantcle.ch`), le pire résultat est : aucune réécriture ne se déclenche sur cet hôte, ce qui est déjà le comportement normal de WordPress. Aucun risque de rediriger le site principal vers le mauvais hôte.

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

## Levier de préproduction, chantier B8

`staging10.linstantcle.ch` a un inode distinct de linstantcle.ch (`constat-perimetre-tunnel.md`) : c'est une installation à part, mais son hôte n'est celui d'aucune marque du registre, et c'est voulu (« on ne devine jamais une marque »). Sans levier, la préproduction ne peut donc exercer aucun chemin Sexcape Room — ni la présentation, ni la garde, ni l'habillage.

`LME_BRANDS_HOST_OVERRIDE` force l'hôte que **toute** la résolution de marque utilise, sans toucher au registre :

```php
// wp-config.php de staging10.linstantcle.ch seulement, jamais ailleurs.
define( 'LME_BRANDS_HOST_OVERRIDE', 'reservation.sexcaperoom.ch' );
```

Trois garanties, vérifiées par les tests purs de `lme_brands_resolve_effective_http_host()` (`includes/core.php`) :

- **inerte hors préproduction.** La constante n'a d'effet que si `wp_get_environment_type()` vaut exactement `'staging'` — jamais en production, jamais en local. Une même ligne oubliée dans le mauvais `wp-config.php` ne fait rien.
- **jamais depuis une requête.** La valeur vient uniquement de la constante PHP, jamais de `$_GET`, `$_POST` ni d'un en-tête : un visiteur ne peut pas la déclencher lui-même.
- **le registre ne change pas.** Le levier ne fait que choisir quel hôte `lme_brands_resolve_brand_by_host()` reçoit ensuite. Un hôte de substitution qui n'est celui d'aucune marque continue de ne résoudre aucune marque, exactement comme un hôte de requête inconnu.

Chaque emploi du levier journalise une ligne `[lme-brands] [WARNING] [host_override_used]` — une seule par requête, `lme_brands_current_http_host()` la mettant en cache pour la durée de la requête. Ce n'est pas une alerte (chapitre 6 : les trois cas qui alertent sans exception restent chambre inconnue, marque indéterminée à l'envoi, chambre étrangère à l'hôte) : le levier actif en préproduction est un fonctionnement attendu pendant la recette de B8, pas une anomalie.

Pour basculer entre les deux marques pendant la recette, changer la valeur de la constante et enregistrer `wp-config.php` — aucun redéploiement de code.

---

## Réécriture d'URL par hôte, chapitre 4.1

Filtres posés au chargement du mu-plugin (donc avant tout thème ou plugin tiers) sur `option_home`, `option_siteurl`, `content_url`, `upload_dir`, `wp_get_attachment_url`. Chaque filtre vérifie, à l'exécution, que :

1. la requête n'est ni en administration, ni en API REST, ni en cron ;
2. l'hôte de la requête correspond exactement à un `host` du registre.

Si l'une des deux conditions manque, la valeur d'origine ressort inchangée.

---

## Filtrage des chambres par marque, chapitre 4.3

Deux couches, parce que Vik n'expose qu'un seul filtre de présentation (`constat-phase-0.md` Q4 : « la couche 1 n'est donc opposable sur aucun écran »).

### Couche de présentation, `includes/room-filter.php`

- **Vue `search`** : filtre natif `vikbooking_apply_search_results_filtering`. Retire une annonce des résultats dès que la chambre n'appartient pas à la marque de l'hôte courant. C'est ce qui fait que la recherche sur `reservation.sexcaperoom.ch` ne retourne que les chambres 4, 9 et 10, et sur `linstantcle.ch` seulement 1, 2, 7 et 8 (critère de recette n°2).
- **Vues `roomdetails`, `availability`, `roomslist` et les autres vues joignables par `view`** : aucun filtre. Vik déclenche bien `vikbooking_before_display_<vue>` avant chaque vue (`libraries/adapter/mvc/controller.php:263` ; `constat-phase-0.md` Q4, corrigé le 26 septembre 2026), mais avant que la vue ne construise sa liste de chambres : il ne permet pas de la filtrer. Leur filtrage tient à un attribut de shortcode (`roomid`, `room_ids`, `category_id`) qui est un *défaut* au sens de `JInput::def()` — il cède devant un paramètre GET ou POST de même nom, démontré en production dans `constat-perimetre-tunnel.md` §6 (`linstantcle.ch/fr/a-huis-clos/?roomid=2` rend L'Aparté). La parade : retirer de la requête, avant que Vik ne la lise, tout paramètre qui viserait une chambre étrangère à la marque de l'hôte. Une fois retiré, `def()` réapplique le véritable défaut de la page. **Avant que Vik ne la lise, c'est sur `init`, priorité 1** : Vik ne rend pas sa vue pendant le shortcode mais dès sa propre clôture `init` de priorité 10 (pré-traitement `VIKBOOKING_SITE_PREPROCESS`), qui injecte les attributs du shortcode puis exécute le contrôleur et met le HTML en réserve. Un premier accrochage sur `template_redirect` retirait le paramètre après ce rendu, sans effet sur la page (`docs/briefs/constat-correctif-room-filter.md`, trace d'exécution à l'appui). Un retrait qui arriverait encore après le dispatch de Vik est journalisé en erreur et alerté (`foreign_room_param_stripped_too_late`).
  - `roomid` (roomdetails) : comparé directement au registre.
  - `room_ids` (availability) : chaque identifiant de la liste comparé au registre, lu comme Vik le lit, par son filtre `int` (`2abc` vaut 2, `lme_brands_vik_filter_int()`) ; un seul étranger fait retirer la liste entière (chapitre 6, aucun échec silencieux — pas de réduction silencieuse).
  - `category_id` (roomslist) : aucune marque n'est attachée à une catégorie Vik dans le registre — on descend au niveau des chambres que `sir_vikbooking_rooms.idcat` range dans cette catégorie, et on applique la même règle.
  - `view` ajouté à l'URL, pour les vues qui listent des chambres selon la seule requête : `availability`, `roomslist`, `loginregister`, `searchsuggestions`, `promotions` (`lme_brands_view_room_selection()`, qui reproduit la lecture de chaque vue). Sans sélection, elles listent toutes les chambres actives, les deux marques confondues. La vue demandée n'est gardée que si la sélection restante désigne au moins une chambre et uniquement des chambres de la marque de l'hôte ; sinon `view` est retiré et la page retombe sur la vue de son shortcode (`foreign_view_param_stripped`, avertissement). `promotions` n'est jamais restreinte par la requête : elle est toujours fermée. Aucune page publiée ne porte nativement l'une de ces vues (relevés des 24 et 26 septembre 2026).
  - **AJAX du site** : `admin-ajax.php?action=vikbooking&vik_ajax_client=site&option=com_vikbooking&view=…` rend n'importe quelle vue du site, sans shortcode donc sans défaut. Ce chemin, où `is_admin()` vaut vrai, est filtré par les mêmes règles, et une vue fermée y est refusée en 403 (`foreign_view_ajax_refused`, avertissement), faute de vue de repli. Conséquence voulue : l'appel de `searchsuggestions` par le formulaire « aucun résultat » de Vik n'affiche aucune suggestion quand la recherche ne porte pas une catégorie propre à la marque, au lieu de suggérer les chambres de l'autre marque. Inventaire et mesures : `docs/briefs/constat-vues-vik-par-view.md`.

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

## E-mails par marque, chapitre 4.4, `includes/mail-brand.php`

**On réécrit le message en vol, on n'en émet jamais un second.** Aucun réglage de Vik ne désactive l'e-mail client d'une réservation confirmée, et son hook d'envoi est un `do_action` qui ne retourne rien, donc n'annule rien (`constat-phase-0.md` Q2). Un second envoi produirait deux messages ; la réécriture en produit un, et conserve les pièces jointes iCal de Vik.

**La marque ne vient jamais de l'hôte de la requête.** Un envoi peut partir de l'administration, d'un cron ou d'une reprise de paiement, où il n'y a pas d'hôte de marque à lire. Elle vient de la réservation, donc de ses chambres — que le hook ne transporte pas. `lme_brands_booking_room_ids()` les relit dans `{prefixe}vikbooking_ordersrooms` par `idorder`, en lecture seule, avec un cache de requête.

### Périmètre

Réécriture si, et seulement si :

- le destinataire est le client (`lme_brands_mail_audience()` reproduit la cascade de Vik : adresse littérale, puis `guest`/`customer`, puis `admin`) ;
- la réservation est directe (`channel` vide). Les réservations OTA ne sont pas touchées, conformément au §4.4 — y compris quand elles portent une chambre Sexcape Room. C'est une décision du brief, pas un oubli.

### Une marque, ou aucune

| Issue | Ce qui la produit | Identité posée | Journal |
|---|---|---|---|
| `ok` | toutes les chambres résolvent vers la même marque | celle de la marque | rien |
| `unknown_room` | une chambre absente du registre | **neutre** | `unknown_room` + `mail_brand_undetermined`, alerte |
| `mixed_brands` | la réservation mêle deux marques | **neutre** | `mail_brand_undetermined`, alerte |
| `no_rooms` | aucune chambre lisible | **neutre** | `mail_brand_undetermined`, alerte |

Jamais une marque devinée sur la première chambre venue. `mixed_brands` n'est pas théorique : la garde de réservation l'interdit depuis le tunnel, rien ne l'interdit dans l'administration de Vik.

### Ce qui est réécrit

- **expéditeur et nom affiché** — `setSender()`. Un nom vide, ou identique à l'adresse, fait que `VBOMailWrapper` efface le nom et se replie sur le titre global du site, donc sur l'autre marque (`wrapper.php:182` et `:203`). Le registre refuse cette forme à son chargement, et `mail-brand.php` refait le contrôle à l'envoi plutôt que de poser une identité bancale ;
- **adresse de réponse** — `setReply()`, si le registre en déclare une ;
- **objet** — `mail.subject` de la marque, dans la langue de la réservation (`sir_vikbooking_orders.lang`, ramené à son code primaire). Table vide : l'objet natif de Vik est gardé, ce qui est le bon choix pour la marque dont le titre du site porte déjà le nom ;
- **corps** — `mail.replacements` puis `mail.signature_html`. **Vides pour les deux marques aujourd'hui, et c'est une position, pas un trou** : voir §5 de `docs/briefs/constat-phase-3-emails.md`, qui explique pourquoi le corps se sépare par marque dans les textes conditionnels de Vik et non par substitution de chaînes ;
- **pièce jointe iCal** — hook `vikbooking_before_create_mail_ical`, qui passe son contenu **par référence**. Vik y écrit le titre global du site dans `SUMMARY` et `LOCATION` : sans cela, un client Sexcape Room verrait « L'Instant Clé » dans son agenda.

### Contrôle de fuite

Après réécriture, l'objet et le corps sont fouillés à la recherche du `label`, du `host` et du `sender_email` des **autres** marques du registre — dérivés du registre, jamais tenus dans une seconde liste. Toute trouvaille journalise `mail_brand_leak` en erreur et alerte.

La recherche décode les entités HTML et ramène les apostrophes typographiques à l'apostrophe droite : sans cela, `L&#039;Instant Clé` et `L’Instant Clé` passeraient inaperçus et le contrôle déclarerait propre un message qui ne l'est pas.

C'est un **quatrième cas d'alerte**, ajouté aux trois du chapitre 6 : il constate en production l'échec du critère de recette n°6. Il criera d'abord, tant que le corps du gabarit unique n'est pas séparé par marque, et se taira le jour où il le sera — ce qui en fait la vérification automatique de ce critère.

### Ce que ce fichier ne couvre pas

**Le corps du rappel avant séjour.** Il vit dans le gabarit de la tâche planifiée, saisi dans l'administration de Vik, où le logo et le titre L'Instant Clé sont aujourd'hui inconditionnels. Il se sépare par marque là-bas, par les textes conditionnels natifs : c'est le chantier F, et c'est un geste de Thomas, pas de code.

---

## La source des autres e-mails de Vik, chantier B5, `includes/mail-brand.php`

Second point d'accroche du même fichier : `vikbooking_before_send_mail` (`onBeforeSendMail`, `platform/org/wordpress/mailer.php:52`), le seul hook que **tous** les e-mails de Vik traversent — rappels avant séjour compris, que `sendBookingEmail()` n'émet pas.

**Ce qui est posé : l'expéditeur, le nom affiché et l'adresse de réponse, rien d'autre.** Ni objet, ni corps. L'adresse de réponse s'aligne sur l'expéditeur de la marque, comme pour le message client (décision de Thomas du 26 septembre 2026, `docs/briefs/constat-b5-rappels.md` §10). Elle suit le From : si l'expéditeur n'est pas posé, ou si la marque est mêlée ou indéterminée, l'adresse de réponse posée par Vik reste — `lme_brands_mail_source_reply_to()`.

**Comment la réservation est retrouvée.** Ce hook ne transporte aucun contexte métier. L'émetteur dépose sa réservation dans `VikBookingHelperConditionalRules` avant de composer son message — c'est le mécanisme natif des textes conditionnels —, et on la relit par son accesseur public `get('booking')`. Mais Vik **ne vide jamais** ce magasin entre deux envois : `lme_brands_mail_booking_matches_recipients()` n'y croit que si le client de cette réservation est parmi les destinataires du message. Sinon, rien n'est touché — le message part comme avant ce plugin, jamais sous une marque devinée.

**Un message, un seul traitement.** Le message client de réservation traverse les deux hooks : le hook métier d'abord, puis celui-ci, sur le même objet. Le hook métier marque donc chaque message qu'il a vu, et celui-ci laisse les messages marqués tranquilles — y compris ceux que le hook métier a délibérément laissés intacts, la copie de l'administrateur et les réservations OTA. La marque vit dans un `WeakMap` (repli `SplObjectStorage` avant PHP 8.0), jamais dans un `spl_object_id()`, qui est réattribué après libération et ferait passer un rappel pour un message déjà traité.

**Le périmètre réel dépasse le rappel, et c'est voulu.** Le recoupement retient tout message adressé au client d'une réservation directe dont la marque se résout : le rappel avant séjour, mais aussi le rappel de pré-enregistrement, les factures, la messagerie en lot et le message envoyé à la main depuis l'écran d'une réservation. Tous partent aujourd'hui sous `L'Instant Clé`, y compris pour une chambre Sexcape Room, et la marque de leur réservation est la bonne pour chacun.

**Marque indéterminée** : identité neutre et alerte `mail_brand_undetermined`, exactement comme pour le message client — même code, le contexte porte `chemin: vikbooking_before_send_mail` pour distinguer les deux chemins dans le journal, et `reply_to_kept`, l'adresse de réponse de Vik laissée en place.

**Pas de contrôle de fuite sur ce chemin, et c'est une décision.** Le corps du rappel nomme L'Instant Clé, c'est connu et inventorié en chantier F : une alerte à chaque envoi répéterait un fait déjà su. Le jour où F sera fait, y brancher `lme_brands_report_mail_leak()` rendra ce travail vérifiable en continu, comme il l'est pour la confirmation.

---

## Paiement par marque, chapitre 4.5, `includes/payment-brand.php`

Greffé sur `payment_before_begin_transaction_vikbooking` (documenté en Q5 de `constat-phase-0.md`), qui se déclenche avant que VikStripe ne construise la session de paiement. Restreint à `$payment->isDriver('stripe')`, la seule passerelle publiée. La marque se résout depuis les chambres de la réservation (`lme_brands_booking_room_ids()`, désormais dans `includes/registry.php`, partagée avec `mail-brand.php`), jamais depuis l'hôte de la requête : un paiement peut reprendre une commande créée plus tôt.

**Ce qu'il pose réellement, et ce que le brief demandait.** Le §4.5 du brief demande une métadonnée de marque et un `statement_descriptor_suffix` sur le **PaymentIntent**. Une lecture ciblée du greffon (`docs/briefs/constat-phase-4-paiement.md`, chapitre 2) établit que :

- la seule clé lue par VikStripe pour une métadonnée par transaction est `tn_metadata` (`$payment->get('tn_metadata', [])`), fusionnée dans la métadonnée de la **session Stripe Checkout** — jamais copiée automatiquement sur le PaymentIntent qu'elle crée ;
- `payment_intent_data.metadata` n'existe que via un réglage d'administration unique pour toute l'installation, partagé par les deux marques : structurellement impropre à une valeur par marque ;
- `statement_descriptor_suffix` n'est lu nulle part dans le greffon.

`lme_brands_set_payment_metadata()` pose donc `lme_brand`, `lme_room_ids` et `lme_booking_id` sur `tn_metadata` — la métadonnée de la session, pas du PaymentIntent. Le libellé de relevé bancaire n'est pas posé du tout : le levier n'existe pas sans modifier le greffon. Décision à prendre par Thomas, voir le constat.

**L'URL de retour.** `return_url`, `error_url` et `notify_url` sont déjà construites sur le bon hôte par le cœur de Vik pendant la même requête (filtrées par `includes/url-rewrite.php`, phase 1). `lme_brands_correct_payment_urls()` est un filet, pas le mécanisme : elle vérifie l'hôte des trois valeurs et ne corrige que si l'une diffère de l'hôte HTTP réel de la requête courante — jamais de l'hôte déclaré au registre pour la marque résolue, même raisonnement et même correctif que `includes/url-rewrite.php` (`constat-correctif-signature-paiement.md` §4) — une correction réelle journalise en erreur et alerte (`payment_url_host_corrected`), parce qu'elle signale un trou ailleurs, pas un fonctionnement normal.

**Marque indéterminée.** Comme en phase 3 : rien n'est posé, ni métadonnée ni correction d'URL, et `payment_brand_undetermined` journalise en erreur et alerte.

**La page de confirmation.** `confirmation_page_id` vaut `845` pour les deux marques dans le registre — pas une page par marque, mais la même page partagée (`constat-perimetre-tunnel.md` §4), dont l'apparence varie déjà par hôte depuis le chantier D.

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

La phase 3 porte le total à **96 tests, tous au vert**. Elle ajoute à `includes/core.php` neuf fonctions pures — `mail_audience()`, `resolve_brand_for_rooms()`, `normalize_language_tag()`, `pick_localized()`, `apply_text_replacements()`, `foreign_brand_tokens()`, `normalize_for_search()`, `find_foreign_tokens()`, `inject_html_before_body_end()` — et `mail_identity()`, qui décide seule de l'identité posée sur un message. C'est cette dernière qui porte la règle « jamais la mauvaise marque » : elle est pure, donc la règle est vérifiable sans site, sans base et sans envoyer un e-mail.

Le chantier B5 porte le total à **110 tests, tous au vert**. Il ajoute `lme_brands_normalize_email()` et `lme_brands_mail_booking_matches_recipients()` — cette dernière est la parade contre l'état partagé que Vik ne remet jamais à zéro, donc la fonction qui décide si l'on a le droit de nommer une marque sur un rappel. Elle est pure, et ses dix cas se vérifient sans site, sans base et sans envoyer un e-mail.

Le chantier B8 porte le total à **120 tests, tous au vert**. Il ajoute `lme_brands_resolve_effective_http_host()`, qui décide seule si le levier de préproduction s'applique — c'est elle qui garantit, de façon vérifiable sans WordPress, que le levier reste inerte hors de l'environnement `staging` quelle que soit la constante définie par erreur ailleurs. `includes/registry.php` (`lme_brands_current_http_host()`), qui lit la constante et journalise son emploi, n'a pas d'équivalent testable hors WordPress, comme le reste des fichiers `includes/` listés ci-dessous.

**Décision du 26 septembre 2026 sur l'adresse de réponse de B5** porte le total à **141 tests, tous au vert**. Elle ajoute `lme_brands_mail_source_reply_to()`, qui décide seule si un message de B5 prend l'adresse de réponse de la marque, et un test sur le registre réel qui tombe si une marque répondait ailleurs qu'elle n'écrit.

Elle ajoute aussi un second fichier, `tests/test-mail-source.php` (**22 tests**), le premier de ce plugin à exécuter un point d'accroche réel hors WordPress : il charge `includes/mail-brand.php` avec des doublures minimales (`add_action`, le journal, la lecture des chambres, le magasin partagé de Vik, un wrapper qui reproduit `VBOMailWrapper`) et le vrai registre, et vérifie `lme_brands_brand_mail_source()` de bout en bout — marque résolue, marque mêlée, chambre inconnue, réservation sans chambre, état périmé, OTA, message déjà tranché, wrapper incomplet, expéditeur refusé.

```bash
php mu-plugins/lme-brands/tests/test-mail-source.php
```

**Vues de Vik joignables par `view`, 26 septembre 2026** porte `tests/test-core.php` à **166 tests, tous au vert**, sous PHP 8.5 en local et sous PHP 8.2.34, celui du serveur. Il ajoute à `includes/core.php` : `lme_brands_normalize_vik_cmd()` et `lme_brands_vik_filter_int()`, qui reproduisent les filtres `cmd` et `int` de Vik ; `lme_brands_room_category_tokens_from_idcat()` ; `lme_brands_view_room_selection()`, qui dit quelles chambres une vue de liste afficherait pour une requête donnée ; `lme_brands_first_foreign_room()`. Les décisions de fermeture y sont vérifiées contre le registre réel et la carte `idcat` relevée sur staging13 (`docs/briefs/constat-vues-vik-par-view.md`).

`includes/logger.php`, `includes/registry.php`, `includes/url-rewrite.php`, `includes/room-filter.php`, `includes/booking-guard.php`, `includes/mail-brand.php`, `includes/payment-brand.php` et `includes/health-screen.php` appellent des fonctions WordPress (`get_option`, `add_filter`, `add_action`, `$wpdb`, `is_admin`, `wp_die`...) et n'ont pas d'équivalent testable hors WordPress dans ce dépôt. Les vérifier suit les procédures manuelles ci-dessous, sur `staging10.linstantcle.ch` de préférence, jamais en production. `payment-brand.php` ne définit aucune fonction pure nouvelle : il orchestre `lme_brands_resolve_brand_for_rooms()` et `lme_brands_swap_url_host()`, déjà couvertes par les tests de la phase 3 et de la phase 1.

**Correctif du 22 septembre 2026** porte le total à **132 tests, tous au vert** (`docs/briefs/brief-correctif-levier-et-fatal-paiement.md` chapitre 2, `docs/briefs/constat-correctif-url-rewrite.md`). Recette du 21 septembre sur `staging13.linstantcle.ch` : sous le levier B8, `includes/url-rewrite.php` visait le `host` déclaré au registre pour la marque résolue — l'hôte de *production* de cette marque, y compris en préproduction — au lieu de l'hôte réel de la requête. Les feuilles de style et les polices de la préproduction se chargeaient donc depuis `reservation.sexcaperoom.ch`. Deux ajouts purs à `includes/core.php` : `lme_brands_normalize_http_host()` et `lme_brands_resolve_url_rewrite_target()`, qui décide seule de la cible d'une réécriture — toujours l'hôte HTTP réel de la requête (`includes/registry.php`, nouvelle fonction `lme_brands_current_raw_http_host()`), jamais un `host` lu dans le registre. En production les deux hôtes coïncident toujours (aucun comportement changé, vérifié par des tests d'équivalence) ; en préproduction sous le levier, ils divergent — exactement où la correction s'applique.

---

## Procédures de vérification manuelle

### 0. Le levier de préproduction — préalable à toute recette sur staging10

1. Sur `staging10.linstantcle.ch`, sans la constante définie : visiter n'importe quelle page front, vérifier `debug.log` — aucune ligne `[host_override_used]`. `lme_brands_current_request_brand_key()` doit résoudre `null` (aucune marque), comportement inchangé depuis la phase 1.
2. Ajouter `define( 'LME_BRANDS_HOST_OVERRIDE', 'reservation.sexcaperoom.ch' );` dans le `wp-config.php` de `staging10` (jamais dans celui de production). Recharger une page front : `debug.log` doit porter une ligne `[lme-brands] [WARNING] [host_override_used]`, une seule pour la requête même si plusieurs fonctions du plugin appellent la résolution d'hôte.
3. Vérifier que la page se comporte comme si elle était visitée depuis `reservation.sexcaperoom.ch` : présentation, garde et apparence (une fois D2 déployé) suivent la marque Sexcape Room.
4. Changer la valeur de la constante pour `linstantcle.ch` (ou la retirer), recharger : bascule immédiate vers l'autre marque, sans redéploiement.
5. Mettre la constante sur un hôte qui n'est celui d'aucune marque (ex. `hote-inconnu.example.ch`) : aucune marque ne doit résoudre, exactement comme un hôte de requête inconnu — critère de recette n°1 du chapitre B8 du plan de marche.
6. Contre-épreuve, la plus importante : vérifier qu'aucun `wp-config.php` de production ne porte cette constante. Si elle y apparaissait, `wp_get_environment_type()` y vaut `'production'` et le levier resterait inerte — mais elle ne doit de toute façon jamais y être écrite.

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

### 7. Couche de présentation, vues sans filtre natif

La vérification 2 de `recetter-moteur.sh` mène ces essais, et ceux des vues fermées le 26 septembre 2026 (`loginregister`, `promotions`, `searchsuggestions`, AJAX du site), dans les deux sens. Les essais manuels ci-dessous restent valables.

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

### 9. E-mails par marque — critères de recette n°6 et n°11 du brief

Sur `staging10.linstantcle.ch`, jamais en production, avec un mode de paiement hors ligne et une boîte de réception dédiée.

**Prérequis, à ne pas sauter** : les quatre adresses du registre (`sender_email` et `reply_to` de chaque marque) doivent exister et être autorisées à émettre. Sans cela ce test mesure la délivrabilité d'adresses fictives. Voir §6 de `docs/briefs/constat-phase-3-emails.md`.

1. Réserver la chambre 4 (Le Boudoir du Désir) depuis `reservation.sexcaperoom.ch`. Dans le message reçu, vérifier **dans les en-têtes bruts**, pas dans l'affichage du client de messagerie :
   - `From:` porte `Sexcape Room <reservations@sexcaperoom.ch>` — le nom **et** l'adresse, le nom seul ne prouve rien ;
   - `Reply-To:` porte l'adresse Sexcape Room ;
   - `Subject:` est celui du registre, et ne contient ni « L'Instant Clé » ni « linstantcle » ;
   - `dkim=pass` et `spf=pass`, sur une boîte Gmail **et** une boîte Outlook.
2. Réserver la chambre 8 (La Parenthèse) depuis `linstantcle.ch` : `From:` porte `L'Instant Clé <reservations@linstantcle.ch>`, et l'objet est celui de Vik, inchangé.
3. Pour les deux, ouvrir la **pièce jointe `.ics`** dans un éditeur de texte : `SUMMARY` et `LOCATION` doivent porter le nom de la marque de la réservation, jamais celui de l'autre.
4. Vérifier que l'e-mail de l'**administrateur** de ces deux réservations n'a pas changé : même expéditeur qu'avant l'installation du plugin, objet toujours suffixé de `#<identifiant>`.
5. Vérifier dans `sir_vikbooking_orders` (lecture seule) que les deux réservations portent la bonne chambre — critère de recette n°11.

### 10. Le contrôle de fuite

1. Après le point 9.1, vérifier `debug.log` : une ligne `[lme-brands] [ERROR] [mail_brand_leak]` doit apparaître, listant `linstantcle.ch` dans son contexte. **C'est le comportement attendu aujourd'hui**, tant que le corps du gabarit n'est pas séparé par marque : le gabarit unique de Vik contient treize URL sur ce domaine.
2. Après le point 9.2, vérifier qu'**aucune** ligne `mail_brand_leak` n'apparaît : un message L'Instant Clé n'a rien d'étranger à signaler.
3. Le jour où le corps sera séparé par marque (§5 du constat de phase 3), refaire 10.1 : le silence est alors la preuve du critère de recette n°6.

### 11. Marque indéterminée — l'expéditeur neutre

1. Dans l'administration de Vik, composer une réservation qui mêle une chambre de chaque marque (ex. 8 et 9), puis lui renvoyer l'e-mail client depuis l'écran de la réservation. Vérifier :
   - `From:` ne nomme **ni** L'Instant Clé **ni** Sexcape Room ;
   - l'objet ne nomme aucune des deux non plus ;
   - `debug.log` porte `[lme-brands] [ERROR] [mail_brand_undetermined]` avec `reason: mixed_brands` et les deux identifiants de chambre dans le contexte ;
   - l'action `lme_brands_alert` s'est déclenchée.
2. Retirer temporairement une chambre vendue de `config/brands.php`, renvoyer l'e-mail d'une réservation qui la porte : mêmes vérifications, avec en plus une ligne `[unknown_room]` et `reason: unknown_room`. Remettre la ligne.
3. Contre-épreuve : une réservation ordinaire à une seule marque ne doit produire aucune de ces lignes.

### 12. Les réservations OTA ne sont pas touchées

Reprendre une réservation Airbnb ou Booking.com existante (`channel` renseigné) et lui renvoyer l'e-mail client depuis l'administration. L'expéditeur, l'objet et le corps doivent être **exactement** ceux d'avant l'installation du plugin, et `debug.log` ne doit porter aucune ligne `lme-brands` — y compris si la réservation porte une chambre Sexcape Room. C'est le §4.4 du brief, et c'est une limite connue, pas un défaut.

### 13. Paiement par marque — critère de recette n°8 (portée réduite, voir le constat)

Sur `staging10.linstantcle.ch`, en mode test Stripe. Détail complet et pourquoi la portée est réduite : `docs/briefs/constat-phase-4-paiement.md`.

1. Réserver une chambre Sexcape Room (ex. 4) depuis `reservation.sexcaperoom.ch` jusqu'à l'écran de paiement. Dans le tableau de bord Stripe (mode test), ouvrir la **session Checkout** : ses métadonnées doivent porter `lme_brand: sexcaperoom`, `lme_room_ids`, `lme_booking_id`. Le PaymentIntent associé n'en porte aucune — attendu, pas une anomalie.
2. `debug.log` ne doit porter aucune ligne `[lme-brands] [ERROR] [payment_url_host_corrected]` pour ce parcours : sa présence signale un trou dans le filtrage d'hôte de la phase 1, à corriger avant toute autre chose.
3. Répéter côté `linstantcle.ch` : `lme_brand: linstantcle`.
4. Annuler le paiement depuis l'écran Stripe : le client doit revenir sur la page 845 de l'hôte de départ, jamais celle de l'autre marque.
5. Composer une réservation à marques mêlées dans l'administration de Vik (ex. 8 et 9) et déclencher un paiement Stripe si l'écran le permet : `debug.log` doit porter `[lme-brands] [ERROR] [payment_brand_undetermined]`, et la session créée ne doit porter aucune métadonnée `lme_*`.

### 14. La source des rappels avant séjour — chantier B5

Sur `staging10.linstantcle.ch`, jamais en production. Mêmes prérequis d'adresses que le point 9.

La tâche `Check-in info` (identifiant 7 dans `sir_vikbooking_cronjobs`) notifie deux jours avant l'arrivée. Pour la déclencher à volonté sans attendre, la dupliquer en préproduction avec `remindbefored` réglé sur l'écart réel d'une réservation de test, ou exécuter la tâche à la main depuis **Cron Jobs** dans l'administration de Vik.

1. Créer en préproduction une réservation de la chambre 4 (Le Boudoir du Désir) dont l'arrivée tombe dans la fenêtre de la tâche, puis exécuter la tâche. Dans le message reçu, vérifier **dans les en-têtes bruts** : `From:` porte `Sexcape Room <reservations@sexcaperoom.ch>`, et `Reply-To:` porte `reservations@sexcaperoom.ch`. Sur la préproduction, le garde-fou de messagerie consigne le `Reply-To` d'origine dans sa transcription : c'est là qu'on le lit si le message est détourné.
2. Vérifier que l'**objet** est resté celui de la tâche (`Infos de dernière minute pour votre séjour`) et que le **corps** est inchangé — logo et titre L'Instant Clé compris. C'est le comportement attendu tant que le chantier F n'est pas fait : B5 corrige l'expéditeur, F corrige le contenu.
3. Refaire avec une chambre L'Instant Clé (ex. 8) : `From:` porte `L'Instant Clé <reservations@linstantcle.ch>`, `Reply-To:` porte `reservations@linstantcle.ch`.
4. Composer une réservation à marques mêlées (ex. 8 et 9) dont l'arrivée tombe dans la fenêtre, exécuter la tâche : `From:` ne nomme aucune des deux marques, `Reply-To:` est resté l'adresse de Vik (`info@maisonnette-enchantee.ch`), et `debug.log` porte `[mail_brand_undetermined]` avec `reason: mixed_brands`, `chemin: vikbooking_before_send_mail` et `reply_to_kept`.
5. Contre-épreuve du recoupement, celle qui compte : dans la **même exécution** de la tâche, deux réservations de marques différentes doivent produire deux messages de marques différentes, `From:` et `Reply-To:` compris. Si les deux portaient la même, la réservation du magasin partagé de Vik ne serait pas relue entre deux envois, et la parade serait en défaut.
6. Contre-épreuve du marqueur : refaire le point 9.1 (message client d'une réservation Sexcape Room). Son `From:` doit être celui de la phase 3, et la copie de l'administrateur de cette même réservation doit rester inchangée — le hook générique ne doit ni doubler l'un, ni reprendre l'autre.
7. Reprendre une réservation OTA portant une chambre Sexcape Room et lui faire envoyer un rappel : expéditeur et adresse de réponse inchangés, aucune ligne `lme-brands` dans le journal.

---

## Retour arrière

Retirer `mu-plugins/lme-brands.php` (ou tout le dossier `mu-plugins/lme-brands/`) du serveur restaure le comportement d'avant ce plugin : aucun fichier de Vik, du thème parent ou d'un plugin tiers n'est modifié.
