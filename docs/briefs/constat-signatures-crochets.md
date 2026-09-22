# Constat — inventaire des signatures de tous les crochets de `mu-plugins/lme-brands/`

22 septembre 2026. Réponse à la commande passée après
`constat-correctif-signature-paiement.md` : la phase 4 a été cassée par une
signature supposée à partir du seul code de Vik, sans tenir compte de ce que
WordPress fait des arguments avant de les livrer (`constat-phase-0.md` §Q5,
leçon finale : « lire le seul code de Vik ne suffit pas à prédire le
comportement réel d'un hook WordPress »). Ce constat reprend chaque crochet
auquel `lme-brands` s'accroche, un par un, et établit sa forme réelle en
lisant **l'appel côté Vik ou WordPress et le traitement du cœur WordPress**,
jamais l'un sans l'autre — la méthode que le défaut de paiement a coûté cher
à apprendre.

Lecture seule sur le serveur (SSH `sg-linstantcle`, sur les fichiers de
`wp-includes/` de `linstantcle.ch`, jamais de secret) et sur `.local/`.
Aucune écriture en base, rien de déployé. Douze `add_action`/`add_filter`
trouvés dans neuf fichiers de `includes/` (`core.php`, `logger.php` et
`registry.php` n'en posent aucun). Un treizième point, `apply_filters(
'lme_brands', $default )` dans `registry.php:26`, est **hors périmètre** :
c'est un filtre que ce plugin *expose*, pas un auquel il s'accroche — aucun
tiers n'y est abonné aujourd'hui.

**Verdict d'ensemble : aucun écart n'a été trouvé, sauf celui déjà corrigé le
22 septembre 2026 dans `payment-brand.php` (`constat-correctif-signature-paiement.md`).**
Chaque crochet est détaillé ci-dessous avec sa preuve.

---

## Le mécanisme à connaître avant de lire le reste

Trois façons dont WordPress livre des arguments à un rappel, lues dans
`wp-includes/plugin.php` et `wp-includes/class-wp-hook.php` (copie de
`linstantcle.ch`, empreintes déjà comparées à la préproduction dans
`constat-fatal-page-paiement.md` §3) :

1. **`do_action( $hook, ...$arg )`** — seule fonction porteuse du piège déjà
   rencontré : si le second argument est un tableau à un seul élément qui est
   un objet, `array( &$this )`, il est déballé et l'objet est livré
   directement (`plugin.php:515-518`, compatibilité PHP4). C'est ce qui a
   cassé `payment-brand.php`.
2. **`do_action_ref_array( $hook, $args )`** et **`apply_filters_ref_array(
   $hook, $args )`** — **aucun déballage**, lu ligne à ligne dans
   `plugin.php:543-568` et `plugin.php:229-259` : `$args` est utilisé tel
   quel comme liste positionnelle. C'est la voie que prend
   `VBOPlatformOrgWordpressDispatcher::trigger()`/`filter()`
   (`.local/vikbooking/admin/helpers/src/platform/org/wordpress/dispatcher.php:32,49`)
   pour **tous** les crochets traduits du nom Joomla (tout ce qui commence
   par `on…VikBooking` ou `on…` dans le code de Vik). Aucun de ces crochets
   ne peut donc subir le piège du chapitre 1.
3. **`apply_filters( $hook, $value, ...$args )`** — pas de déballage non
   plus (`plugin.php:174-208`) ; sert aux filtres natifs de WordPress
   (`option_home`, `content_url`, etc.).

Une fois les arguments réunis, `WP_Hook::apply_filters()`
(`class-wp-hook.php:328-362`) les livre au rappel par
`call_user_func_array()`, tronqués à `accepted_args` si le rappel en déclare
moins que ce que le crochet fournit (`array_slice( $args, 0,
$accepted_args )`, ligne 355) — **jamais d'erreur si `accepted_args` est
inférieur au compte réel**, seulement des arguments non lus. Le risque
inverse existe : si `accepted_args` **dépasse** le compte réel d'arguments
fournis (`$the_['accepted_args'] >= $num_args`, ligne 352), le rappel est
appelé avec **moins** d'arguments qu'il n'en déclare sans valeur par défaut —
`ArgumentCountError` fatale sous PHP 8, la même famille d'erreur que le
défaut de paiement. C'est le second risque vérifié pour chaque crochet
ci-dessous, en plus du premier.

---

## 1. `vikbooking_apply_search_results_filtering`

**Fichier :** `includes/room-filter.php:47`, `add_filter(…, 'lme_brands_filter_search_results', 10, 3 )`.

**Appel côté Vik :** `vikbooking/site/views/search/view.html.php:590` —
`$customFiltering = …->filter('onApplySearchResultsFiltering', [$tt[0], $resultFilters]);`

**Traitement WordPress :** `VBOPlatformOrgWordpressDispatcher::filter()`
(`dispatcher.php:44-58`) fait `array_unshift($args, null)` puis
`apply_filters_ref_array()` — pas de déballage (mécanisme n°2 ci-dessus).
`$args` livré à `WP_Hook::apply_filters()` vaut `[null, $tt[0],
$resultFilters]`, trois éléments.

**Signature réellement reçue :** `( $value = null, array $room, array
$result_filters )`, `accepted_args` du crochet = 3, compte réel fourni = 3 →
correspondance exacte, aucune troncature, aucun déficit.

**Signature déclarée :** `function lme_brands_filter_search_results( $value, $room, $result_filters )` — identique.

**Écart : aucun.**

---

## 2. `template_redirect`

**Fichier :** `includes/room-filter.php:88`, `add_action( 'template_redirect', 'lme_brands_enforce_shortcode_room_scope', 0 )`.

**Appel côté WordPress :** `wp-includes/template-loader.php:23` —
`do_action( 'template_redirect' )`, sans second argument.

**Traitement WordPress :** `do_action()` (mécanisme n°1), avec `$arg`
variadique vide. `plugin.php:513-515` : `if ( empty( $arg ) ) { $arg[] =
''; }` — pas de déballage possible ici (la branche `elseif` ne s'exécute que
si `$arg` n'est pas vide), mais un argument fantôme, la chaîne vide, est
tout de même livré. Compte réel fourni = 1.

**Signature réellement reçue :** `( '' )`, un seul argument, la chaîne vide.
`accepted_args` du crochet, non précisé au 4ᵉ paramètre de `add_action()`,
vaut 1 par défaut → correspondance exacte.

**Signature déclarée :** `function lme_brands_enforce_shortcode_room_scope()` — zéro paramètre déclaré.

**Écart : aucune erreur.** Un rappel PHP appelé avec plus d'arguments qu'il
n'en déclare ignore silencieusement l'excédent ; ce n'est pas le sens inverse
(déclarer plus que ce qui est fourni) qui casse. `accepted_args` (1) ne
dépasse pas le compte réel (1), donc même le second risque du chapitre
« mécanisme » ne s'applique pas ici.

---

## 3. `vikbooking_before_send_booking_mail`

**Fichier :** `includes/mail-brand.php:46`, `add_action(…, 'lme_brands_rewrite_booking_mail', 10, 3 )`.

**Appel côté Vik :** `vikbooking/site/helpers/lib.vikbooking.php:6502` —
`…->trigger('onBeforeSendBookingMailVikBooking', [$who, $booking, $mail]);`

**Traitement WordPress :** `trigger()` → `do_action_ref_array()` (mécanisme
n°2), `$args` livré tel quel : `[$who, $booking, $mail]`, trois éléments.

**Signature réellement reçue :** `( $who, $booking, $mail )`, dans cet
ordre. `accepted_args` = 3, compte réel = 3 → correspondance exacte.

**Signature déclarée :** `function lme_brands_rewrite_booking_mail( $who, $booking, $mail )` — identique, même ordre.

**Écart : aucun.**

---

## 4. `vikbooking_before_create_mail_ical`

**Fichier :** `includes/mail-brand.php:255`, `add_action(…, 'lme_brands_brand_mail_ical', 10, 3 )`.

**Appel côté Vik :** `vikbooking/site/helpers/lib.vikbooking.php:5563` —
`…->trigger('onBeforeCreateMailIcalVikBooking', [$recip, $booking, &$ics_str]);`

Point à noter, absent de `constat-phase-0.md` §Q2, qui ne citait que
« `[&$ics_str]` » en insistant sur le passage par référence du contenu — la
lecture du code ici montre que **trois** arguments sont réellement transmis,
pas un seul. Ce n'était pas faux, seulement elliptique : le hook n'a jamais
été utilisé sous une forme à un seul argument dans ce plugin.

**Traitement WordPress :** `trigger()` → `do_action_ref_array()` (mécanisme
n°2), `$args` livré tel quel : `[$recip, $booking, &$ics_str]`, trois
éléments, le troisième par référence (le tableau `$args` lui-même contient
une référence PHP à la variable `$ics_str` de l'appelant, WordPress ne
retire ni ne copie cette référence en la faisant transiter par
`call_user_func_array()`).

**Signature réellement reçue :** `( $recip, $booking, &$ics_str )`.
`accepted_args` = 3, compte réel = 3 → correspondance exacte.

**Signature déclarée :** `function lme_brands_brand_mail_ical( $recip, $booking, &$ics_str )` — identique, y compris le passage par référence du troisième paramètre, nécessaire pour que `$ics_str = str_replace(...)` modifie effectivement le fichier `.ics` de l'appelant.

**Écart : aucun.**

---

## 5. `vikbooking_before_send_mail`

**Fichier :** `includes/mail-brand.php:336`, `add_action(…, 'lme_brands_brand_mail_source', 10, 1 )`.

**Appel côté Vik :** `vikbooking/admin/helpers/src/platform/org/wordpress/mailer.php:52` —
`…->trigger('onBeforeSendMail', [$mail]);`

**Traitement WordPress :** `trigger()` → `do_action_ref_array()` (mécanisme
n°2), `$args` livré tel quel : `[$mail]`, un seul élément.

**Signature réellement reçue :** `( $mail )`. `accepted_args` = 1, compte
réel = 1 → correspondance exacte.

**Signature déclarée :** `function lme_brands_brand_mail_source( $mail )` — identique.

**Écart : aucun.**

---

## 6–10. Les cinq filtres de réécriture d'URL — `includes/url-rewrite.php`

Ce sont des filtres natifs du **cœur WordPress**, pas de Vik : le point
d'accroche et le traitement sont le même fichier, lu directement sur le
serveur.

| # | Crochet | Fichier :ligne (mu-plugin) | Appel réel (`wp-includes/…`, copie `linstantcle.ch`) | Args réels fournis | `accepted_args` déclaré | Rappel déclaré |
|---|---|---|---|---|---|---|
| 6 | `option_home` | `url-rewrite.php:87` | `option.php:256` — `apply_filters( "option_{$option}", maybe_unserialize($value), $option )` | 2 : `( $value, $option )` | 1 (défaut) | `lme_brands_filter_option_home( $value )` |
| 7 | `option_siteurl` | `url-rewrite.php:93` | idem, même ligne, `$option` vaut `'siteurl'` | 2 : `( $value, $option )` | 1 (défaut) | `lme_brands_filter_option_siteurl( $value )` |
| 8 | `content_url` | `url-rewrite.php:99` | `link-template.php:3667` — `apply_filters( 'content_url', $url, $path )` | 2 : `( $url, $path )` | 1 (défaut) | `lme_brands_filter_content_url( $url )` |
| 9 | `upload_dir` | `url-rewrite.php:118` | `functions.php:2429` — `apply_filters( 'upload_dir', $cache[$key] )` | 1 : `( $uploads )` | 1 (défaut) | `lme_brands_filter_upload_dir( $uploads )` |
| 10 | `wp_get_attachment_url` | `url-rewrite.php:124` | `post.php:7248` — `apply_filters( 'wp_get_attachment_url', $url, $post->ID )` | 2 : `( $url, $post_id )` | 1 (défaut) | `lme_brands_filter_wp_get_attachment_url( $url )` |

**Traitement WordPress commun :** `apply_filters()` (mécanisme n°3, pas de
déballage). `WP_Hook::apply_filters()` tronque à `accepted_args` quand le
compte réel le dépasse (`class-wp-hook.php:355`) — c'est le cas pour #6, #7,
#8 et #10, où deux arguments sont fournis mais un seul est déclaré. **Sens
sans risque** : le second argument (`$option`, `$path`, `$post_id`) est
simplement non transmis, jamais lu par ces quatre rappels, qui n'en ont pas
besoin. Le sens inverse — `accepted_args` supérieur au compte réel, celui qui
aurait pu produire une `ArgumentCountError` — ne se produit sur aucun des
cinq : `accepted_args` vaut 1 partout, jamais plus que le minimum fourni (1
pour `upload_dir`, 2 pour les quatre autres).

**Écart : aucun**, pour les cinq.

---

## 11. `payment_before_begin_transaction_vikbooking`

**Fichier :** `includes/payment-brand.php:81`, `add_action(…, 'lme_brands_brand_payment_transaction', 10, 1 )`.

**Appel côté Vik :** `vikbooking/libraries/adapter/payment/payment.php:329` —
`do_action($this->getHook('payment_before_begin_transaction'), array(&$this));`

**Traitement WordPress :** `do_action()` (mécanisme n°1). `$arg = [
array(&$this) ]` au départ ; la condition de `plugin.php:515-518` s'applique
(`is_array($arg[0])` vrai, `count($arg[0]) === 1` vrai, `isset($arg[0][0])`
vrai, `is_object($arg[0][0])` vrai) : `$arg[0] = $arg[0][0]`, donc `$arg = [
$paymentObj ]`, un seul élément, l'objet lui-même — pas un tableau qui le
contiendrait.

**Signature réellement reçue :** `( $payment )`, l'objet `JPayment`
directement. `accepted_args` = 1, compte réel après déballage = 1 →
correspondance exacte.

**Signature déclarée :** `function lme_brands_brand_payment_transaction( $payment )` — identique, **déjà corrigée le 22 septembre 2026** (`constat-correctif-signature-paiement.md`). La version fautive du 17 septembre déclarait `( $args )` et lisait `$args[0]`, provoquant l'`Error` fatale documentée dans `constat-fatal-page-paiement.md`.

**Écart : aucun, sur le code actuel.** C'est le seul des treize crochets où
un écart a existé, et il est refermé.

---

## 12. `vikbooking_before_create_booking_record`

**Fichier :** `includes/booking-guard.php:32`, `add_action(…, 'lme_brands_guard_booking_record', 10, 5 )`.

**Appel côté Vik, deux occurrences identiques :**
`vikbooking/site/controller.php:1114` et `:1512` —
`…->trigger('onBeforeCreateBookingRecord', [$booking_record, $rooms, $tars, $selopt, $arrpeople]);`

Les deux occurrences ont été relues côte à côte : même liste, même ordre,
aucune divergence entre la branche « réservation confirmée » et la branche
« réservation en attente de paiement ».

**Traitement WordPress :** `trigger()` → `do_action_ref_array()` (mécanisme
n°2), `$args` livré tel quel : `[$booking_record, $rooms, $tars, $selopt,
$arrpeople]`, cinq éléments.

**Signature réellement reçue :** `( $booking_record, $rooms, $tars, $selopt,
$arrpeople )`, dans cet ordre. `accepted_args` = 5, compte réel = 5 →
correspondance exacte.

**Signature déclarée :** `function lme_brands_guard_booking_record( $booking_record, $rooms, $tars, $selopt, $arrpeople )` — identique, même ordre.

**Écart : aucun.**

---

## 13. `admin_menu`

**Fichier :** `includes/health-screen.php:19`, `add_action( 'admin_menu', 'lme_brands_register_health_screen' )`.

**Appel côté WordPress :** `wp-admin/includes/menu.php:168` —
`do_action( 'admin_menu', '' );`

**Traitement WordPress :** `do_action()` (mécanisme n°1), avec `$arg = [
'' ]` explicitement fourni par l'appelant. La condition de déballage ne
s'applique pas (`$arg[0]` est une chaîne, pas un tableau). Compte réel
fourni = 1.

**Signature réellement reçue :** `( '' )`, un argument fantôme historique,
jamais destiné à être lu. `accepted_args`, non précisé au 3ᵉ paramètre de
`add_action()`, vaut 1 par défaut → correspondance exacte.

**Signature déclarée :** `function lme_brands_register_health_screen()` — zéro paramètre déclaré.

**Écart : aucune erreur**, même raisonnement qu'au chapitre 2 : un
argument fourni en trop et non déclaré est simplement ignoré.

---

## Ce que ce constat ne fait pas

Conformément à la commande : rien n'est corrigé, aucune clé n'est lue, aucune
écriture n'a touché la base, rien n'est déployé. Le seul écart trouvé
(chapitre 11) était déjà refermé avant cette passe ; les douze autres
crochets sont sans écart. Si une décision doit suivre, elle porte sur la
méthode plutôt que sur du code à changer : ce constat vaut confirmation que
la leçon de `constat-phase-0.md` §Q5 — vérifier chaque forme d'argument
contre le traitement réel du cœur WordPress, jamais contre la seule lecture
de Vik — a été appliquée aux treize crochets existants du plugin, pas
seulement à celui qui avait déjà cassé.

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-22 | Création. Inventaire des treize `add_action`/`add_filter` de `mu-plugins/lme-brands/`, forme reçue établie par lecture croisée de l'appel (Vik ou cœur WordPress) et du traitement du cœur WordPress (`do_action()`, `do_action_ref_array()`, `apply_filters()`, `apply_filters_ref_array()`, troncature `accepted_args` de `WP_Hook::apply_filters()`). Aucun écart trouvé hors celui déjà corrigé dans `payment-brand.php`. |
