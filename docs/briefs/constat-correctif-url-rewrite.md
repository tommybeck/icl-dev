# Constat — correctif de la réécriture d'URL en préproduction

22 septembre 2026. Réponse au chapitre 2 de
`brief-correctif-levier-et-fatal-paiement.md` et à
`constat-deploiement-moteur.md` §8. Correctif porté dans le code
(`mu-plugins/lme-brands/includes/core.php`, `includes/registry.php`,
`includes/url-rewrite.php`), avec ses tests unitaires
(`mu-plugins/lme-brands/tests/test-core.php`). Lecture seule sur le
serveur, rien de déployé, aucune clé lue.

---

## 1. Le défaut, en une phrase

`includes/url-rewrite.php` réécrivait `option_home`, `option_siteurl`,
`content_url`, `upload_dir` et `wp_get_attachment_url` vers le `host`
**déclaré au registre** pour la marque résolue
(`$config['brands'][$brand_key]['host']`) — au lieu de l'hôte qui sert
réellement la requête. En production, les deux coïncident toujours ; sous
le levier de préproduction B8 (`LME_BRANDS_HOST_OVERRIDE`), ils divergent,
parce que l'hôte résolu est une valeur de **simulation** (celle qui active
les règles métier d'une marque), jamais l'hôte réel de la préproduction.
Résultat observé le 21 septembre 2026 : `staging13.linstantcle.ch` chargeait
ses feuilles de style et ses polices depuis `https://reservation.sexcaperoom.ch/` —
la production — alors que `sexcaperoom-tunnel.css` répondait en 200 sur
`staging13` lui-même.

---

## 2. Pourquoi les deux hôtes divergent sous le levier, et pas ailleurs

`lme_brands_current_http_host()` (`includes/registry.php`) retourne l'hôte
**effectif** de résolution de marque : l'hôte réel de la requête, sauf sous
le levier de préproduction, où il retourne la valeur forcée par
`LME_BRANDS_HOST_OVERRIDE` (`reservation.sexcaperoom.ch` pendant la
passe Sexcape Room de la recette du 21 septembre). C'est le comportement
voulu et déjà testé du chantier B8 : ce lecteur sert à activer les règles
métier d'une marque (filtrage des chambres, garde de réservation) sans
changer d'hôte physique.

L'ancien `includes/url-rewrite.php` réutilisait cette même valeur pour
résoudre la marque, **puis** allait chercher le `host` déclaré au registre
pour cette marque — c'est-à-dire, pour `sexcaperoom`,
`reservation.sexcaperoom.ch`, l'hôte de **production** (vérifié le
15 septembre 2026 dans `config/brands.php`, commentaire de la marque
`sexcaperoom`). Sur un vrai visiteur de production, cet hôte déclaré est
systématiquement égal à l'hôte réel de la requête, parce que
`lme_brands_resolve_brand_by_host()` n'a pu faire correspondre une marque
qu'en comparant l'hôte de la requête, exactement, à ce `host` déclaré : les
deux sont donc égaux par construction, et la réécriture ne fait rien
(`constat-deploiement-moteur.md` §8, point 1). Mais sous le levier, l'hôte
« résolu » n'est plus l'hôte réel — c'est une valeur simulée — et cibler le
registre revient alors à réécrire les URL de la préproduction vers la
production.

---

## 3. Décision : découpler, ou hôte de substitution — ni l'un ni l'autre tel quel

Le brief proposait deux pistes. Aucune des deux, prise à la lettre, ne
tient à l'examen ; la correction retenue en reprend l'esprit sans en avoir
les défauts.

### Piste 1, « découpler », ne tient pas

Faire lire à `url-rewrite.php` l'hôte réel de la requête, **jamais** le
levier, pour décider s'il y a une marque à faire respecter. Sur
`staging13.linstantcle.ch`, cet hôte ne correspond à aucune marque du
registre (ni `linstantcle.ch`, ni `reservation.sexcaperoom.ch`), donc
`lme_brands_resolve_brand_by_host()` retourne toujours `null` et la
réécriture ne se déclenche jamais, quel que soit le levier.

Deux défauts, tous deux vérifiés par lecture du code plutôt que supposés :

1. **Elle romprait l'invariant posé par le chantier B8 lui-même** — le
   commentaire de tête de `lme_brands_current_http_host()`
   (`includes/registry.php`) est explicite : « Point de normalisation
   partagé par la réécriture d'URL, le filtrage de présentation et la
   garde de réservation : trois lecteurs, une seule façon de lire l'hôte. »
   Découpler `url-rewrite.php` de ce lecteur commun créerait une
   incohérence à l'intérieur d'une même requête : sous le levier,
   `room-filter.php` et `booking-guard.php` traiteraient la page comme
   relevant de Sexcape Room (chambres filtrées, garde appliquée), tandis
   que `url-rewrite.php` la traiterait comme n'appartenant à aucune marque.
2. **Elle empêcherait pour de bon de recetter l'apparence du tunnel sous le
   levier** — exactement le chantier D (`brief-habillage-tunnel.md`) et la
   vérification `--srlm-` du script de déploiement B9
   (`constat-script-deploiement.md` chapitre 1, étape 6 et chapitre 3.5) :
   le mécanisme qui décide vers quel hôte pointent les feuilles de style ne
   s'exécuterait jamais en préproduction. Le brief le relevait déjà : « la
   réécriture d'URL n'est alors jamais exercée en préproduction. » Vérifié
   ici de bout en bout, pas seulement noté.

### Piste 2, « hôte de substitution par environnement », telle que décrite ne tient pas non plus

Ajouter, dans le registre, une seconde valeur de `host` propre à la
préproduction. Deux défauts :

1. Elle ajoute une **valeur configurable de plus**, à tenir manuellement à
   jour (« l'hôte réel de la préproduction »), qui peut dériver de l'hôte
   réel (renommage de sous-domaine, nouvelle préproduction sous un autre
   nom — déjà arrivé une fois, `staging10` → `staging13`,
   `constat-phase-0.md` B8 → `constat-script-deploiement.md`) sans que rien
   ne le signale : exactement le genre de configuration en dur que la
   règle absolue n°5 de `CLAUDE.md` proscrit quand une valeur déjà connue
   du système peut la remplacer.
2. Elle ne règle le problème que pour l'environnement qu'on a pensé à
   couvrir. Le même défaut existe, symétriquement, pour une passe
   `--forcer-override` vers `linstantcle.ch` (recette de L'Instant Clé sur
   la préproduction, chapitre 1 du brief) : la cible deviendrait
   `linstantcle.ch`, la production de l'autre marque. Une seconde valeur au
   registre ne couvrirait que le cas déjà observé, pas la classe du
   défaut.

### Ce qui tient : ne jamais viser le registre, toujours l'hôte réel de la requête

La cause du défaut n'est pas « quel hôte le registre déclare-t-il », c'est
que `url-rewrite.php` allait chercher sa cible dans le **registre**, un
fichier de configuration statique, plutôt que dans l'**hôte réel de la
requête**, une donnée déjà disponible (`$_SERVER['HTTP_HOST']`), jamais
soumise au levier. La correction ne demande donc **aucune nouvelle valeur
de configuration** : `includes/url-rewrite.php` continue de lire
`lme_brands_current_http_host()` (le lecteur partagé, sous levier) pour
décider **si** une marque gouverne la requête — préservant l'invariant du
chantier B8 — mais vise désormais, comme cible de la réécriture,
`lme_brands_current_raw_http_host()` : l'hôte HTTP réel, jamais forcé.

En production, les deux hôtes coïncident toujours (le levier n'existe pas
en production — refus explicite du script de déploiement,
`constat-script-deploiement.md` §2, ligne « Le levier n'existe jamais en
production »), donc rien ne change : vérifié par des tests d'équivalence
(§5). En préproduction sous le levier, ils divergent exactement là où le
défaut se produisait, et c'est là que la cible devient l'hôte réel — donc
un no-op vérifiable, jamais une fuite vers la production.

---

## 4. Le correctif

**`mu-plugins/lme-brands/includes/core.php`**, deux fonctions pures
ajoutées, testables sans WordPress :

- `lme_brands_normalize_http_host( $raw_host )` — la normalisation d'hôte
  (minuscule, port retiré), déjà dupliquée deux fois en substance dans
  `registry.php` et dans `lme_brands_resolve_effective_http_host()`,
  factorisée à un seul endroit.
- `lme_brands_resolve_url_rewrite_target( array $config, $effective_host, $raw_host )`
  — la décision elle-même : si `$effective_host` ne résout aucune marque,
  ou si `$raw_host` est absent, aucune cible (`null`) ; sinon, la cible est
  toujours `$raw_host`, jamais un `host` lu dans `$config`.

**`mu-plugins/lme-brands/includes/registry.php`** : `lme_brands_current_http_host()`
réutilise `lme_brands_normalize_http_host()` (aucun changement de
comportement, juste la factorisation) ; nouvelle fonction
`lme_brands_current_raw_http_host()`, qui retourne l'hôte HTTP réel de la
requête, normalisé, **jamais** soumis à `LME_BRANDS_HOST_OVERRIDE` — seul
nouvel appelant : `includes/url-rewrite.php`.

**`mu-plugins/lme-brands/includes/url-rewrite.php`** :
`lme_brands_current_request_brand_host()` ne fait plus le lookup
`$config['brands'][$brand_key]['host']` ; elle délègue à
`lme_brands_resolve_url_rewrite_target()`, avec l'hôte effectif (sous
levier) pour décider s'il y a une marque à servir, et l'hôte réel comme
cible. Les cinq filtres (`option_home`, `option_siteurl`, `content_url`,
`upload_dir`, `wp_get_attachment_url`) et leur garde-fou (jamais en
administration, API REST ou cron) sont inchangés : seule la fonction
qu'ils appellent tous change de cible.

---

## 5. Preuve, avant et après

Rejouée en PHP pur (`lme_brands_resolve_url_rewrite_target()` et
`lme_brands_swap_url_host()` sont toutes deux pures, aucune dépendance à
WordPress), sur le registre réel du dépôt et l'URL exacte relevée dans le
DOM le 21 septembre :

```
cible calculée (nouveau code) : staging13.linstantcle.ch
avant : https://staging13.linstantcle.ch/wp-content/themes/astra-child/assets/css/sexcaperoom-tunnel.css
après : https://staging13.linstantcle.ch/wp-content/themes/astra-child/assets/css/sexcaperoom-tunnel.css
→ no-op, aucune fuite vers la production

pour mémoire, ancienne cible (code retiré) : reservation.sexcaperoom.ch
ancien résultat : https://reservation.sexcaperoom.ch/wp-content/themes/astra-child/assets/css/sexcaperoom-tunnel.css
→ c'est exactement le défaut observé le 21 septembre
```

---

## 6. Tests unitaires ajoutés

`mu-plugins/lme-brands/tests/test-core.php`, section « Correctif du 22
septembre 2026 : cible de la réécriture d'URL », douze nouveaux cas :

- `lme_brands_normalize_http_host()` : hôte déjà propre inchangé,
  minuscules, port retiré, valeur vide ou non textuelle donne `null`.
- `lme_brands_resolve_url_rewrite_target()` :
  - **non-régression directe du défaut** : sous le levier (`effective_host`
    = `reservation.sexcaperoom.ch`, `raw_host` = `staging13.linstantcle.ch`),
    la cible est l'hôte réel de la préproduction, jamais l'hôte de
    production déclaré au registre ;
  - **équivalence de production** : quand l'hôte effectif et l'hôte réel
    coïncident (aucun levier actif), le résultat est identique à l'ancien
    comportement, pour les deux marques ;
  - hôte effectif absent (contexte exclu), hôte effectif sans marque
    connue, hôte réel absent ou vide : aucune cible dans les quatre cas,
    jamais une cible devinée.

```
$ php mu-plugins/lme-brands/tests/test-core.php
...
132 tests, 0 échec(s).
```

120 tests avant ce correctif (chantier B8), 132 après. Tous au vert,
y compris les 120 existants — aucune régression sur la résolution de
marque, le filtrage de présentation, la garde de réservation, les e-mails
ou le registre réel.

`php -l` propre sur les trois fichiers modifiés.

---

## 7. Ce que ce correctif ne couvre pas

- **`payment-brand.php`** vise toujours `$brand['host']` (l'hôte déclaré
  au registre, jamais l'hôte réel) pour corriger `return_url`, `error_url`
  et `notify_url` (`lme_brands_correct_payment_urls()`) — un mécanisme
  distinct de celui corrigé ici, hors du périmètre de ce chapitre du
  brief, et qui n'a de toute façon jamais pu s'exécuter jusqu'ici : voir
  `constat-fatal-page-paiement.md`, l'erreur fatale à la ligne 73 du même
  fichier arrête l'exécution avant d'atteindre ce code. Le jour où ce
  fichier sera corrigé, le même raisonnement — cible toujours l'hôte réel,
  jamais le registre — s'appliquera probablement là aussi ; à vérifier à ce
  moment-là, pas ici.
- **La liste blanche d'hôte (G2/G3)**, hors périmètre, comme le rappelle
  `constat-deploiement-moteur.md` §9.
- **Aucun test contre le vrai serveur** n'a été rejoué pour ce correctif
  (consigne du brief : rien de déployé). La preuve du §5 est en PHP pur,
  contre le registre réel et l'URL réellement observée ; la vérification
  de bout en bout, avec le vrai filtre WordPress en place, reste à faire
  au prochain déploiement en préproduction, par le script
  `deployer-moteur.sh` déjà équipé pour ça (chapitre 6, étape « jeton
  `--srlm-` », `constat-script-deploiement.md`).

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-22 | Création. Les deux pistes du brief examinées et rejetées telles quelles ; correctif retenu : la cible d'une réécriture d'URL est toujours l'hôte HTTP réel de la requête, jamais un `host` lu dans le registre, quel que soit le levier. `lme_brands_normalize_http_host()` et `lme_brands_resolve_url_rewrite_target()` ajoutées à `includes/core.php`, `lme_brands_current_raw_http_host()` à `includes/registry.php`. Douze tests ajoutés, 132 au total, tous au vert. |
