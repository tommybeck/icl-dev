# Constat — correctif de la signature du rappel de paiement (B9c)

22 septembre 2026. Réponse à `docs/briefs/plan-de-marche.md` §B9c. Correctif
porté dans le code (`mu-plugins/lme-brands/includes/payment-brand.php`),
prémisse fausse corrigée (`docs/briefs/constat-phase-0.md` §Q5), test ajouté
(`mu-plugins/lme-brands/tests/test-core.php`). Lecture seule sur le serveur,
rien de déployé, aucune clé lue, aucune écriture en base.

---

## 1. La signature, établie par lecture du code de Vik et de WordPress

`constat-fatal-page-paiement.md` §2 établit déjà la cause de la fatale du
21 septembre : `payment.php:329` construit l'appel comme
`do_action($this->getHook('payment_before_begin_transaction'), array(&$this))`,
mais `do_action()` du **cœur WordPress** (`wp-includes/plugin.php`) déballe
automatiquement ce motif — un tableau à un seul élément qui est un objet —
avant d'appeler les rappels :

```php
// wp-includes/plugin.php, dans do_action() :
if ( empty( $arg ) ) {
    $arg[] = '';
} elseif ( is_array( $arg[0] ) && 1 === count( $arg[0] ) && isset( $arg[0][0] ) && is_object( $arg[0][0] ) ) {
    // Backward compatibility for PHP4-style passing of `array( &$this )` as action `$arg`.
    $arg[0] = $arg[0][0];
}
```

Relevé identique, ligne pour ligne, sur `staging13.linstantcle.ch` et
`linstantcle.ch` (`constat-fatal-page-paiement.md` §3, empreintes prises sur
les deux installations). Ce n'est pas un comportement propre à Vik ni à ce
mu-plugin : c'est un mécanisme du cœur de WordPress, qui s'applique à
n'importe quel `do_action($hook, array(&$this))`, de n'importe quel plugin,
sur n'importe quelle version de WordPress qui porte encore ce bloc de
compatibilité ascendante (documenté comme tel dans son propre commentaire).

**La vraie signature : `lme_brands_brand_payment_transaction()` reçoit
l'objet de paiement directement, jamais un tableau dont l'indice 0 le
contiendrait.** La pile de la fatale le confirmait déjà à la ligne 0 :
`lme_brands_brand_payment_transaction(Object(VikBookingStripePayment))` — un
objet en argument, pas un tableau.

### Le correctif

`mu-plugins/lme-brands/includes/payment-brand.php` :

- La fonction change de signature : `function lme_brands_brand_payment_transaction( $payment )` au lieu de `function lme_brands_brand_payment_transaction( $args )`.
- La ligne fautive, `$payment = isset( $args[0] ) ? $args[0] : null;`, est retirée : il n'y a rien à déballer, l'argument reçu **est** déjà l'objet de paiement.
- Le reste de la fonction — restriction à `isDriver('stripe')`, garde sur `get()`/`set()`, résolution de marque par les chambres de la réservation, pose de la métadonnée — est inchangé dans sa logique, seule la première ligne disparaît.
- Le commentaire de tête du fichier, qui portait la même prémisse fausse (« le rappel reçoit un tableau dont l'indice 0 est l'objet de paiement »), est réécrit pour porter la vraie signature et l'historique de l'erreur.

`php -l` propre sur le fichier modifié.

---

## 2. La prémisse, corrigée à sa source

`docs/briefs/constat-phase-0.md` §Q5 portait, depuis le 11 septembre 2026,
le paragraphe suivant :

> Piège d'implémentation : l'appel est `do_action($hook, array(&$this))`,
> donc la fonction rappelée reçoit un tableau dont l'indice `0` est l'objet
> de paiement, et non l'objet directement.

C'est la lecture littérale du seul code de Vik (`payment.php:329`), exacte
pour cette ligne prise isolément — mais fausse une fois WordPress ajouté,
pour la raison du chapitre 1 ci-dessus. Cette prémisse a été recopiée telle
quelle dans le commentaire de tête de `payment-brand.php` lors de la phase
4 (17 septembre 2026), sans jamais être vérifiée contre le comportement réel
de `do_action()`, et c'est elle qui a produit la fatale.

**Correction apportée à `constat-phase-0.md` §Q5** : le paragraphe fautif
est conservé à l'identique, entre guillemets, puis expressément désigné
comme faux et expliqué — c'est ce qui empêche la prémisse de resservir,
plutôt que de simplement la remplacer en silence. Le paragraphe correct qui
suit dit ce qui est vrai : le rappel reçoit l'objet directement, avec la
même preuve que le chapitre 1 ci-dessus. Une ligne de leçon ferme le
paragraphe : lire le seul code de Vik ne suffit pas à prédire le
comportement réel d'un hook WordPress.

**Pourquoi cela compte pour B6.** Le prompt de B6 (`plan-de-marche.md`)
prévoit un webhook Stripe et un mutateur sur le même objet de paiement, dans
la même famille de crochets de Vik. Si `constat-phase-0.md` restait faux,
B6 hériterait de la même prémisse et risquerait la même classe d'erreur sur
un autre hook `do_action($hook, array(&$this))` de Vik. La correction est
donc à sa source, pas seulement dans le fichier qui a planté.

---

## 3. Le test, et pourquoi les 120 précédents ne l'avaient pas vu

**Ce que `plan-de-marche.md` affirme au moment de lancer B9c est lui-même
imprécis, et vaut la peine d'être corrigé ici plutôt que répété.** Le texte
dit : « Les 120 tests étaient au vert parce qu'ils simulaient la forme
fausse. » Ce n'est pas ce qui s'est passé. Vérifié par lecture de
`tests/test-core.php` en entier (aucune occurrence du mot « payment », aucune
section consacrée à `payment-brand.php`) et confirmé par
`mu-plugins/lme-brands/README.md:291` : « `payment-brand.php` ne définit
aucune fonction pure nouvelle : il orchestre `lme_brands_resolve_brand_for_rooms()`
et `lme_brands_swap_url_host()`, déjà couvertes par les tests de la phase 3
et de la phase 1. » **Les 120 tests étaient au vert parce qu'aucun d'eux
n'exerçait `lme_brands_brand_payment_transaction()` — ni sous sa forme
fausse, ni sous aucune forme.** Ils couvraient les fonctions pures que cette
fonction appelle *après* la ligne fautive, jamais la ligne elle-même. C'est
une différence qui compte : « un test a validé la mauvaise hypothèse » et
« aucun test n'a touché ce code » appellent une correction différente — la
seconde a été le cas ici, et prétendre le contraire dans une future lecture
de ce chantier referait chercher un test qui n'a jamais existé.

### Le test ajouté

`payment-brand.php` appelle des fonctions WordPress (`is_object()`,
`method_exists()` mis à part, qui sont du PHP natif, mais `lme_brands_log()`,
`lme_brands_get_config()`) : il n'est pas une fonction pure et ne peut pas
être chargé par le harnais de tests en ligne de commande, comme
`README.md:291` le documente pour l'ensemble des fichiers qui consomment
WordPress. Le test ajouté ne contourne pas cette limite en important
WordPress ; il exerce **le fait précis qui a produit la fatale** — la
vraie forme de l'argument livré par `do_action()` — en PHP pur, dans
`tests/test-core.php`, nouvelle section « Correctif B9c, signature réelle de
payment_before_begin_transaction_vikbooking » :

1. Une fonction locale au test, `lme_brands_test_simulate_do_action_unwrap()`,
   reproduit fidèlement le bloc de compatibilité ascendante de
   `wp-includes/plugin.php` cité au chapitre 1 — rien de plus, en PHP pur.
   Elle n'existe que pour ce test ; ni `core.php` ni `payment-brand.php` n'en
   ont besoin, WordPress fait ce travail lui-même en production.
2. Premier test : appliquée à `array(&$objet)` — la forme que Vik construit
   à `payment.php:329` — elle rend l'objet directement. C'est la vraie
   signature.
3. **Deuxième test, la non-régression directe** : sur cet objet réellement
   livré, `isset($args[0])` — la ligne 73 du 17 septembre, mot pour mot —
   est exécutée dans un `try`/`catch`. Elle lève une `\Error` PHP 8 :
   assertion vérifiée. **C'est ce test qui aurait échoué sur le code du
   17 septembre** : le code de la phase 4 ne capturait cette erreur nulle
   part, elle remontait telle quelle jusqu'au gestionnaire d'erreurs de
   WordPress — la fatale observée en production le 21 septembre, à
   l'identique.
4. Troisième test : la forme corrigée, `is_object($payment)` sur ce même
   argument, ne lève rien et retourne vrai.

```
$ php mu-plugins/lme-brands/tests/test-core.php
...
Correctif B9c, signature réelle de payment_before_begin_transaction_vikbooking
  ok  - do_action($hook, array(&$this)) livre l'objet de paiement directement au rappel, jamais un tableau à l'indice 0 — vraie signature établie par constat-fatal-page-paiement.md §2
  ok  - non-régression directe du défaut : isset($args[0]) sur l'objet réellement livré au rappel lève une Error PHP8 (« Cannot use object ... as array »), reproduisant le plantage de production du 21 septembre à l'identique — ce test aurait échoué sur le code du 17 septembre, faute d'attraper cette Error
  ok  - la forme corrigée (payment-brand.php:87, is_object($payment) sur l'argument reçu directement) reconnaît l'objet de paiement sans lever d'erreur

135 tests, 0 échec(s).
```

132 tests avant ce correctif (B9b), 135 après. Tous au vert, y compris les
132 existants — aucune régression sur la résolution de marque, le filtrage
de présentation, la garde de réservation, les e-mails, le registre réel ou
la cible de réécriture d'URL.

---

## 4. `lme_brands_correct_payment_urls()` — l'hôte réel, pas le registre

`lme_brands_correct_payment_urls()` visait `$brand['host']`, l'hôte déclaré
au registre pour la marque résolue, pour corriger `return_url`, `error_url`
et `notify_url`. `constat-correctif-url-rewrite.md` §7 le relevait déjà,
sans le corriger, hors périmètre de ce chapitre-là : « le jour où ce fichier
sera corrigé, le même raisonnement — cible toujours l'hôte réel, jamais le
registre — s'appliquera probablement là aussi ; à vérifier à ce moment-là,
pas ici. » C'est ce moment.

### La preuve

Cette fonction s'exécute pendant `JPayment::showPayment()`
(`payment.php:329`), donc pendant la **même requête HTTP** que le rendu de
la page de paiement — la requête que `includes/url-rewrite.php` filtre déjà
vers l'hôte réel depuis le correctif B9b. Les deux fonctions tournent sur la
même requête, avec les mêmes deux hôtes disponibles :

- l'hôte **effectif** de résolution de marque
  (`lme_brands_current_http_host()`), potentiellement forcé par le levier de
  préproduction B8 ;
- l'hôte HTTP **réel** de la requête
  (`lme_brands_current_raw_http_host()`), jamais forcé.

En production, `lme_brands_resolve_effective_http_host()` ne force jamais
rien (`environment_type` n'y vaut jamais `'staging'`,
`constat-deploiement-moteur.md`), donc l'hôte effectif est toujours l'hôte
réel de la requête. Et `lme_brands_resolve_brand_by_host()` ne peut faire
correspondre cet hôte effectif à une marque qu'en le comparant, exactement,
au `host` que cette marque déclare au registre — donc en production, l'hôte
réel et le `host` du registre sont égaux par construction, à ce point du
code. **Viser l'un ou l'autre ne change rien en production** : c'est
exactement la preuve qui fondait déjà le correctif de `url-rewrite.php`
(`constat-deploiement-moteur.md` §8, point 1), et elle vaut à l'identique
ici, pour la même raison structurelle.

Sous le levier de préproduction, les deux hôtes divergent : l'hôte effectif
est une valeur de **simulation** (le `host` d'une marque, forcé pour activer
ses règles métier), jamais l'hôte qui sert réellement la requête. Si
`lme_brands_correct_payment_urls()` continuait de viser `$brand['host']`,
elle referait, pour le paiement, exactement la fuite du 21 septembre que
B9b a corrigée pour les feuilles de style : sous le levier, elle
« corrigerait » `return_url`, `error_url` et `notify_url` de l'hôte réel de
la préproduction **vers l'hôte de production** de la marque — c'est-à-dire
qu'elle romprait précisément ce que `url-rewrite.php` vient de fixer, une
requête plus tard, sur les mêmes trois valeurs.

**Nuance à ne pas perdre : ce défaut n'a jamais pu s'exécuter en pratique**,
`constat-correctif-url-rewrite.md` §7 le relevait déjà — la fatale de
`constat-fatal-page-paiement.md` arrêtait l'exécution de `payment-brand.php`
à sa première ligne, avant d'atteindre `lme_brands_correct_payment_urls()`.
Ce chapitre ne corrige donc pas un incident observé, il corrige un défaut
qui se serait produit à la prochaine tentative de paiement en préproduction
sous le levier, une fois la fatale du chapitre 1 réparée — ce qui vient
d'arriver dans ce même correctif.

### Le correctif

`payment-brand.php` :

- `lme_brands_brand_payment_transaction()` appelle désormais
  `lme_brands_correct_payment_urls( $payment, $brand_key, lme_brands_current_raw_http_host(), $booking_id )`
  au lieu de `$brand['host']`. La variable locale `$brand`, qui ne servait
  plus qu'à cette lecture, est retirée.
- `lme_brands_correct_payment_urls()` gagne une garde explicite : si l'hôte
  réel est indisponible (`null` ou chaîne vide — cas qui ne devrait jamais
  se produire pendant le rendu d'une vraie page de paiement, `$_SERVER['HTTP_HOST']`
  étant toujours présent sur une requête HTTP réelle, mais chapitre 6 du
  brief oblige : jamais un échec silencieux), la fonction journalise une
  erreur explicite (`payment_url_host_unavailable`) et ne touche à aucune
  URL, plutôt que de laisser `lme_brands_swap_url_host()` no-opper en
  silence sur un hôte absent.
- Le commentaire de tête de la fonction porte désormais le même
  raisonnement que celui de `lme_brands_resolve_url_rewrite_target()`
  (`includes/core.php`), avec un renvoi explicite vers
  `constat-correctif-url-rewrite.md`.

`php -l` propre, `132 → 135` tests au vert après ce correctif (chapitre 3
ci-dessus couvre les trois nouveaux).

---

## 5. Ce que ce constat ne fait pas

Conformément au prompt de lancement : **rien n'est déployé**, aucune clé
n'a été lue, aucune écriture n'a touché la base. La commande de test #1822
laissée par la fatale du 21 septembre (`constat-fatal-page-paiement.md`
§4) n'est pas traitée ici — elle attend toujours le geste de Thomas dans
l'administration de Vik, décrit dans ce même constat.

Ce correctif lève le verrou dur que B9c posait sur B10
(`plan-de-marche.md` §B) pour son propre motif : la fatale de paiement est
corrigée, sa prémisse est corrigée à sa source, et un test exerce désormais
la vraie forme du rappel. Les autres prérequis de B10 — recette des deux
passes, exclusion `tests/` du script de déploiement, revue de B5, verrou
d'hôte G2/G3 — restent entiers et ne sont pas traités ici.

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-22 | Création. Signature corrigée dans `payment-brand.php` (l'objet de paiement est livré directement par `do_action()`, jamais dans un tableau, à cause du déballage de compatibilité ascendante du cœur WordPress). Prémisse fausse corrigée à sa source dans `constat-phase-0.md` §Q5. Trois tests ajoutés dans `test-core.php`, qui exercent la vraie forme de l'argument reçu par le rappel et démontrent que le code du 17 septembre aurait échoué sur ce test précis. `lme_brands_correct_payment_urls()` corrigée pour viser l'hôte réel de la requête plutôt que l'hôte déclaré au registre, même raisonnement que le correctif B9b, avec sa propre preuve. |
