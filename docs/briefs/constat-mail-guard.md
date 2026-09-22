# Constat — écriture de `mu-plugins/lme-mail-guard/`

22 septembre 2026. Réponse au prompt de lancement de
`docs/briefs/brief-redirection-emails-hors-production.md` chapitre 5.
Lu en entier avant d'écrire une ligne : ce brief, `docs/briefs/constat-signatures-crochets.md`
et `docs/briefs/constat-deploiement-moteur.md` chapitre 8. Rien n'est
déployé par ce constat, aucune ligne n'est écrite en base, aucune clé n'a
été lue : les seules lectures faites sont en lecture seule par SSH
(`sg-linstantcle`), sur `wp-includes/pluggable.php` et sur la structure de
répertoires du serveur, et par `curl -I` sur des URL publiques — jamais un
secret, jamais `wp-config.php`.

**Addendum du même jour (chapitre 10) : levée de la réserve bloquante de
plan-de-marche.md §B9e.** Mêmes garanties, avec une lecture en plus :
`wp-config.php`, en production, mais **par clé nommée explicite
uniquement** — `DISABLE_WP_CRON` et `WP_ENVIRONMENT_TYPE`, jamais un
affichage du fichier ni une clé au hasard — conformément à la règle
absolue n°2 de `CLAUDE.md`. Aucune des deux n'y est définie ; ni l'une ni
l'autre n'est un secret. Le reste des lectures de l'addendum porte sur du
code public du cœur WordPress (`wp-includes/cron.php`,
`wp-includes/load.php`) et de VikBooking
(`wp-content/plugins/vikbooking/libraries/system/cron.php`), toutes en
lecture seule par SSH.

---

## 1. La forme réelle de `$args['headers']`, établie en lisant `wp_mail()` du cœur

Le brief demandait explicitement de ne pas supposer cette forme, « comme
B9d l'a fait pour les crochets ». Même méthode ici : lecture directe de
`wp_mail()` sur le serveur (`~/www/linstantcle.ch/public_html/wp-includes/pluggable.php`,
WordPress 6.9, PHP 8.2.33, lu le 22 septembre 2026), pas une hypothèse.

Trois faits qui gouvernent tout `mu-plugins/lme-mail-guard/includes/core.php` :

1. **`apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments', 'embeds' ) )`**
   (`pluggable.php:206`) — un seul argument livré au filtre `wp_mail`, le
   tableau `$atts` complet. `$headers` y est **exactement** ce que
   l'appelant a passé à `wp_mail()`, pas encore normalisé : chaîne vide
   par défaut (signature `$headers = ''`), chaîne à une ou plusieurs
   lignes séparées par `"\r\n"` ou `"\n"`, ou déjà un tableau de lignes
   `"Nom: valeur"`. **Jamais un tableau associatif indexé par nom
   d'en-tête** — la normalisation en tableau associatif
   (`$headers[ trim($name) ] = trim($content)`) est un effet interne de
   `wp_mail()`, plus bas dans la fonction, jamais ce que le filtre reçoit
   ni ce qu'il doit rendre.
2. **`apply_filters( 'pre_wp_mail', null, $atts )`** (`pluggable.php:225`),
   présent depuis WordPress 5.7.0 et confirmé sur cette installation :
   rendre autre chose que `null` court-circuite `wp_mail()`, qui retourne
   alors cette valeur sans jamais construire ni envoyer de message. C'est
   le seul moyen natif d'« abandonner l'envoi », exigé par le brief pour
   le cas où l'adresse fourre-tout n'est pas configurée — pas une
   invention de ce greffon.
3. **`Cc:` et `Bcc:` sont extraits des lignes d'en-tête par leur nom
   exact**, insensible à la casse (`switch ( strtolower( $name ) ) { case 'cc': …
   case 'bcc': … }`, `pluggable.php:367-372`), puis pilotent directement
   `$phpmailer->addCC()`/`addBCC()` plus bas. Retirer une ligne `Cc:`/`Bcc:`
   du tableau qu'on rend au filtre `wp_mail` **suffit** à empêcher tout
   envoi de copie — confirmé en lisant jusqu'au bout, pas supposé.

Ces trois faits sont documentés dans `includes/core.php`, à côté de
chaque fonction qu'ils gouvernent.

---

## 2. Les quatre défauts du brief, corrigés

### 1.a — la faute de frappe sur l'hôte du tunnel

`reservation.sexcaperoom.ch` (singulier) est dans la liste,
`reservations.sexcaperoom.ch` (pluriel) n'y est pas. Testé en régression
(`tests/test-core.php`, « la faute de frappe … ne matche toujours pas »).

### 1.b — les liens Markdown au lieu de noms d'hôte

`lme_mail_guard_default_production_hosts()` (`includes/core.php`) ne
contient que des noms d'hôte nus, jamais de balise Markdown.

### 1.c — l'erreur fatale sur `headers` vide

`lme_mail_guard_normalize_headers()` normalise **inconditionnellement**,
avant toute lecture — jamais un `if ( ! empty(...) )` suivi d'un accès en
tableau. Testé explicitement avec `''` (le cas le plus fréquent de
`$args['headers']`, chapitre 1 ci-dessus) : produit un tableau vide, sans
erreur.

### 1.d — le Bcc qui fuyait malgré le commentaire

Défaut le plus dangereux du brief, parce que silencieux. Le code proposé
gardait une ligne dès que `stripos( trim( $h ), 'cc:' ) !== 0` — un test
de **position** d'une sous-chaîne, pas de nom d'en-tête. Sur
`"Bcc: espion@example.ch"`, la sous-chaîne `"cc:"` apparaît en position 1
(juste après le `B`), donc le test rend `1`, différent de `0`, et la
ligne est conservée.

`lme_mail_guard_strip_cc_bcc()` compare le **nom** de l'en-tête (la partie
avant le premier deux-points, entière, insensible à la casse) à `'cc'` et
`'bcc'` — jamais une sous-chaîne trouvée n'importe où dans la ligne. Testé
en régression avec exactement le cas qui a cassé le code proposé
(`"Bcc: espion@example.ch"`, retiré), et avec un cas piège
supplémentaire, une valeur d'en-tête qui contient littéralement `"cc:"`
(`"X-Debug: cc: ceci ne commence pas par cc"`, conservé) — pour vérifier
que la correction ne bascule pas dans l'excès inverse.

---

## 3. Les deux durcissements du chapitre 2

### 2.a — deux conditions, jamais une seule

`lme_mail_guard_is_production_context()` exige `wp_get_environment_type() === 'production'`
**et** l'hôte de la requête dans la liste de production. L'hôte vient de
`$_SERVER['HTTP_HOST']` normalisé (`includes/guard.php`,
`lme_mail_guard_current_host()`), jamais de `option_home` ni d'aucune
valeur qu'un autre greffon pourrait réécrire.

L'échappatoire `FORCE_EMAIL_REDIRECT` est gardée, sous le même nom, et
compose par-dessus : à `true`, elle force le détournement même si les
deux conditions de production sont réunies —
`lme_mail_guard_should_redirect()`.

### 2.b — une redirection en production doit être bruyante

`lme_mail_guard_maybe_warn_brand_host()` (`includes/guard.php`) journalise
un avertissement (`production_brand_host_redirected`) chaque fois qu'un
message est détourné **ou abandonné** alors que l'hôte de la requête
résout une marque du registre `lme-brands`
(`lme_brands_current_request_brand_key()`, appelée seulement si elle
existe — couplage faible, `function_exists()`, jamais un `require` : ce
greffon fonctionne seul, sans `lme-brands`).

Couvre aussi le cas que le brief ne nomme pas explicitement mais que la
règle absolue n°6 de `CLAUDE.md` exige : un envoi **abandonné** faute
d'adresse fourre-tout (chapitre 5 ci-dessous) journalise toujours une
**erreur** (`catchall_not_configured`), jamais un échec silencieux —
c'est un chemin qui se termine par un e-mail non envoyé, donc par une
erreur journalisée qui alerte, au sens strict de cette règle.

---

## 4. La liste des hôtes de production, établie par preuve, pas devinée

Le brief ne donnait que deux entrées sûres (`linstantcle.ch`,
`sexcaperoom.ch`, ce dernier hors de propos ici — voir plus bas) et un
hôte corrigé (`reservation.sexcaperoom.ch`). Le reste de la liste
originale de Thomas était illisible (liens Markdown, chapitre 1.b) : rien
à en récupérer. Plutôt que deviner les entrées manquantes,
`docs/briefs/brief-verrou-hote-reservation.md` ligne 66 a été relu — il
établit déjà que quatre hôtes partagent la même racine sur le serveur —
et **revérifié aujourd'hui**, par les mêmes moyens, avant de l'encoder :

```
$ ssh sg-linstantcle "for d in linstantcle.ch reservation.sexcaperoom.ch \
    maisonnette-enchantee.ch api.linstantcle.ch admin.linstantcle.ch; do \
    ls -i ~/www/\$d/public_html/index.php; done"
119038289  linstantcle.ch/public_html/index.php
119038289  reservation.sexcaperoom.ch/public_html/index.php
119038289  maisonnette-enchantee.ch/public_html/index.php
119038289  api.linstantcle.ch/public_html/index.php
119054161  admin.linstantcle.ch/public_html/index.php   ← installation distincte
```

Quatre hôtes, un seul inode : `linstantcle.ch`, `reservation.sexcaperoom.ch`,
`maisonnette-enchantee.ch`, `api.linstantcle.ch`. `admin.linstantcle.ch`
porte un inode différent — confirmé comme une installation WordPress
distincte, hors de ce greffon (qui ne s'y déploie pas).

Une cinquième entrée a été trouvée aujourd'hui, absente de
`brief-verrou-hote-reservation.md` : `www.linstantcle.ch` n'a pas de
répertoire propre sur le serveur, mais répond en 301 vers
`https://www.linstantcle.ch/fr/` — donc servi par cette même installation
WordPress, sous ce même hôte, sans repli serveur vers le domaine nu. Un
visiteur peut réserver et déclencher un envoi sous cet hôte.

```
$ curl -sI https://www.linstantcle.ch/  →  301, Location: https://www.linstantcle.ch/fr/
$ curl -sI https://linstantcle.ch/      →  301, Location: https://linstantcle.ch/fr/
$ curl -sI https://maisonnette-enchantee.ch/      →  301, Location: https://linstantcle.ch/
$ curl -sI https://www.maisonnette-enchantee.ch/  →  301, Location: https://linstantcle.ch/
$ curl -sI https://api.linstantcle.ch/            →  301, Location: https://api.linstantcle.ch/fr/
$ curl -sI https://www.reservation.sexcaperoom.ch/  →  ne résout pas (DNS)
$ curl -sI https://www.api.linstantcle.ch/          →  ne résout pas (DNS)
```

`maisonnette-enchantee.ch` (nu et en `www`) est absorbée par la
redirection canonique de WordPress vers `linstantcle.ch`, sur une requête
GET. **Gardée dans la liste tout de même** : cette redirection ne
s'applique jamais aux requêtes POST (comportement documenté du cœur
WordPress), ni à l'API REST, ni à `admin-ajax.php` — des chemins qu'un
envoi déclenché par Vik ou un formulaire peut emprunter sous cet hôte sans
jamais passer par la redirection canonique. L'exclure aurait recréé
exactement le défaut 1.a sous un autre nom : un hôte de production réel,
absent de la liste, qui avalerait silencieusement un e-mail.

`sexcaperoom.ch` (nu) est un **site distinct**, sur un serveur distinct
(`gvam1277`, `docs/briefs/brief-habillage-tunnel.md` ligne 9) : hors de
cette installation, donc hors de cette liste. C'est la raison pour
laquelle le brief ne citait que `linstantcle.ch` comme entrée sûre
directement réutilisable, pas `sexcaperoom.ch` malgré ce qu'il disait au
chapitre 1.b — cette mention concernait la forme de l'entrée (nom d'hôte
nu contre lien Markdown), pas son appartenance à cette installation.

Liste finale, `lme_mail_guard_default_production_hosts()` :

```php
'linstantcle.ch',
'www.linstantcle.ch',
'reservation.sexcaperoom.ch',
'maisonnette-enchantee.ch',
'api.linstantcle.ch',
```

Codée en dur dans `includes/core.php`, pas dans un fichier de
configuration séparé : ce n'est pas une correspondance
chambre/marque/expérience/espace au sens de la règle absolue n°5 de
`CLAUDE.md`, mais un fait d'infrastructure — quels hôtes partagent ce
`wp_mail()` — qui change aussi rarement que le montage SiteGround
lui-même. Le raisonnement complet, avec chaque hôte examiné et pourquoi il
est ou n'est pas dans la liste, est documenté dans la docblock de la
fonction.

---

## 5. L'adresse fourre-tout, un réglage de Thomas

`LME_MAIL_GUARD_CATCHALL_EMAIL`, constante de `wp-config.php`, jamais
posée par ce dépôt. Non définie ou vide hors production :
`lme_mail_guard_maybe_abort_send()` (`includes/guard.php`) abandonne
l'envoi via `pre_wp_mail`, plutôt que d'inventer une adresse par défaut —
journalisé en erreur (chapitre 3 ci-dessus). Définie, elle redirige et
l'envoi part normalement, vers elle.

`FORCE_EMAIL_REDIRECT`, même nom que dans le brief, booléenne, échappatoire
du chapitre 2.a.

---

## 6. Ce que ce greffon ne touche jamais

Écrit dans l'en-tête de `mu-plugins/lme-mail-guard/lme-mail-guard.php`,
comme demandé : ce greffon ne touche ni `From`, ni `Sender`, ni
`Reply-To`. L'identité d'envoi par marque est posée en amont par
`mu-plugins/lme-brands/includes/mail-brand.php`, sur les crochets de Vik,
avant que `wp_mail()` ne s'exécute. La vérification n°5 de la recette lit
les en-têtes d'une confirmation pour vérifier cette identité : elle reste
valable après ce greffon, le message arrivant dans la boîte fourre-tout
au lieu de celle du client, avec les mêmes `From`/`Sender`/`Reply-To`.

---

## 7. Fichiers écrits

```
mu-plugins/lme-mail-guard.php                     point d'entrée (racine, chargement WordPress)
mu-plugins/lme-mail-guard/lme-mail-guard.php       bootstrap, docblock des réglages
mu-plugins/lme-mail-guard/includes/core.php        décisions pures, sans WordPress
mu-plugins/lme-mail-guard/includes/logger.php      journalisation, même forme que lme-brands
mu-plugins/lme-mail-guard/includes/guard.php       accroche wp_mail / pre_wp_mail
mu-plugins/lme-mail-guard/tests/test-core.php      tests unitaires purs
```

Rien n'est déployé : ces fichiers vivent dans le dépôt, pas encore sur le
serveur (ni préproduction, ni production).

---

## 8. Tests

```
$ php mu-plugins/lme-mail-guard/tests/test-core.php
44 tests, 0 échec(s).
```

Couvrent, comme demandé : la décision « production ou non »
(`lme_mail_guard_is_production_context()`, `lme_mail_guard_should_redirect()`,
y compris la régression du défaut 1.a et l'échappatoire `FORCE_EMAIL_REDIRECT`)
et la normalisation des en-têtes (`lme_mail_guard_normalize_headers()`,
`lme_mail_guard_strip_cc_bcc()` — avec la régression exacte du défaut 1.d
et un cas piège pour vérifier que le correctif ne bascule pas dans
l'excès —, `lme_mail_guard_format_original_to()`,
`lme_mail_guard_build_redirected_headers()` de bout en bout, et
`lme_mail_guard_resolve_catchall()` pour le repli inerte).

`php -l` propre sur les six fichiers PHP du greffon.

---

## 9. Ce que ce constat ne fait pas

Rien n'est déployé sur la préproduction ni sur la production. Aucune
ligne n'est écrite en base. Aucune clé n'a été lue — les seules lectures
serveur sont `ls -i` sur des inodes et la lecture d'un fichier du cœur
WordPress (`pluggable.php`, code public, pas de secret), toutes deux en
lecture seule par SSH. Le déploiement de ce greffon (inventaire, ordre,
vérification) reste à écrire séparément, sur le modèle de
`constat-deploiement-moteur.md` — pas fait ici, la commande ne le demandait
pas.

---

## 10. La réserve levée : le cron hors requête HTTP

22 septembre 2026, en réponse à la réserve bloquante du plan de marche
§B9e : `lme_mail_guard_is_production_context()` rendait `false` dès que
l'hôte était absent, donc hors de toute requête HTTP — WP-CLI, ou un cron
lancé en ligne de commande. En production, l'envoi n'y était alors ni
reconnu comme production, ni détourné (pas d'adresse fourre-tout à y
livrer), et `pre_wp_mail` l'abandonnait.

### 10.1 — comment le cron est réellement déclenché sur cet hébergement, établi et non supposé

Lecture seule, par SSH (`sg-linstantcle`), sur trois fichiers :

1. **`wp-config.php` de production ne définit pas `DISABLE_WP_CRON`**
   (`grep -n 'DISABLE_WP_CRON' ~/www/linstantcle.ch/public_html/wp-config.php`
   — aucune correspondance). C'est la constante qui, si elle valait `true`,
   signalerait un vrai cron système externe (typiquement une tâche Site
   Tools de SiteGround appelant `wp cron event run` en ligne de commande).
   Absente, elle laisse WordPress à son comportement par défaut.
2. **Ce comportement par défaut est celui de `spawn_cron()`**, lu dans
   `wp-includes/cron.php` du cœur (même installation, même jour) :
   `$cron_url = add_query_arg( 'doing_wp_cron', $doing_wp_cron, site_url( 'wp-cron.php' ) );`
   suivi de `wp_remote_post( $cron_request['url'], $cron_request['args'] )`
   (`cron.php:961` et `:999`). C'est une **vraie requête HTTP**, adressée à
   l'hôte du site lui-même — elle porte donc un `HTTP_HOST` égal à l'hôte
   de production, exactement comme n'importe quelle requête de visiteur.
3. **Le rappel avant séjour de Vik passe par ce même mécanisme**, pas par
   un point d'entrée séparé : `VikBookingCron::setup()`
   (`wp-content/plugins/vikbooking/libraries/system/cron.php`) enregistre
   ses tâches par `add_action( $hook, … )` puis `wp_schedule_event( time(),
   $interval, $hook )` — l'API native de WP-Cron, pas un script CLI ou une
   URL propre à Vik. La docblock de `VikBookingCron::runJob()` le confirme
   explicitement : « require the main library in case WPCron runs the
   job », « Initialize timezone handler when WP-Cron executes the job ».

**Conclusion, établie et non supposée : sur cet hébergement, aujourd'hui,
le rappel avant séjour de Vik s'exécute par la boucle HTTP de WP-Cron, qui
porte un `HTTP_HOST`.** Aucun rappel de production ne disparaissait donc
avant ce jour du fait de la réserve — mais la réserve restait réelle :
`wp-config.php` peut se voir ajouter `DISABLE_WP_CRON` à tout moment
depuis Site Tools (fonctionnalité SiteGround de « vrai cron » serveur, qui
appelle alors `wp cron event run` sans `HTTP_HOST`), sans qu'aucune ligne
de ce dépôt ne change et sans que quiconque relise ce constat à ce
moment-là. WP-CLI est d'ailleurs déjà installé sur le serveur
(`/usr/local/bin/wp`), donc un appel manuel en ligne de commande — par
Thomas, par le support SiteGround, ou par une tâche future — est possible
dès aujourd'hui. Le crontab système du compte n'est, lui, pas consultable
depuis ce shell (`crontab -l` : commande absente, restriction normale de
l'environnement CageFS de SiteGround) : son absence de preuve n'est pas
une preuve d'absence, et c'est une raison de plus de corriger la fonction
plutôt que de se fier au seul mécanisme observé aujourd'hui.

### 10.2 — le correctif : `wp_get_environment_type()` seul quand l'hôte est absent

`lme_mail_guard_is_production_context()` (`includes/core.php`) ne rend
plus `false` par défaut quand l'hôte est absent. Elle rend désormais la
valeur de `'production' === $environment_type` seule pour ce cas : pas
d'hôte à vérifier, donc rien à comparer à la liste des hôtes de
production. Établi par lecture de `wp_get_environment_type()` du cœur
(`wp-includes/load.php:250-299`, même serveur, même jour) : sans
`WP_ENVIRONMENT_TYPE` défini — confirmé absent du `wp-config.php` de
production par la même méthode qu'au 10.1 — la fonction retombe sur son
défaut `'production'` (`load.php:294-296`). La branche ajoutée reconnaît
donc la production réelle de cette installation, pas une hypothèse sur ce
que vaudrait la constante.

La préproduction n'est pas affectée : le premier `if` de la fonction
écarte déjà tout `$environment_type` différent de `'production'` avant
que l'hôte ne soit even regardé, donc `'staging'` (valeur du levier de
préproduction, chapitres précédents) continue de tout détourner, hôte
présent ou non.

Deux tests ajoutés à `tests/test-core.php`, et le test qui figeait
l'ancien comportement corrigé plutôt que supprimé (son intitulé et sa
valeur attendue changent, pas sa position) :

- `is_production_context( 'production', null, $prod_hosts )` attend
  désormais `true` (régression du défaut de la réserve).
- `is_production_context( 'staging', null, $prod_hosts )` attend `false`
  (l'absence d'hôte ne fait jamais basculer une préproduction en
  production).
- `should_redirect( 'production', null, $prod_hosts, false )` attend
  `false` : un rappel de production lancé par WP-CLI ou un cron en ligne
  de commande n'est plus détourné ni abandonné.

```
$ php mu-plugins/lme-mail-guard/tests/test-core.php
47 tests, 0 échec(s).
```

(44 tests au 22 septembre, version 1.0 ; 3 ajoutés ici, aucun retiré,
aucun autre modifié.)

### 10.3 — la journalisation de tout abandon, déjà conforme, vérifiée et non recodée

Relecture de `includes/guard.php` à la lumière de la règle n°3 de la
commande : « tout abandon d'envoi se journalise, avec ou sans marque
résolue ». `lme_mail_guard_maybe_abort_send()` journalise déjà
`catchall_not_configured` en erreur de façon **inconditionnelle**, juste
avant de retourner `false` — cet appel ne dépend pas du résultat de
`lme_mail_guard_maybe_warn_brand_host()` (qui, lui, ne journalise qu'un
avertissement **supplémentaire**, et seulement si l'hôte résout une
marque). Un abandon sans hôte, donc sans marque résolue, journalise donc
déjà l'erreur `catchall_not_configured` — vérifié par lecture, aucune
correction nécessaire ici, et aucun test ajouté pour ce point : ce fichier
consomme `wp_get_environment_type()`, `add_filter()` et `error_log()`, il
n'est pas testable sans site WordPress au sens de `tests/test-core.php`
(même limite, déjà documentée, que pour `mu-plugins/lme-brands`, qui n'a
lui non plus qu'un `tests/test-core.php`).

Un effet de bord du correctif 10.2, à noter : puisqu'un envoi de
production avec hôte absent n'est désormais plus détourné du tout (il
part normalement), il n'atteint plus jamais `maybe_abort_send()` — il n'y
a donc plus d'abandon à journaliser dans ce cas précis, parce qu'il n'y a
plus d'abandon.

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-22 | Création. `mu-plugins/lme-mail-guard/` écrit : quatre défauts du brief corrigés (1.a-1.d), deux durcissements appliqués (2.a-2.b), forme réelle de `$args['headers']` établie par lecture de `wp_mail()` du cœur sur le serveur, liste des hôtes de production établie par preuve (SSH, inodes, `curl -I`) plutôt que devinée depuis les entrées Markdown illisibles du brief. 44 tests unitaires purs, 0 échec. Rien de déployé, rien écrit en base, aucune clé lue. |
| 1.1 | 2026-09-22 | Réserve bloquante de plan-de-marche.md §B9e levée. Établi par lecture du serveur (wp-config.php, wp-includes/cron.php, wp-includes/load.php, vikbooking/libraries/system/cron.php) que le rappel avant séjour de Vik s'exécute aujourd'hui par la boucle HTTP par défaut de WP-Cron, qui porte un `HTTP_HOST` — aucun rappel de production n'a donc disparu jusqu'ici, mais la réserve restait réelle (SiteGround peut activer un vrai cron CLI sans toucher au dépôt, et WP-CLI est déjà installé sur le serveur). `lme_mail_guard_is_production_context()` décide désormais sur `wp_get_environment_type()` seul quand l'hôte est absent, la préproduction continuant de tout détourner. 3 tests ajoutés (47 au total, 0 échec), le test qui figeait l'ancien comportement corrigé plutôt que supprimé. La journalisation inconditionnelle de tout abandon (`catchall_not_configured`), déjà conforme à la règle absolue n°6, relue et confirmée sans modification. Rien de déployé, rien écrit en base, aucune clé lue. |
