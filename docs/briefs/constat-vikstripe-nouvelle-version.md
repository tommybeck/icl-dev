# Constat — la « nouvelle version » de VikStripe est la 2.2.4, et elle n'a jamais tourné

**Deux réponses, et la seconde annule la question.**

**Un.** La copie que Thomas a installée n'est pas une version plus récente. Elle déclare `2.2.4`,
comme celle qui tourne en production, et les deux arborescences sont identiques fichier pour
fichier à l'exception de deux détails sans effet à l'exécution : une virgule finale et un garde-fou
de compatibilité dont il est prouvé qu'il ne se déclenche pas sur cette installation. **Le défaut
de réconciliation n'est donc pas corrigé — il ne pouvait pas l'être, puisqu'il n'y a rien de
nouveau.**

**Deux.** Elle n'a pas dysfonctionné : **elle n'a jamais tourné.** Le remplacement a été demandé le
15 septembre 2026 à 09:44:15 UTC et il n'a pas abouti. Les 360 fichiers du greffon en production
portent toujours la date de leur installation du 23 octobre 2025, et les journaux de transaction
que le greffon écrit dans son propre dossier forment une suite ininterrompue depuis le
25 octobre 2025. **Le code de l'archive n'a jamais remplacé celui de la production, et n'a donc pas
pu y produire le moindre symptôme.**

Version 2, 18 septembre 2026. Réponse au prompt du chapitre 5 de
`brief-vikstripe-nouvelle-version.md`.

Lecture seule de bout en bout : aucun fichier de `.local/` modifié, aucun greffon installé,
désinstallé ni déployé, aucune écriture en base, aucune clé Stripe lue ni journalisée.

Documents liés : `brief-vikstripe-nouvelle-version.md`, `constat-incident-1818.md` §1 et §5,
`constat-reserve-paiement.md` §0, `constat-perimetre-tunnel.md` §F,
`constat-phase-4-paiement.md` §2.

**Ce que ce document établit :** la version réelle de la seconde copie, à sa source ; les trois
réponses du chapitre 3.b, lignes des deux copies en regard ; la nature exacte et l'effet des deux
seuls écarts de code ; la fenêtre de la manœuvre du 15 septembre, à la seconde ; et la preuve, par
trois voies indépendantes, que le remplacement n'a pas eu lieu.

**Ce qu'il n'établit pas :** le message d'erreur que Thomas a vu à 09:44:15. WordPress ne
journalise pas l'échec d'une mise à jour manuelle, et la réponse HTTP ne distingue pas un succès
d'un échec. Le mécanisme le plus probable est nommé au §4.3 et donné pour tel. **Ni ce que
l'écriture isolée du 11 décembre 2025 sur `stripe.php` a changé** — voir §4.6, une trouvaille de
chemin qui dépasse ce constat.

---

## Sources et méthode

| Source | Ce qui en a été tiré |
|---|---|
| `.local/wp-vikstripe/` | la 2.2.4 en production, confirmée par B4a et par `constat-reserve-paiement.md` §0 |
| `.local/wp-vikstripe-new/` | la copie installée par Thomas, déposée dans `.local/` le 18 septembre 2026 à 08:09 |
| `diff -rq` puis `diff -u` entre les deux arborescences, 360 fichiers de chaque côté | l'inventaire complet des écarts |
| `shasum -a 256` sur les fichiers cités | l'identité à l'octet près |
| PHP 8.5.10 en local, `-d disable_functions=php_uname` | le comportement réel de PHP face au garde-fou ajouté |
| `~/www/linstantcle.ch/logs/*.gz`, 20 août au 18 septembre 2026, via `ssh sg-linstantcle` | la séquence d'installation du 15 septembre, à la seconde ; l'absence de 5xx |
| `~/www/linstantcle.ch/public_html/wp-content/debug.log` (213 Mo, `WP_DEBUG_LOG` actif) | l'absence d'erreur fatale dans la fenêtre |
| Horodatages et tailles des 360 fichiers du greffon en production, et des 113 `Stripe/*.tx` | la preuve que le remplacement n'a pas eu lieu |
| `php -r 'ini_get("disable_functions")'` sur le serveur | le garde-fou `php_uname` est inerte ici, sur preuve et non par déduction |

L'accès SSH a manqué à la version 1 de ce constat : la clé portait une phrase de passe et n'était
pas chargée dans l'agent, ce que le `BatchMode` des appels masquait en un `Permission denied` qui
ressemblait à un refus du serveur. Rien n'était révoqué. `ssh-add --apple-use-keychain
~/.ssh/icl_ed25519`, une fois par redémarrage, suffit — **à porter dans
`handoff-acces-mysql.md`**, qui décrit la clé sans mentionner la phrase de passe.

La 2.2.4 n'a pas été relue en entier, conformément à la consigne : seules les lignes comparées ont
été rouvertes, et les constats existants ont servi pour tout le reste.

---

## 1. La version, à sa source

Demandée avant toute autre chose par le brief. Relevée dans le fichier, pas d'après le nom du
dossier.

```
.local/wp-vikstripe-new/vikstripe.php:5     Version:      2.2.4
.local/wp-vikstripe-new/vikstripe.php:21    define('VIKSTRIPEVERSION', '2.2.4');
```

À comparer à la production, déjà établie au §0 du constat de réserve :

```
.local/wp-vikstripe/vikstripe.php:5         Version:      2.2.4
.local/wp-vikstripe/vikstripe.php:21        define('VIKSTRIPEVERSION', '2.2.4');
```

**C'est la même version.** L'en-tête et la constante concordent des deux côtés, et donnent la même
valeur. Il n'existe pas, dans ce qui a été installé, de version plus récente de VikStripe.

### Ce que les deux copies déclarent par ailleurs

| Déclaration | `wp-vikstripe` | `wp-vikstripe-new` | Source |
|---|---|---|---|
| Version du greffon | `2.2.4` | `2.2.4` | `vikstripe.php:5` et `:21` |
| Bibliothèque Stripe PHP | `14.6.0` | `14.6.0` | `Stripe/lib/Stripe.php:61` |
| Version d'API Stripe demandée | `2024-04-10` | `2024-04-10` | `Stripe/lib/Util/ApiVersion.php:9` |
| WordPress minimal | `4.0` | `4.0` | `vikstripe.php:57` / `:70`, clé `requires` |
| PHP minimal annoncé au manifeste | `7.0` | `7.0` | `vikstripe.php:59` / `:72`, clé `requires_php` |
| PHP minimal annoncé au `readme.txt` | `5.4.0` | `5.4.0` | `readme.txt`, fichiers identiques |
| Version minimale de Vik Booking | **aucune déclarée** | **aucune déclarée** | recherche dans les deux `vikstripe.php` |

**Aucune des deux copies n'exige une version de Vik Booking.** L'hypothèse « exigence supérieure à
1.8.14 » du brief est écartée : il n'y a pas d'exigence du tout.

La version d'API Stripe est **figée dans la bibliothèque, identique des deux côtés**, et n'est
donc pas un candidat au dysfonctionnement.

### Le journal des versions ne dit rien de plus

`changelog.md` est **identique à l'octet près** entre les deux copies
(`bf9a67a7b3f56feff29d84e11870c97ea740f12757fd5c486a6b30c350ce2f49`) et s'arrête toujours à
« 2.2.3 — *Release date - 17 July 2025* ». L'écart 2 du constat de réserve tient : **l'éditeur ne
documente pas ce que sa 2.2.4 change, et il en a manifestement publié au moins deux constructions
sous le même numéro** — voir §3.

### Datation des deux copies

| Copie | Horodatage des fichiers | Lecture |
|---|---|---|
| `wp-vikstripe/` | `2026-09-15 12:26:24` à `12:27:10`, uniforme | horodatage du rapatriement SFTP |
| `wp-vikstripe-new/` | `2026-05-26 12:14:28`, uniforme sur les 360 fichiers | horodatage de construction de l'archive de l'éditeur, préservé au dépaquetage |
| `wp-vikstripe-new/` (dossier) | `2026-09-18 08:09:35` | dépôt dans `.local/` par Thomas, pour ce travail |

`wp-vikstripe-new/Stripe/` ne contient **aucun fichier `.tx`**, là où la copie de production en
porte 110. C'est la signature d'une archive de distribution dépaquetée, jamais d'une copie prise
sur le serveur. **Le §4.2 tranche ce qu'on ne pouvait pas trancher sans le serveur : cette archive
n'a jamais servi, nulle part, et sa taille caractéristique — `vikstripe.php` de 9 744 octets —
n'existe sur aucun des sites du compte.**

---

## 2. Les trois questions du chapitre 3.b

Préalable qui les commande toutes : `stripe.php` **ne diffère que par une virgule**, et la
numérotation des lignes est **identique** entre les deux copies jusqu'à la ligne 470. Toutes les
lignes citées par `constat-incident-1818.md` pointent donc le même code dans les deux fichiers,
sous le même numéro.

```
diff -rq .local/wp-vikstripe .local/wp-vikstripe-new
  Files wp-vikstripe/stripe.php   and wp-vikstripe-new/stripe.php   differ
  Files wp-vikstripe/vikstripe.php and wp-vikstripe-new/vikstripe.php differ
  (plus 110 fichiers Stripe/*.tx présents seulement en production : des journaux de transaction,
   pas du code)
```

360 fichiers de chaque côté, **358 identiques à l'octet près**, dont `vikbooking/stripe.php`
(`abccaeea3ac07db1f862a909df0b1fc6a4a240417a52330363c71c276b570839` des deux côtés),
`tmpl/success.html.php`, `utils.php` et l'intégralité de la bibliothèque Stripe.

### 2.1 Le test de réutilisation de `createSession()` compare-t-il encore un arrondi à un non-arrondi ?

**Oui. Le code est identique, caractère pour caractère, au même numéro de ligne.**

| `.local/wp-vikstripe/stripe.php` (2.2.4 production) | `.local/wp-vikstripe-new/stripe.php` (2.2.4 installée) |
|---|---|
| `356  $session = get_option("stripe_order_{$this->get('oid')}");` | `356  $session = get_option("stripe_order_{$this->get('oid')}");` |
| `363    $checkout_session = $stripe->checkout->sessions->retrieve($session);` | `363    $checkout_session = $stripe->checkout->sessions->retrieve($session);` |
| `365    if (($checkout_session->amount_total / ($this->getParam('use_decimals', 1) ? 100 : 1) == $this->get('total_to_pay')) && !empty($checkout_session['url']) )` | `365    if (($checkout_session->amount_total / ($this->getParam('use_decimals', 1) ? 100 : 1) == $this->get('total_to_pay')) && !empty($checkout_session['url']) )` |
| `371      delete_option("stripe_order_{$this->get('oid')}");` | `371      delete_option("stripe_order_{$this->get('oid')}");` |
| `376      delete_option("stripe_order_{$this->get('oid')}");` | `376      delete_option("stripe_order_{$this->get('oid')}");` |

Et l'arrondi de l'aller, lui aussi inchangé :

| production | installée |
|---|---|
| `389  $amount_to_pay = round($this->get('total_to_pay'));` | `389  $amount_to_pay = round($this->get('total_to_pay'));` |
| `392    $amount_to_pay = round($this->get('total_to_pay'), 2) * 100;` | `392    $amount_to_pay = round($this->get('total_to_pay'), 2) * 100;` |

La comparaison **n'a pas changé**, donc elle échoue exactement de la même façon. Sur la commande
1818, `253 / 100 == 2.533` reste faux, l'option reste effacée et une session Stripe de plus reste
créée à chaque rendu. **Un total à trois décimales fait toujours échouer le test.**

`diff` sur cette plage, pour preuve négative :

```
diff <(sed -n '570,700p' wp-vikstripe/stripe.php) <(sed -n '570,700p' wp-vikstripe-new/stripe.php)
  → aucune sortie
```

et la seule différence de tout le fichier, 100 lignes plus bas :

```
--- wp-vikstripe/stripe.php
+++ wp-vikstripe-new/stripe.php
@@ -467,7 +467,7 @@
     $config['metadata'] = array_merge(
         $config['metadata'] ?? [],
-        $transaction_metadata,     ← production, ligne 470
+        $transaction_metadata      ← installée, ligne 470
     );
@@ -1134,4 +1134,4 @@
-}\ No newline at end of file
+}
```

**Verdict : le défaut établi au §1.d de `constat-incident-1818.md` est intact.** La ligne 365 est
la même dans les deux copies.

### 2.2 L'option `stripe_order_<id>` est-elle encore une case unique écrasée à chaque rendu ?

**Oui. Six points d'appel, aux mêmes six numéros de ligne, dans les deux copies.**

| Ligne | Appel | production | installée |
|---|---|---|---|
| 356 | `get_option("stripe_order_…")` | ✔ | ✔ |
| 371 | `delete_option("stripe_order_…")` — montant différent | ✔ | ✔ |
| 376 | `delete_option("stripe_order_…")` — exception | ✔ | ✔ |
| 505 | `add_option("stripe_order_…", $checkout_session->id)` | ✔ | ✔ |
| 581 | `get_option("stripe_order_…")` — à la validation | ✔ | ✔ |
| 638 | `delete_option("stripe_order_…")` | ✔ | ✔ |

Une seule clé par commande, écrite par `add_option` après un `delete_option` — il n'y a toujours
pas de `update_option`, et surtout **aucune structure qui garderait plusieurs sessions par
commande**. Le §2.b du constat de la 1818 tient mot pour mot : c'est la dernière session créée qui
gagne, et les précédentes sont perdues pour le site.

**La nouvelle version ne garde pas plusieurs sessions par commande.**

### 2.3 Un webhook est-il enfin appelé, et sur quels événements ?

**Non. Aucun.** Recherche dans toute la copie installée, bibliothèque Stripe exclue :

```
grep -rn "Webhook|constructEvent|register_rest_route|wp_ajax_|admin_post_" \
     wp-vikstripe-new/*.php wp-vikstripe-new/vikbooking/ wp-vikstripe-new/tmpl/
  → aucune correspondance
```

Les quatre fichiers de webhook relevés par l'écart 3 du constat de réserve —
`Stripe/lib/Webhook.php`, `Stripe/lib/WebhookEndpoint.php`, `Stripe/lib/WebhookSignature.php`,
`Stripe/lib/Service/WebhookEndpointService.php` — sont présents dans la copie installée, **à
l'octet près les mêmes qu'en production**, et **toujours jamais appelés**. Ils sont chargés par
l'amorce de la bibliothèque officielle et rien d'autre.

Il n'y a donc **aucun événement** à énumérer, et `checkout.session.completed` n'en fait pas partie.

Le retour de paiement reste entièrement porté par le navigateur du client, par le crochet
`payment_on_after_validation_vikbooking` de `vikbooking/stripe.php:223-242` — fichier dont
l'empreinte est identique dans les deux copies. La conclusion du §F de
`constat-perimetre-tunnel.md` est inchangée.

**Conséquence directe : la parade K du §5 de `constat-incident-1818.md` reste nécessaire.** Rien
dans ce qui a été installé ne la rend superflue.

---

## 3. Les deux seuls écarts de code, et pourquoi aucun n'explique une panne

### 3.1 Écart A — une virgule finale, `stripe.php:470`

```
production  470    $transaction_metadata,
installée   470    $transaction_metadata
```

Une virgule finale dans une **liste d'arguments d'appel** est légale depuis PHP 7.3 et illégale
avant — elle y produit une erreur d'analyse, donc une page blanche. Sur cette installation, en
PHP 8.2.33, **les deux formes s'analysent et s'exécutent identiquement**. `array_merge()` reçoit
exactement les deux mêmes arguments.

Écart annexe : la copie de production se termine par `}` sans saut de ligne final, la copie
installée par `}` suivi d'un saut de ligne. Sans effet — c'est même la forme la plus sûre, un
octet parasite après le `?>` absent étant impossible dans les deux cas.

### 3.2 Écart B — un garde-fou `php_uname`, `vikstripe.php:37-49`

Présent **seulement dans la copie installée**, à l'intérieur de la fermeture accrochée à `init`
en priorité 1 :

```
.local/wp-vikstripe-new/vikstripe.php:38-49
    /**
     * Prevents Stripe from breaking on some hosting environments where the php_uname function is disabled.
     * Indeed, the Stripe library uses this function to obtain the server information and generate a unique
     * identifier for the server, but if the function is disabled, it can cause errors.
     */
    if (!function_exists('php_uname'))
    {
        function php_uname($mode = 'a')
        {
            return PHP_OS;
        }
    }
```

La copie de production passe directement de la ligne 36 (`}`) à la ligne 37 (`}, 1);`).
C'est l'unique raison de l'écart de taille, 9 744 octets contre 9 335.

**Sur cette installation, le bloc est mort, et c'est mesuré, pas déduit :**

```
ssh sg-linstantcle "php -r 'echo ini_get(\"disable_functions\"); var_dump(function_exists(\"php_uname\"));'"
  → disable_functions=[]
  → bool(true)
```

`disable_functions` est **vide**, `php_uname` existe, donc `!function_exists('php_uname')` est
faux et **rien n'est jamais déclaré**. Le garde-fou de l'archive ne se serait pas déclenché une
seule fois. Il ne peut pas être la cause de quoi que ce soit ici.

**Et même s'il s'était déclenché, il n'aurait rien cassé.** Les deux cas, pour mémoire :

1. **`php_uname` n'est pas désactivée** — c'est le cas de ce serveur, ci-dessus. Le bloc est mort.
2. **`php_uname` est désactivée**, sur un autre hébergement. `function_exists()` retourne alors
   `false`, et la fonction de
   remplacement est déclarée. J'ai vérifié que **PHP l'accepte**, plutôt que de le supposer :

   ```
   php -d disable_functions=php_uname -r '
     var_dump(function_exists("php_uname"));
     if (!function_exists("php_uname")) { function php_uname($mode="a"){ return PHP_OS; } }
     var_dump(php_uname());'
   → bool(false)
   → string(6) "Darwin"
   ```

   Aucune erreur fatale, aucun `Cannot redeclare`. La déclaration aboutit et la fonction répond.
   Test mené sur PHP 8.5.10, postérieur au 8.2.33 du serveur ; aucune version de PHP n'a jamais
   interdit cela, une fonction désactivée étant retirée de la table des fonctions.

**Conclusion des deux écarts : à l'exécution, sur cette installation, les deux copies se
comportent exactement pareil.** Il n'existe pas, dans le code, de chemin qui diffère.

### 3.3 Laquelle des deux constructions est la plus récente

Le brief pose la question implicitement en appelant l'une « nouvelle ». Les deux portent `2.2.4`,
donc le numéro ne tranche pas. **Un seul indice tient, et il porte sur `vikstripe.php` :**

- le `vikstripe.php` de production date du 23 octobre 2025 et n'a jamais été retouché depuis
  (§4.2) ; celui de l'archive est daté du 26 mai 2026 et porte le garde-fou `php_uname` en plus.
  Un garde-fou de compatibilité d'hébergement est un ajout, pas un retrait. **L'archive est donc
  la construction la plus tardive de ce fichier-là.**

**L'argument de la virgule, lui, ne tient plus.** La version 1 de ce constat y voyait une seconde
preuve : retirer une virgule finale serait la correction qu'un éditeur publie, puisqu'elle
**casse le greffon sur PHP 7.0 à 7.2**, que le manifeste déclare pourtant supporter
(`requires_php => 7.0`). Mais le §4.6 établit que le `stripe.php` de production a été réécrit
seul, le 11 décembre 2025, hors de toute mise à jour. **La virgule peut venir de cette écriture-là
aussi bien que d'une construction antérieure de l'éditeur, et rien sur ce serveur ne permet de
choisir.** Le point est laissé ouvert.

**Lecture retenue : E4J a republié sa 2.2.4 avec deux correctifs de compatibilité d'hébergement,
sans changer le numéro de version et sans tenir son journal.** C'est cohérent avec l'écart 2 du
constat de réserve, et cela veut dire qu'**une mise à jour depuis vikwp.com peut à tout moment
remplacer le fichier sans que rien ne signale le changement**. À garder en tête pour la recette.

**Ce point est une lecture, pas un fait établi.** L'ordre de publication ne se prouve pas sans
l'éditeur ; seul le contenu des deux fichiers l'est.

---

## 4. Comment et pourquoi la copie installée a dysfonctionné

**Établi : elle n'a jamais dysfonctionné, parce qu'elle n'a jamais tourné. Le remplacement a été
demandé le 15 septembre 2026 à 09:44:15 UTC, et il n'a pas abouti. Les fichiers du greffon en
production n'ont pas bougé depuis son installation du 23 octobre 2025.**

La version 1 de ce constat disait ne pas pouvoir établir ce point, faute d'accès au serveur.
L'accès rétabli, il s'établit — et la réponse n'est aucune de celles que j'avais envisagées.

### 4.1 La fenêtre, à la seconde

Relevée dans le journal d'accès de `linstantcle.ch`, qui couvre du 20 août au 18 septembre 2026.
Une seule séquence d'installation manuelle de greffon existe sur cette période en dehors de celle
d'`emcp-pro` du 10 septembre :

```
15/Sep/2026:09:43:46 +0000  200      52  POST /wp-admin/admin-ajax.php   ref=…/plugin-install.php
15/Sep/2026:09:44:05 +0000  200  115731  POST /wp-admin/update.php?action=upload-plugin
                                          ref=…/plugin-install.php
15/Sep/2026:09:44:05 +0000  200       0  POST /wp-admin/update.php?action=upload-plugin
15/Sep/2026:09:44:15 +0000  200  115340  GET  /wp-admin/update.php?action=upload-plugin
                                               &package=6609&overwrite=update-plugin&_wpnonce=2ded33419b
15/Sep/2026:09:44:44 +0000  200  122737  GET  /wp-admin/plugin-install.php
                                          ref=…&overwrite=update-plugin&_wpnonce=2ded33419b
```

Lecture, pas à pas :

- **09:44:05** — Thomas téléverse une archive depuis `plugin-install.php`. WordPress constate que
  le dossier `wp-vikstripe` existe déjà et affiche l'écran de comparaison « ce greffon est déjà
  installé », qui propose de remplacer l'existant par le téléversé ;
- **09:44:15** — il confirme. Le paramètre `overwrite=update-plugin` est la confirmation du
  remplacement, `package=6609` désigne l'archive téléversée. **C'est l'instant de la manœuvre ;**
- **09:44:44** — vingt-neuf secondes plus tard, il revient sur `plugin-install.php`, et **plus
  aucune requête de greffon n'existe ce jour-là ni les suivants**. Pas de seconde tentative, pas
  d'activation, pas de désactivation, aucune restauration par l'interface.

**La fenêtre est donc 09:44:15 UTC, et elle s'est refermée en quelques secondes.**

### 4.2 Le remplacement n'a pas eu lieu : les fichiers n'ont pas bougé

C'est le fait central, et il est massif.

| Constat sur le serveur | Valeur |
|---|---|
| Fichiers du greffon, hors `.tx` | **359 datés du 23 octobre 2025**, 1 daté du 11 décembre 2025 |
| `vikstripe.php` en production | **9 335 octets**, `2025-10-23 18:26:22` — c'est la construction **sans** le garde-fou `php_uname` |
| Taille du `vikstripe.php` de l'archive | **9 744 octets**. Cette taille n'existe nulle part sur le compte |
| Dossier `wp-vikstripe/` | `2025-10-23 18:26:24` |
| Toute copie de `vikstripe.php` sur le compte | deux, `linstantcle.ch` et `staging10`, **toutes deux 9 335 octets** |

**Aucun fichier du greffon ne porte la date du 15 septembre 2026.** Si le remplacement avait
abouti, les 360 fichiers porteraient cette date, comme `emcp-pro` porte `2026-09-15 06:39:36`
depuis sa mise à jour du même matin. **Les horodatages sont donc bien significatifs sur cette
installation, et ils disent que VikStripe n'a pas été réécrit.**

Deuxième preuve, indépendante et plus forte encore : **les 113 fichiers `Stripe/*.tx` écrits par le
greffon dans son propre dossier forment une suite ininterrompue du 25 octobre 2025 au
17 septembre 2026**, dont trois du 15 septembre lui-même :

```
2025-10-25 20:06:01   Stripe/705402171-1225.tx     ← le plus ancien
2026-09-15 03:57:39   Stripe/2033980266-1813.tx    ← avant la manœuvre
2026-09-15 10:06:04   Stripe/1531728432-1815.tx    ← 22 minutes après
2026-09-15 10:08:32   Stripe/579718074-1816.tx
2026-09-17 10:50:54   Stripe/1651018164-1818.tx    ← le troisième rendu de la 1818
```

Une installation de greffon par WordPress **supprime le dossier de destination avant de
dépaqueter**. Les fichiers d'octobre 2025 auraient disparu. Ils sont là. **Le dossier n'a jamais
été détruit.**

### 4.3 Ce qui s'est passé, très probablement, et ce que je ne prouve pas

**Le mécanisme le plus probable est le retour arrière automatique de WordPress.** Depuis la 6.3,
`WP_Upgrader` déplace le dossier existant vers `wp-content/upgrade-temp-backup/plugins/<greffon>`
avant de dépaqueter, et **le redéplace à sa place si la mise à jour échoue**. Un déplacement
conserve les horodatages et le contenu — donc exactement ce qu'on observe : mêmes dates, mêmes
`.tx`. Le répertoire `upgrade-temp-backup/plugins/` est aujourd'hui vide, comme après un cycle
terminé.

**Ce que je ne prouve pas :** le message d'erreur que Thomas a vu à 09:44:15. WordPress ne
journalise pas l'échec d'une mise à jour **manuelle** dans `debug.log`, et la réponse HTTP était un
`200` de 115 340 octets, taille d'une page d'administration ordinaire, qui ne distingue pas un
succès d'un échec. Le retour sur `plugin-install.php` vingt-neuf secondes plus tard, sans nouvelle
tentative, est cohérent avec un échec ; il ne le démontre pas.

**Ce qui est en revanche démontré, et qui suffit :** quelle qu'ait été la cause, **le code de
l'archive n'a pas remplacé celui de la production**, et il n'a donc pas pu y produire le moindre
symptôme.

### 4.4 Aucune erreur, nulle part, dans la fenêtre

Trois recherches, toutes vides :

| Recherche | Résultat |
|---|---|
| Erreurs PHP fatales dans `wp-content/debug.log` le 15 septembre | **aucune.** 22 lignes pour la journée entière, rien entre 06:39:34 et 10:11:42 |
| Réponses HTTP 5xx le 15 septembre | **aucune** |
| Mentions de `wp-vikstripe` dans `debug.log` ce jour-là | deux avertissements `Undefined array key "guest_name"` à `stripe.php:326`, à **03:57:35** et **20:25:01** — le comportement ordinaire du greffon, avant et après la manœuvre |

L'avertissement de 20:25:01 est le même que celui de 03:57:35, sur la même ligne du même fichier.
**Le greffon fonctionnait normalement le soir du 15 septembre, avec le code qu'il avait le matin.**

### 4.5 Les pistes du brief, refermées une à une

| Piste du brief | Résultat |
|---|---|
| Nom de crochet changé | **aucun.** Les 15 `add_action`/`add_filter` de `vikstripe.php` et les 4 de `vikbooking/stripe.php` portent les mêmes noms, dans le même ordre, seulement décalés de 13 lignes |
| Crochet supprimé | **aucun.** Même nombre, même cible |
| `payment_on_after_validation_vikbooking` | **identique.** `vikbooking/stripe.php:223`, fichier identique à l'octet près |
| `payment_before_begin_transaction_vikbooking`, utilisé par `lme-brands/includes/payment-brand.php` | **inchangé.** Il vit dans le cœur de Vik (`payment.php:329`), que ce greffon ne touche pas |
| Signature de rappel modifiée | **aucune** |
| Version d'API Stripe incompatible | **écartée.** `2024-04-10` des deux côtés, figée dans la bibliothèque, elle-même identique |
| Exigence de version de Vik Booking | **écartée.** Aucune des deux copies n'en déclare |
| Fonction absente sous PHP 8.2.33 | **écartée, et désormais sur preuve serveur** : `disable_functions` est **vide** et `function_exists('php_uname')` vaut `true` sur cette installation. Le garde-fou de l'archive ne se serait jamais déclenché |
| Canal `tn_metadata` de la phase 4 | **intact.** `stripe.php:454`, `:967`, `:973`, identiques |

### 4.6 Une trouvaille en chemin : `stripe.php` a été réécrit seul, le 11 décembre 2025

Sur les 360 fichiers du greffon en production, **359 portent la date d'installation du
23 octobre 2025. Un seul fait exception :**

```
2025-12-11 11:12:36.330269044   33202 octets   wp-vikstripe/stripe.php
```

Sept semaines après l'installation, **et lui seul**. Une mise à jour de greffon réécrit tous les
fichiers : les autres greffons du site le confirment, leurs dates sont uniformes par mise à jour.
Un fichier isolé qui bouge dans un greffon tiers, c'est une écriture hors mise à jour — ce que la
règle absolue n°1 interdit. `staging10` porte le même fichier **à la nanoseconde près**, ce qui
situe la copie de préproduction après cette écriture.

**Ce que je ne peux pas dire :** ce que cette écriture a changé. La construction d'origine du
23 octobre 2025 n'existe plus nulle part sur le compte, et les journaux ne remontent qu'au
20 août 2026.

**Ce que je peux borner, et qui rassure :** le `stripe.php` de production ne diffère du
`stripe.php` de l'archive E4J de mai 2026 **que par une virgule finale et un saut de ligne**
(§3.1). Quoi qu'il se soit passé le 11 décembre 2025, le fichier en est ressorti fonctionnellement
identique à ce que publie l'éditeur. **En particulier, la ligne 365 fautive est mot pour mot celle
de l'archive E4J : c'est bien un défaut de l'éditeur, pas un artefact local.** Le signalement à
E4J ne risque donc pas de porter sur du code modifié chez nous.

À verser au journal, et à éclaircir par Thomas s'il s'en souvient.


## 5. Les deux questions ouvertes du chapitre 3.d

Le brief demandait de ne les traiter que si les réponses tombaient sous les yeux. Elles y sont
tombées, et les deux réponses sont négatives par construction, les fichiers concernés étant
identiques.

**Critère de recette n°8 de la phase 4** (`constat-phase-4-paiement.md` §2) — **reste ouvert.**
Aucune des deux copies ne mentionne `statement_descriptor_suffix` :

```
grep -rn "statement_descriptor" wp-vikstripe*/stripe.php wp-vikstripe-new/vikbooking/stripe.php
  → aucune correspondance
```

Quant à `payment_intent_data.metadata`, il existe dans les deux (`stripe.php:459-461`) mais ne
porte que le paramètre global `transaction_metadata` du réglage d'administration, **le même pour
toutes les transactions**. Le seul canal par transaction reste `tn_metadata` (`stripe.php:454`),
déjà celui qu'emploie la phase 4, et il n'alimente que `$config['metadata']` de la session, pas
celui du `payment_intent`. **Rien de nouveau : le critère n'est pas redevenu atteignable.**

**La réserve de paiement** (`constat-reserve-paiement.md`) — **non, rien n'est rattrapé.** La copie
installée ne rattrape pas davantage un paiement dont le navigateur ne revient jamais : pas de
webhook (§2.3), `validateTransaction()` identique (plage 570-700 vérifiée sans écart), aucune tâche
planifiée, aucune route enregistrée. Le §4 du constat de réserve tient intégralement.

---

## 6. Recommandation

Une seule, parmi les quatre du chapitre 4 du brief.

> ### **3. Ne pas mettre à jour, le défaut n'est pas corrigé, et écrire à E4J en citant la ligne.**

**Pourquoi celle-là.** La question qui bloquait le signalement était : « l'éditeur a-t-il déjà
corrigé ? » Elle est tranchée, et sans réserve — ce qui a été installé est la **même version
2.2.4**, portant **la même ligne 365**, avec **la même case unique** et **toujours aucun webhook**.
Écrire à E4J n'est donc ni inutile ni embarrassant : c'est la seule voie, et le signalement peut
citer `stripe.php:365` tel quel, avec le correctif de deux lignes proposé au §1.d du constat de la
1818.

**« Ne pas mettre à jour » est ici presque une tautologie, et c'est ce qui rend la
recommandation sûre :** il n'y a rien à gagner, puisqu'il n'y a rien de nouveau. Le seul apport
réel de l'autre construction est un garde-fou dont on a mesuré qu'il ne se déclenche pas ici, et
une virgule sans effet en PHP 8.2. Le risque, lui, est celui de toute manipulation d'un greffon de
paiement en production.

**Ce n'est pas la recommandation n°2**, qui demanderait d'écrire aussi à E4J sur une
incompatibilité de la nouvelle version. **Le §4 l'exclut désormais formellement** : le code de
l'archive n'a jamais été en place sur ce serveur, donc aucune incompatibilité n'a pu s'y
manifester. Signaler une incompatibilité à E4J serait factuellement faux et affaiblirait le
signalement du vrai défaut.

**Ce n'est pas la recommandation n°4.** La version 1 de ce constat hésitait, faute des journaux.
Ils ont été lus : la fenêtre est datée à la seconde, le remplacement est établi comme n'ayant pas
eu lieu, et il ne manque plus rien qui puisse changer la décision.

**Ce que la recommandation n'autorise pas.** Elle ne demande aucun déploiement, aucune
désinstallation, aucun changement en préproduction. **Elle ne demande pas non plus de refaire la
mise à jour du 15 septembre pour voir ce qui se passe** : l'archive n'apporte rien, et la retenter
sur un greffon de paiement en production serait un risque pris sans contrepartie.

**Ce qui reste à Thomas, hors recommandation.**

1. **Éclaircir l'écriture du 11 décembre 2025 sur `stripe.php`** (§4.6). C'est le seul point
   inquiétant de ce constat : un fichier de greffon tiers réécrit seul, hors mise à jour, contre
   la règle absolue n°1. Le fichier en est ressorti fonctionnellement identique à celui de
   l'éditeur, donc rien n'est cassé aujourd'hui — mais une mise à jour de VikStripe écrasera cette
   écriture sans prévenir, et personne ne saura ce qui aura disparu. **Si vous vous en souvenez,
   inscrivez-le dans `journal-vik.md` ; sinon, notez qu'on ne sait pas.**
2. **Inscrire la manœuvre du 15 septembre 2026 dans `journal-vik.md`** : téléversement et
   confirmation de remplacement à 09:44:15 UTC, sans effet. Le journal n'en porte aucune trace,
   alors qu'un remplacement de greffon de paiement en production est exactement ce qu'il existe
   pour retenir. C'est ce trou qui a coûté ce constat.
3. **Charger la clé SSH dans l'agent** à chaque redémarrage du Mac,
   `ssh-add --apple-use-keychain ~/.ssh/icl_ed25519`, et **le porter dans
   `handoff-acces-mysql.md`**, qui décrit la clé et les raccourcis sans mentionner la phrase de
   passe. Sans l'agent, les journaux et la base sont hors d'atteinte, et ce constat l'a appris à
   ses dépens.

---

## Commandes exécutées

Toutes en lecture seule, le 18 septembre 2026. Aucune n'écrit, aucune ne touche `.local/`, aucune
ne lit ni ne journalise une clé Stripe.

```bash
# Version à sa source, et déclarations du manifeste
sed -n '1,40p' .local/wp-vikstripe-new/vikstripe.php
sed -n '1,40p' .local/wp-vikstripe/vikstripe.php
grep -n "requires\|Requires\|VikUpdater" .local/wp-vikstripe{,-new}/vikstripe.php
grep -n "const VERSION"  .local/wp-vikstripe{,-new}/Stripe/lib/Stripe.php
grep -n "CURRENT"        .local/wp-vikstripe{,-new}/Stripe/lib/Util/ApiVersion.php

# Inventaire complet des écarts
diff -rq .local/wp-vikstripe .local/wp-vikstripe-new
diff -u  .local/wp-vikstripe/stripe.php    .local/wp-vikstripe-new/stripe.php
diff -u  .local/wp-vikstripe/vikstripe.php .local/wp-vikstripe-new/vikstripe.php
find .local/wp-vikstripe -type f | grep -v '\.tx$' | wc -l   # 360
find .local/wp-vikstripe-new -type f | wc -l                  # 360

# Identité à l'octet près des fichiers cités par les constats
shasum -a 256 .local/wp-vikstripe{,-new}/{stripe.php,utils.php,changelog.md,vikstripe.php}
shasum -a 256 .local/wp-vikstripe{,-new}/vikbooking/stripe.php
shasum -a 256 .local/wp-vikstripe{,-new}/tmpl/success.html.php

# Les trois questions du 3.b
sed -n '356,377p' .local/wp-vikstripe{,-new}/stripe.php
sed -n '385,395p' .local/wp-vikstripe{,-new}/stripe.php
grep -n "stripe_order" .local/wp-vikstripe{,-new}/stripe.php
grep -rn "Webhook\|constructEvent\|register_rest_route\|wp_ajax_\|admin_post_" \
     .local/wp-vikstripe-new/*.php .local/wp-vikstripe-new/vikbooking/ .local/wp-vikstripe-new/tmpl/
diff <(sed -n '570,700p' .local/wp-vikstripe/stripe.php) \
     <(sed -n '570,700p' .local/wp-vikstripe-new/stripe.php)

# Le 3.d
grep -rn "statement_descriptor" .local/wp-vikstripe{,-new}/stripe.php
grep -n  "tn_metadata\|metadata" .local/wp-vikstripe-new/stripe.php

# Datation des copies
stat -f "%Sm %N" -t "%F %T" .local/wp-vikstripe{,-new} .local/wp-vikstripe{,-new}/Stripe

# Comportement réel de PHP face au garde-fou ajouté
php -d disable_functions=php_uname -r '
  var_dump(function_exists("php_uname"));
  if (!function_exists("php_uname")) { function php_uname($mode="a"){ return PHP_OS; } }
  var_dump(php_uname());'

# Diagnostic de l'accès SSH manquant à la version 1
ssh -vvv -o BatchMode=yes sg-linstantcle "echo ok"   # → "Server accepts key", puis pas de signature
nc -z -v -w 8 gfram1004.siteground.biz 18765         # → succeeded : le réseau n'est pas en cause
ssh-keygen -y -P "" -f ~/.ssh/icl_ed25519            # → incorrect passphrase : la clé est chiffrée
ssh-add --apple-load-keychain ; ssh-add -l           # → agent vide, rien dans le trousseau
ssh-add --apple-use-keychain ~/.ssh/icl_ed25519      # → par Thomas, et l'accès revient
```

Toutes les commandes ci-dessous sont en lecture seule sur le serveur : ni `rm`, ni `mv`, ni
`cp`, ni redirection, ni `mysql`, ni lecture d'un fichier de configuration.

```bash
# La fenêtre du 15 septembre, et toute installation manuelle de greffon sur un mois
ssh sg-linstantcle "zcat ~/www/linstantcle.ch/logs/*.gz | grep -a 'upload-plugin'"
ssh sg-linstantcle "zcat ~/www/linstantcle.ch/logs/linstantcle.ch-2026-09-16.gz \
  | grep -a '15/Sep/2026:09:4' | grep -a 'wp-admin'"

# Le remplacement n'a pas eu lieu : dates, tailles, et journaux de transaction
ssh sg-linstantcle "ls -la --time-style=full-iso \
  ~/www/linstantcle.ch/public_html/wp-content/plugins/wp-vikstripe/"
ssh sg-linstantcle "find ~/www/linstantcle.ch/public_html/wp-content/plugins/wp-vikstripe \
  -type f ! -name '*.tx' -printf '%TY-%Tm-%Td\n' | sort | uniq -c"
ssh sg-linstantcle "find ~/www -maxdepth 6 -name 'vikstripe.php' -printf '%T+  %s  %p\n'"
ssh sg-linstantcle "ls -la --time-style=full-iso \
  ~/www/linstantcle.ch/public_html/wp-content/plugins/wp-vikstripe/Stripe/*.tx"
ssh sg-linstantcle "ls -la --time-style=full-iso \
  ~/www/linstantcle.ch/public_html/wp-content/plugins/emcp-pro/"   # témoin : les dates sont fiables
ssh sg-linstantcle "ls -laR --time-style=full-iso \
  ~/www/linstantcle.ch/public_html/wp-content/upgrade-temp-backup/plugins/"

# Aucune erreur dans la fenêtre
ssh sg-linstantcle "grep -a '15-Sep-2026' ~/www/linstantcle.ch/public_html/wp-content/debug.log"

# Le garde-fou php_uname est inerte ici
ssh sg-linstantcle "php -r 'echo ini_get(\"disable_functions\"); var_dump(function_exists(\"php_uname\"));'"
```

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-18 | Création, en réponse au chapitre 5 de `brief-vikstripe-nouvelle-version.md`. §4 laissé ouvert faute d'accès au serveur, attribué à tort à une révocation de clé chez SiteGround. |
| 2.0 | 2026-09-18 | Accès SSH rétabli — la clé portait une phrase de passe, rien n'était révoqué. §4 entièrement réécrit sur les journaux et les horodatages du serveur : la fenêtre est datée à 09:44:15 UTC le 15 septembre, et **le remplacement n'a pas eu lieu**. Preuve serveur que le garde-fou `php_uname` est inerte ici (§3.2). Argument de la virgule retiré du §3.3. Nouvelle trouvaille : `stripe.php` réécrit seul le 11 décembre 2025 (§4.6). Recommandation inchangée, n°3, et n°2 désormais formellement exclue. |
