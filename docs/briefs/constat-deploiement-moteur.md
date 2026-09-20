# Constat — inventaire de déploiement du moteur, chantier B8

Version 1, 20 septembre 2026. Réponse au prompt B8 de `plan-de-marche.md`, à partir de
`sexcape-room-reservation.md` et de l'état réel du serveur. Chaque fait de ce document vient d'une
lecture directe, en lecture seule, faite le 20 septembre 2026 — jamais d'une supposition. **Rien
n'est déployé par ce constat, aucune ligne n'est écrite en base, aucune clé n'a été lue** : chaque
copie de fichier et chaque écriture dans `wp-config.php` reste un geste de Thomas.

---

## 0. Pourquoi ce constat existe

Sept phases du moteur sont écrites (B1 à B5, B4a, B4b) et **aucune n'est en service** :
`https://reservation.sexcaperoom.ch/` sert aujourd'hui la page d'accueil de L'Instant Clé, sans
habillage. Le plan disait « ne déploie rien » à chaque phase sans jamais dire qui déploie, où, ni
selon quelle vérification. Ce document comble ce trou : l'inventaire exact, l'ordre, la preuve
d'activité, et le retour arrière — pour la cible immédiate, **`staging10.linstantcle.ch`**, jamais
la production avant que la recette du chapitre B8 du plan de marche soit passée.

---

## 1. Ce qui est déjà sur le serveur, établi par lecture directe

Toutes les valeurs ci-dessous viennent d'une commande `ls`, `wc -c` ou `grep` sur un nom de
constante explicite, exécutée par SSH en lecture seule (`sg-linstantcle`), ou d'une lecture de
`sir_options` par clé explicite (jamais par joker, jamais une clé secrète) — conformément à la
règle absolue n°2 de `CLAUDE.md`.

- **`staging10.linstantcle.ch` est une copie fidèle du `wp-content` de production** à la date de
  cette lecture : les deux mêmes mu-plugins existants (voir plus bas), et un `astra-child` dont
  `functions.php` et `style.css` font exactement les mêmes tailles qu'en production, à l'octet
  près.
- **`wp-content/mu-plugins/`** (staging10 et production, identique) : deux fichiers, ni l'un ni
  l'autre n'est `lme-brands`. Rien de ce chantier n'y est encore.
  - `api-host.php`, 1371 octets — verrouille `api.linstantcle.ch` à des points d'entrée précis, sur
    `template_redirect` priorité 0. Fonctions préfixées `linstantcle_*`.
  - `vre-paid-autoconfirm.php`, 5592 octets — confirme automatiquement des commandes VikRestaurants
    payées. Hooks `vikrestaurants_status_change_takeaway_order` et
    `vikrestaurants_success_payment_transaction` uniquement. Fonctions préfixées `vre_acpo_*`.
  - **Aucune collision** de nom de fonction ni de hook avec `lme-brands`, établie par lecture
    complète des deux fichiers, pas par supposition.
- **`wp-content/themes/astra-child/functions.php`** (staging10 et production, identique) fait
  **7390 octets**, daté du 6 mai 2026, et porte déjà de la logique de production sans rapport avec
  ce chantier : suivi de conversion Google Analytics et Meta Pixel sur les réservations Vik
  Booking et les commandes VikRestaurants, règles `noindex` par page pour Yoast et pour Rank Math,
  visibilité de la barre d'administration pour les éditeurs, surlignage des jours de check-in
  indisponibles sur le calendrier Vik (CSS et JS injectés), balise de vérification de domaine
  Facebook, autorisation du robot Facebook dans `robots.txt`. **Ce n'est pas le fichier vide que ce
  dépôt supposait.** Voir chapitre 2.
- **`wp-content/themes/astra-child/style.css`** (staging10 et production, identique) fait **2771
  octets** : l'en-tête complet du thème (`Theme Name`, `Author`, `Description`...). Pas davantage
  un fichier vide.
- **`sir_options`**, lu par clé explicite (`option_name IN ('current_theme','template','stylesheet')`) :
  `current_theme = Astra Child`, `template = astra`, `stylesheet = astra-child`. **`astra-child`
  est déjà le thème actif sur linstantcle.ch**, sans étape d'activation à faire dans
  l'administration.
- **`wp-config.php` de staging10**, lu par nom de constante explicite (jamais le fichier entier,
  règle absolue n°2) : `WP_ENVIRONMENT_TYPE` y vaut déjà `'staging'`, posé par le système de
  staging SiteGround lui-même (« Added by SiteGround WordPress Staging system »). **Rien à ajouter
  de ce côté.** `LME_BRANDS_HOST_OVERRIDE` n'y existe pas encore.
- **`wp-config.php` de production**, même méthode de lecture : ne définit `WP_ENVIRONMENT_TYPE`
  nulle part. `wp_get_environment_type()` y retourne donc `'production'`, la valeur par défaut de
  WordPress. **Le levier de préproduction y est donc structurellement inerte**, sans rien à ajouter
  ni à vérifier pour s'en assurer — voir `lme_brands_resolve_effective_http_host()`,
  `includes/core.php`. `LME_BRANDS_HOST_OVERRIDE` n'y existe pas non plus.
- PHP **8.2.33** sur le serveur qui porte linstantcle.ch et staging10 : `WeakMap` est disponible,
  `lme_brands_mail_wrapper_decided()` (`includes/mail-brand.php`) l'utilisera, jamais son repli
  `SplObjectStorage`.

---

## 2. Correction apportée au dépôt avant ce constat

`themes/astra-child/functions.php` et `style.css` de ce dépôt ont été écrits le 9 septembre 2026,
avant la phase 1, sous une hypothèse jamais vérifiée depuis : que le contenu réel du thème serait
récupéré du serveur par SFTP pour remplacer ces fichiers (README.md du dossier, à l'origine). Ce
geste n'a jamais eu lieu. La phase D2 (`constat-habillage-tunnel.md`, 15 septembre 2026) a hérité
de cette hypothèse sans la vérifier : elle affirme que « le reste du fichier (feuille
`astra-child-style` existante) est inchangé » — une feuille qui, à la lecture directe faite
aujourd'hui, n'existe pas sous ce nom sur le serveur. Le fichier réel enqueue `style.css` sous le
handle **`astra-child-theme-css`**, priorité 15, pas `astra-child-style`.

**Conséquence évitée de justesse.** Un déploiement qui aurait copié `themes/astra-child/functions.php`
et `style.css` de ce dépôt par-dessus les fichiers réels aurait **supprimé en silence** tout ce que
le chapitre 1 énumère — suivi de conversion, règles de référencement, calendrier Vik — sans la
moindre erreur PHP, découvert seulement quand un indicateur (revenu publicitaire, indexation)
aurait décroché sans raison apparente. C'est exactement le risque que règle absolue n°6 de
`CLAUDE.md` (« aucun échec silencieux ») vise, appliqué ici au geste de déploiement lui-même plutôt
qu'au code.

**Corrigé aujourd'hui**, avant d'écrire ce constat :

- `themes/astra-child/functions.php` du dépôt ne prétend plus être un fichier à copier : il documente,
  dans un commentaire, la seule ligne à ajouter au fichier réel, et pourquoi.
- `themes/astra-child/style.css` du dépôt porte un avertissement : ne jamais le déployer.
- `themes/astra-child/README.md` explique que ce dossier ne porte que l'ajout de ce chantier, pas
  une copie du thème.

Le reste de ce document tient compte de cette correction : `functions.php` et `style.css` ne
figurent **pas** dans l'inventaire des fichiers à copier au chapitre 3.

---

## 3. Inventaire, fichier par fichier

| # | Source (dépôt) | Destination (sous `wp-content/`) | Action | Taille |
|---|---|---|---|---|
| 1 | `mu-plugins/lme-brands.php` | `mu-plugins/lme-brands.php` | copie neuve | 749 o |
| 2 | `mu-plugins/lme-brands/lme-brands.php` | `mu-plugins/lme-brands/lme-brands.php` | copie neuve | 1246 o |
| 3 | `mu-plugins/lme-brands/config/brands.php` | `mu-plugins/lme-brands/config/brands.php` | copie neuve | 20554 o |
| 4 | `mu-plugins/lme-brands/includes/core.php` | `mu-plugins/lme-brands/includes/core.php` | copie neuve | 36561 o |
| 5 | `mu-plugins/lme-brands/includes/logger.php` | `mu-plugins/lme-brands/includes/logger.php` | copie neuve | 2234 o |
| 6 | `mu-plugins/lme-brands/includes/registry.php` | `mu-plugins/lme-brands/includes/registry.php` | copie neuve | 7585 o |
| 7 | `mu-plugins/lme-brands/includes/url-rewrite.php` | `mu-plugins/lme-brands/includes/url-rewrite.php` | copie neuve | 3695 o |
| 8 | `mu-plugins/lme-brands/includes/room-filter.php` | `mu-plugins/lme-brands/includes/room-filter.php` | copie neuve | 10328 o |
| 9 | `mu-plugins/lme-brands/includes/booking-guard.php` | `mu-plugins/lme-brands/includes/booking-guard.php` | copie neuve | 8238 o |
| 10 | `mu-plugins/lme-brands/includes/mail-brand.php` | `mu-plugins/lme-brands/includes/mail-brand.php` | copie neuve | 23855 o |
| 11 | `mu-plugins/lme-brands/includes/payment-brand.php` | `mu-plugins/lme-brands/includes/payment-brand.php` | copie neuve | 8667 o |
| 12 | `mu-plugins/lme-brands/includes/health-screen.php` | `mu-plugins/lme-brands/includes/health-screen.php` | copie neuve | 4355 o |
| 13 | `mu-plugins/lme-brands/README.md`, `mu-plugins/lme-brands/tests/test-core.php` | mêmes chemins | optionnel | — |
| 14 | `themes/astra-child/inc/sexcaperoom-tunnel.php` | `themes/astra-child/inc/sexcaperoom-tunnel.php` | copie neuve | 11445 o |
| 15 | `themes/astra-child/assets/css/sexcaperoom-tunnel.css` | `themes/astra-child/assets/css/sexcaperoom-tunnel.css` | copie neuve | 7079 o |
| 16–22 | `themes/astra-child/assets/fonts/sexcaperoom/*.woff2` (7 fichiers) | mêmes chemins | copie neuve | 12996 à 29592 o chacun |
| — | *(aucune source)* | `themes/astra-child/functions.php`, fichier **réel**, 7390 o | **fusion manuelle**, une ligne ajoutée — chapitre 5 | — |
| — | `themes/astra-child/style.css` du dépôt | — | **ne pas déployer**, chapitre 2 | — |

Ligne 13 : `README.md` et `tests/test-core.php` vivent dans un sous-dossier de `mu-plugins/lme-brands/`,
que WordPress ne charge jamais automatiquement (seuls les fichiers PHP à la racine de
`mu-plugins/` le sont — `mu-plugins/README.md`, confirmé par la structure de chargement de ce
plugin lui-même : `lme-brands.php` à la racine pointe vers le dossier). Les inclure ou non ne change
rien au comportement du site.

Empreintes `sha256` (douze premiers caractères hexadécimaux), pour la vérification de l'inventaire
après copie, chapitre 6, dans le même esprit que la veille de version E4 (« surveiller l'empreinte
des fichiers, jamais le numéro de version ») :

| Fichier | `sha256` (12 premiers caractères) |
|---|---|
| `mu-plugins/lme-brands.php` | `e95436dd88b7` |
| `mu-plugins/lme-brands/lme-brands.php` | `7a3996869b42` |
| `mu-plugins/lme-brands/config/brands.php` | `fba71d56c2e0` |
| `mu-plugins/lme-brands/includes/core.php` | `a4ffca3882fc` |
| `mu-plugins/lme-brands/includes/logger.php` | `cfe20ef11f2b` |
| `mu-plugins/lme-brands/includes/registry.php` | `acc186f2704f` |
| `mu-plugins/lme-brands/includes/url-rewrite.php` | `aa6070fe6495` |
| `mu-plugins/lme-brands/includes/room-filter.php` | `a6e521cd52ba` |
| `mu-plugins/lme-brands/includes/booking-guard.php` | `3105020dc41c` |
| `mu-plugins/lme-brands/includes/mail-brand.php` | `b4ca7f7cfc01` |
| `mu-plugins/lme-brands/includes/payment-brand.php` | `b6380d66260f` |
| `mu-plugins/lme-brands/includes/health-screen.php` | `9f97a0a52bda` |
| `themes/astra-child/inc/sexcaperoom-tunnel.php` | `d17a3ac0a960` |
| `themes/astra-child/assets/css/sexcaperoom-tunnel.css` | `97852cf642d0` |

Recalculables depuis ce dépôt avec `shasum -a 256 <fichier>`, et depuis le serveur avec
`sha256sum <fichier>` (ou `shasum -a 256` selon l'outil disponible).

---

## 4. Ordre des copies

1. **Le mu-plugin d'abord**, en entier (lignes 1 à 13). C'est la seule dépendance des étapes
   suivantes : `themes/astra-child/inc/sexcaperoom-tunnel.php` teste
   `function_exists('lme_brands_current_request_brand_key')` avant de faire quoi que ce soit, mais
   déployer le registre avant le thème évite une fenêtre, même courte, où cette fonction
   n'existerait pas encore.
2. **Le thème ensuite** : créer `themes/astra-child/inc/` et `themes/astra-child/assets/` (lignes
   14 à 22), puis ajouter la ligne unique au `functions.php` réel (chapitre 5). Dans cet ordre et
   pas l'inverse : ajouter la ligne `require_once` avant que le fichier qu'elle inclut n'existe
   provoquerait une erreur fatale PHP sur **tout** le site, linstantcle.ch compris — voir chapitre
   8.
3. **Vérifier l'écran de santé** (Outils → Santé lme-brands) avant de toucher à `wp-config.php` :
   il ne dépend d'aucune résolution d'hôte, donc il fonctionne dès l'étape 1, sur staging10 comme en
   production. Les sept chambres vendues doivent apparaître synchronisées avant d'aller plus loin.
4. **Le levier de préproduction en dernier**, sur `staging10` seulement (chapitre 5) : une fois 1 à
   3 en place et vérifiés, pour que le tout premier instant où le levier devient actif trouve déjà
   tout ce dont il dépend.

---

## 5. Mise en service du levier — `staging10` seulement, jamais ailleurs

`WP_ENVIRONMENT_TYPE` vaut déjà `'staging'` sur `staging10` (chapitre 1) : il ne reste qu'une seule
ligne à ajouter à son `wp-config.php`, hors du bloc géré par SiteGround pour ne pas la perdre à une
resynchronisation :

```php
define( 'LME_BRANDS_HOST_OVERRIDE', 'reservation.sexcaperoom.ch' );
```

Changer la valeur pour `'linstantcle.ch'` bascule l'autre marque, sans redéploiement de code — voir
`mu-plugins/lme-brands/README.md`, section « Levier de préproduction ». **Ne jamais poser cette
ligne dans le `wp-config.php` de production** : `wp_get_environment_type()` y vaut `'production'`
par défaut, donc `lme_brands_resolve_effective_http_host()` l'ignorerait de toute façon (testé,
`tests/test-core.php`), mais elle n'a rien à y faire.

---

## 6. Vérification, morceau par morceau

**1. Le mu-plugin est présent et intact.** Depuis le serveur : `sha256sum` (ou `shasum -a 256`) sur
chacun des douze fichiers du chapitre 3, comparé à la table du chapitre 3. Puis charger n'importe
quelle page front de staging10 et vérifier `debug.log` : aucune erreur fatale, aucune ligne
`[lme-brands]` inattendue (une ligne `[config_invalid]` signalerait un registre corrompu par le
transfert).

**2. Le registre résout correctement.** Outils → Santé lme-brands (`add_management_page`, chapitre
4.2 du brief) : les sept chambres vendues apparaissent « synchronisées ». Si l'une manque ou est en
trop, **ne pas continuer** — la suite du chantier B8 (recette) part d'un registre faux.

**3. Le thème est en place, sans avoir touché au reste.** `grep -n "sexcaperoom-tunnel"
wp-content/themes/astra-child/functions.php` doit retourner exactly une ligne, celle du chapitre 5,
ajoutée à la fin ou n'importe où au niveau racine du fichier réel. `wc -c` sur ce même fichier doit
donner **7390 + la longueur de la ligne ajoutée**, jamais 749 ni une valeur proche de ce dépôt — un
nombre proche indiquerait un écrasement plutôt qu'une fusion, et il faut alors restaurer
immédiatement depuis la sauvegarde (chapitre 7).

**4. L'apparence Sexcape Room s'active sous le levier, et seulement sous lui.** Sur staging10,
`LME_BRANDS_HOST_OVERRIDE` réglé sur `reservation.sexcaperoom.ch` : le code source d'une page porte
les jetons `--srlm-*`, les trois familles de police (`Marcellus`, `Jost`, `Lora`) se chargent
(réseau de l'onglet Network du navigateur, fichiers `.woff2` de ce dossier). Basculer la constante
sur `linstantcle.ch` : ces jetons disparaissent, l'apparence Astra standard revient — critère de
recette n°4 du chapitre B8 du plan de marche.

**5. Un hôte inconnu ne résout toujours aucune marque.** Constante réglée sur une valeur qui n'est
celle d'aucune marque (ex. `hote-inconnu.example.ch`) : aucune apparence Sexcape Room, aucune
réécriture d'URL. Couvert par le test automatisé
`resolve_effective_http_host : le levier de préproduction n'invente jamais une marque…`,
`tests/test-core.php`, et vérifiable à l'œil sur le serveur de la même façon.

---

## 7. Retour arrière, morceau par morceau

- **Le mu-plugin** : supprimer `wp-content/mu-plugins/lme-brands.php` et tout le dossier
  `wp-content/mu-plugins/lme-brands/`. Un mu-plugin n'a pas d'étape de désactivation : le retirer du
  système de fichiers **suffit et suffit immédiatement**, à la requête suivante. Le site retrouve
  son comportement d'avant B1, sur les deux hôtes.
- **Le thème** : retirer la ligne `require_once .../inc/sexcaperoom-tunnel.php;` ajoutée au
  `functions.php` réel, puis supprimer `wp-content/themes/astra-child/inc/` et
  `wp-content/themes/astra-child/assets/`. Aucun autre fichier du thème n'a été touché : ce retour
  arrière est chirurgical, sans risque pour le suivi de conversion, les règles `noindex` ou le reste
  du chapitre 1.
- **Le levier** : retirer (ou mettre en commentaire) la ligne `define( 'LME_BRANDS_HOST_OVERRIDE',
  ... )` du `wp-config.php` de staging10. Sans effet ailleurs, puisqu'elle n'existe nulle part
  ailleurs.
- **Sauvegarde avant écriture, règle absolue n°3.** Avant l'étape 2 de l'ordre des copies (fusion
  dans `functions.php`), copier le fichier réel tel quel (`cp functions.php
  functions.php.avant-b8-20260920`) sur le serveur, hors de tout dossier servi publiquement. C'est
  le retour arrière le plus rapide si la fusion manuelle introduit une faute de frappe : remplacer
  le fichier fusionné par cette copie plutôt que de retaper la ligne à la main.

---

## 8. Le risque réel : ce qui change sur linstantcle.ch, pas seulement l'hôte de réservation

C'est le point que ce constat doit dire explicitement. `lme-brands` est un **mu-plugin** : il n'a
pas d'étape d'activation, et ses hooks se posent sur des événements de **Vik Booking**, qui est une
installation unique partagée par les deux marques. Rien de ce qui suit n'est scopé à un hôte par
construction — chaque effet est produit par du code qui vérifie une condition à l'exécution, jamais
par une frontière physique entre deux sites.

1. **Les URL de linstantcle.ch passent, dès l'upload, par les mêmes filtres que celles de
   reservation.sexcaperoom.ch** (`option_home`, `option_siteurl`, `content_url`, `upload_dir`,
   `wp_get_attachment_url`, `includes/url-rewrite.php`). Sur l'hôte `linstantcle.ch`, la marque
   résolue est `linstantcle`, dont le `host` déclaré est justement `linstantcle.ch` :
   `lme_brands_swap_url_host()` ne change donc rien, par construction (« ne fait rien si l'URL est
   déjà sur le bon hôte »). L'effet visible est nul, l'effet réel ne l'est pas : chaque page de
   linstantcle.ch exécute désormais ce code à chaque chargement.
2. **Le filtrage de présentation (`includes/room-filter.php`) s'applique aussi sur linstantcle.ch,
   et c'est voulu : c'est la correction d'une fuite déjà documentée.** `constat-perimetre-tunnel.md`
   §6 démontrait que `linstantcle.ch/fr/a-huis-clos/?roomid=2` rendait L'Aparté — une chambre
   étrangère servie sous l'hôte de l'autre marque. Dès l'upload du mu-plugin, un paramètre
   `roomid`/`room_ids`/`category_id` qui viserait une chambre Sexcape Room **sur une page
   linstantcle.ch** sera retiré de la requête et journalisé en avertissement
   (`foreign_room_param_stripped`) — un comportement de pages qui n'existait pas avant ce
   déploiement, sur le site de production principal, pas seulement sur le tunnel.
3. **La garde de réservation (`includes/booking-guard.php`) s'applique à toute création de
   commande Vik, quel que soit l'hôte qui l'a déclenchée**, parce que
   `vikbooking_before_create_booking_record` est un hook de Vik lui-même, pas un hook du thème ou
   d'un contexte d'hôte. Dès l'upload : une tentative de réservation d'une chambre Sexcape Room
   depuis une page linstantcle.ch (aujourd'hui possible, §6 déjà cité) sera **refusée avec un 403**
   au lieu d'aboutir. C'est le correctif recherché, mais c'est un changement de comportement
   fonctionnel immédiat et réel du parcours de réservation de linstantcle.ch, pas seulement de
   celui de Sexcape Room. Une chambre désactivée (`avail = 0`, chambres de test 5 et 6) est
   également refusée dès l'upload, sur **n'importe quel hôte**, y compris `staging10` lui-même,
   sans qu'aucune marque n'ait besoin de se résoudre pour ce refus précis.
4. **Les e-mails de toute réservation linstantcle.ch passent, dès l'upload, par la même réécriture
   que ceux de Sexcape Room** (`includes/mail-brand.php`, `vikbooking_before_send_booking_mail` et
   `vikbooking_before_send_mail`). Vik n'a qu'un seul chemin d'envoi pour les deux marques : ce
   fichier réécrit l'expéditeur, l'objet et le contenu de **chaque** message client d'une
   réservation directe, quelle que soit sa marque, dès que le mu-plugin est en place — pas
   seulement ceux de Sexcape Room. Pour une chambre linstantcle, le résultat attendu est
   `reservations@linstantcle.ch` / « L'Instant Clé » (déjà dans le registre), donc sans changement
   visible si ces valeurs correspondent à ce que Vik pose nativement aujourd'hui — **à vérifier
   avant tout déploiement en production**, pas supposé.
5. **Chaque paiement Stripe de linstantcle.ch reçoit désormais une métadonnée `lme_brand` sur sa
   session** (`includes/payment-brand.php`, `payment_before_begin_transaction_vikbooking`), dès
   l'upload, pour la même raison : le hook ne connaît pas d'hôte, seulement une réservation.
   Additif, sans effet sur le montant ni le comportement du paiement, mais c'est un changement réel
   des sessions Stripe créées par le site principal.
6. **`themes/astra-child/functions.php` porte, après la fusion du chapitre 5, une ligne qui
   s'exécute sur chaque page de linstantcle.ch aussi**, puisque `wp_enqueue_scripts` n'est pas scopé
   à un hôte. `lme_sexcaperoom_current_appearance()` y retourne `null` (la marque `linstantcle` ne
   porte pas de clé `appearance` dans le registre — vérifié dans `config/brands.php`), donc rien ne
   s'enqueue visuellement. Mais c'est le point de plus grand rayon d'action de ce déploiement : une
   erreur PHP dans `inc/sexcaperoom-tunnel.php`, ou dans une fonction de `lme-brands` qu'il appelle,
   casserait **toutes** les pages de linstantcle.ch, pas seulement celles du tunnel — parce que ce
   fichier est chargé par un `require_once` inconditionnel dans le thème actif du site entier.
   C'est la raison de l'ordre du chapitre 4 (le mu-plugin avant le thème) et de la vérification
   « aucune erreur fatale » du chapitre 6, étape 1, avant toute autre chose.

**En une phrase : rien dans ce déploiement n'est isolé à l'hôte de réservation, parce que Vik
Booking et le thème actif sont partagés par construction avec linstantcle.ch. Chaque effet décrit
ci-dessus est soit un no-op vérifié par construction (1, 6 en apparence), soit la correction
recherchée d'une fuite déjà documentée (2, 3), soit un changement additif à vérifier avant la
production (4, 5) — mais aucun n'est scopé à `reservation.sexcaperoom.ch` par le code lui-même.**

---

## 9. Ce que ce constat ne couvre pas

- **La liste blanche d'hôte (G2/G3)** reste un chantier distinct : tant qu'elle n'est pas en
  service, `reservation.sexcaperoom.ch` continue de servir tout le site linstantcle.ch en
  production — une fuite de marque déjà connue (`plan-de-marche.md`, chantier G), pas créée par ce
  déploiement.
- **La recette de bout en bout** (les huit lignes du chapitre B8 du plan de marche) commence une
  fois ce déploiement fait et vérifié : ce document prépare le terrain, il ne l'exécute pas.
- **L'exclusion NitroPack et cache dynamique SiteGround** du tunnel (chapitre 4.6 du brief
  principal) reste à faire séparément, geste de Thomas dans le tableau de bord de chaque service.
- **Les clés Stripe de préproduction.** Avertissement déjà posé dans `plan-de-marche.md` : VikStripe
  sur staging10 utilise aujourd'hui les clés de production, copiées avec la base. Basculer sur les
  clés de test **avant** tout essai de paiement, geste de Thomas, avant la ligne 6 de la recette.

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-20 | Création. Inventaire de déploiement établi par lecture directe du serveur (SSH, lecture seule ; `sir_options` par clé explicite ; `wp-config.php` par nom de constante explicite, jamais lu en entier). Correction du dépôt : `functions.php` et `style.css` d'`astra-child` n'étaient pas des copies du serveur et auraient écrasé en silence une logique de production réelle — corrigés avant ce constat. |
