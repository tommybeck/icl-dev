# Constat — le script de déploiement, chantier B9

21 septembre 2026. Réponse au prompt B9 de `plan-de-marche.md` §5, à partir de
`constat-deploiement-moteur.md` (chapitres 3 à 7) et de l'état réel du
serveur, revérifié en lecture seule le jour même. Le script vit à la racine
du dépôt : `deployer-moteur.sh`.

**Rien de production n'a été modifié pour écrire ce constat.** Toute
vérification citée ci-dessous vient d'une lecture directe (SSH en lecture
seule, `wp-cli`, ou un bac à sable jetable hors de tout `public_html`, décrit
au chapitre 5 et supprimé avant la fin de la session). La préproduction et la
production sont, à l'heure de ce constat, dans l'état exact où B8 les avait
laissées.

---

## 1. Ce que le script fait

Un seul fichier, `deployer-moteur.sh`, lancé de la même façon sur les deux
environnements :

```bash
./deployer-moteur.sh --env staging --hote staging13.linstantcle.ch
./deployer-moteur.sh --env production --hote linstantcle.ch
```

**Il simule par défaut.** Sans `--appliquer`, il imprime les préalables, le
plan de copie (fichier par fichier : à créer, déjà à jour, à mettre à jour) et
ce qu'il ferait à `functions.php` et à `wp-config.php` — et n'écrit rien.
C'est le mode dans lequel on le lit avant de le croire, exactement ce que
demandait le prompt.

**Avec `--appliquer`, dans l'ordre :**

1. Sauvegarde de `functions.php` réel (`~/.icl-dev-deploy-backups/<hôte>/<horodatage>/`,
   hors de tout dossier servi publiquement).
2. Copie du mu-plugin et du thème par `rsync --files-from`, à partir de la
   liste exacte des fichiers **suivis par git** (jamais `find` sur le dossier
   de travail — voir chapitre 4.1, la trouvaille qui a motivé ce choix).
   Additif, jamais `--delete`.
3. Vérification des empreintes `sha256` de chaque fichier copié contre sa
   source locale (chapitre 6.1 du constat de déploiement).
4. Fusion de `functions.php` : ajoute la ligne `require_once` si elle est
   absente, refuse si le fichier ne fait pas la taille attendue avant fusion,
   refuse si la ligne existe déjà deux fois.
5. Sur la préproduction seulement : pose `LME_BRANDS_HOST_OVERRIDE` dans
   `wp-config.php`, avant la ligne `require_once ABSPATH . 'wp-settings.php';`
   pour que la constante existe avant le bootstrap WordPress — l'ajouter après
   cette ligne l'aurait rendue inerte.
6. Vérification (chapitre 6 du constat) : une seule ligne `require_once`, une
   taille de fichier cohérente, le site répond (200/301/302, pas de 5xx), pas
   de nouvelle erreur fatale PHP dans `debug.log` depuis le déploiement, et sur
   la préproduction, le jeton d'apparence `--srlm-` présent dans la page
   servie sous le levier actif.
7. Sort en erreur au premier contrôle rouge, avec le message qui dit quoi
   corriger.

**`--rollback`** retire dans l'ordre inverse ce que ce script a posé
(`inc/`, `assets/`, `mu-plugins/lme-brands*`, la ligne du levier), puis
restaure `functions.php` depuis la sauvegarde la plus récente pour cet hôte.
Sans sauvegarde et sans ligne à retirer, c'est un constat sans effet, pas une
erreur.

**Idempotent, vérifié.** Deux exécutions de suite : la seconde ne recopie
rien (empreintes déjà identiques), ne refusionne rien
(`already_merged=1`), ne repose rien (`override_state=unchanged`) — testé au
chapitre 5.

---

## 2. Ce qu'il refuse, et pourquoi

Chaque refus vit dans le script lui-même, à un seul endroit (le script
distant ne fait que constater ; toute décision « on continue ou on refuse »
est prise côté local, en clair, jamais devinée) :

| Préalable | Comment il est vérifié | Refuse si |
|---|---|---|
| `wp_get_environment_type()` conforme à l'environnement visé | `wp eval` + **empreinte hexadécimale**, jamais la chaîne affichée (chapitre 3 : pourquoi) | l'empreinte ne correspond pas |
| Empreintes de ce qui est déjà en place | tailles de `api-host.php`, `vre-paid-autoconfirm.php`, `style.css`, et de `functions.php` avant fusion, contre les valeurs de `constat-deploiement-moteur.md` chapitre 1 | une taille diffère de la référence |
| VikStripe en clés de test, en préproduction seulement | `wp db query`, **`LEFT(...,8)`** posé par la requête SQL elle-même — jamais la clé, jamais même son chargement en mémoire côté script — sur la passerelle Stripe **publiée** | le préfixe n'est pas exactement `sk_test_`, ou zéro/plusieurs passerelles Stripe publiées (état ambigu) |
| Une seule ligne `require_once`, jamais dupliquée | `grep -Fxc` de la ligne exacte | elle apparaît déjà deux fois |
| `functions.php` ressemble au thème réel avant fusion | taille exacte, chapitre 6 du constat de déploiement | elle diffère de `7390` (constante à mettre à jour si Thomas modifie ce fichier pour une autre raison — voir chapitre 6 ci-dessous) |
| Le levier n'existe jamais en production | présence de la ligne dans `wp-config.php` | elle y est déjà (un ancien geste manuel à corriger, pas un état que ce script doit faire semblant de ne pas voir) |
| `--override-host` n'a rien à faire en production | argument passé | `--env production` et `--override-host` sont utilisés ensemble |
| Remplacer un levier déjà posé avec une autre valeur | comparaison de la valeur existante | sans `--forcer-override` : deux essais de recette (marque A, marque B) ne doivent jamais s'écraser en silence |
| Le point d'ancrage `wp-settings.php` est unique | `grep -n -F -x` | zéro ou plusieurs occurrences : insertion refusée plutôt que devinée |
| Aucune erreur fatale PHP après le déploiement | `debug.log`, uniquement les octets écrits depuis l'offset pris juste avant la fusion | une ligne `PHP Fatal error` apparaît dans cette fenêtre |
| Le site répond après déploiement | `curl` sur `https://<hôte>/` | code différent de 200/301/302 |
| **`--appliquer` en production** | argument | sans **aussi** `--confirmer-production` — un geste volontaire à deux mains, celui de Thomas, jamais celui de Code |

Aucun de ces refus ne dépend d'un jugement fait sur le serveur : le script
distant ne renvoie que des faits (tailles, empreintes, préfixes, comptes de
lignes), jamais un verdict. Toute la politique — ce qui est acceptable, ce
qui ne l'est pas — se lit dans `deployer-moteur.sh` lui-même, côté local, en
une seule lecture.

---

## 3. Trois trouvailles faites en construisant et testant ce script

Ce script n'a pas été écrit puis livré tel quel : il a été testé, pour de
vrai, contre la préproduction réelle en lecture seule et contre un bac à
sable jetable pour les écritures (chapitre 5). Trois faits inattendus en sont
sortis, et changent la façon dont ce script — et tout script futur qui
lirait cet environnement — doit se comporter.

### 3.1 TranslatePress traduit même la sortie de `wp-cli`

En vérifiant `wp_get_environment_type()` par un simple `wp eval "echo
wp_get_environment_type();"`, la préproduction a répondu **`mise en scène`**
— la traduction française du mot anglais « staging » — au lieu de `staging`.
Un `var_export` de la **constante brute** `WP_ENVIRONMENT_TYPE` donnait le
même résultat, avec des espaces parasites autour : `' mise en scène '`.

Ce n'est pas la valeur réelle qui est fausse : `strlen(WP_ENVIRONMENT_TYPE)`
vaut `7` et `bin2hex(WP_ENVIRONMENT_TYPE)` vaut `73746167696e67`, soit
`staging` en toutes lettres. `translatepress-business` et
`translatepress-multilingual` sont actifs sur ce site (`wp plugin list`) et
interceptent, au niveau de la sortie, jusqu'au mot anglais littéral produit
par un `eval` en ligne de commande — pas seulement le HTML rendu au
navigateur. Un script qui comparerait la chaîne affichée à `'staging'`
échouerait donc **à tort**, sur un site parfaitement sain, à cause d'une
extension qui n'a rien à voir avec ce chantier.

**Conséquence dans le script :** la vérification ne lit jamais la chaîne
affichée. Elle compare `bin2hex(wp_get_environment_type())` à l'empreinte
hexadécimale attendue, calculée localement à partir du mot `staging` ou
`production` — un canal que la traduction automatique ne peut pas toucher.
Toute autre lecture de texte produit par ce site (une valeur, un statut, un
libellé) devrait appliquer la même précaution si elle sert une décision
programmatique plutôt qu'un affichage humain.

### 3.2 Le compte MySQL en lecture seule ne voit pas la base de préproduction

`handoff-acces-mysql.md` documente un utilisateur MySQL en lecture seule,
restreint à `dbvkhvlostfyua` (la base de production). Une requête directe
contre `dbzhntllr3st06` — la base de **préproduction**, un nom entièrement
différent, lu par `DB_NAME` dans `wp-config.php` par clé explicite, jamais le
fichier en entier — échoue : `Access denied for user
'u0o3qxc4bioyn'@'localhost' to database 'dbzhntllr3st06'`. Le compte n'a
jamais eu de droit sur cette base : ce n'est pas une régression, personne ne
l'avait vérifié jusqu'ici parce qu'aucun script n'avait eu besoin d'y lire
quoi que ce soit.

**Conséquence dans le script :** la vérification du préfixe Stripe passe par
`wp db query` (`wp-cli`, présent sur le serveur), qui se connecte avec les
identifiants **du site lui-même** — ceux que WordPress utilise pour tourner,
lus en interne par `wp-cli`, jamais par ce script. Ce choix n'est pas
seulement une solution de contournement technique : il respecte mieux la
règle absolue n°2 que l'utilisateur externe en lecture seule, puisque ni
`DB_USER` ni `DB_PASSWORD` ne sont jamais lus, manipulés ni même vus par ce
script, sur aucun des deux environnements.

### 3.3 `grep -c` fait échouer un `&&` quand le compte est zéro

Une première version du script écrivait `[ -f "$FICHIER" ] && grep -Fxc --
"$LIGNE" "$FICHIER" || echo 0` pour compter les occurrences d'une ligne.
`grep -c` **imprime** le compte correct mais **sort en code 1** dès que ce
compte vaut zéro — un comportement documenté mais facile à oublier. Le `&&`
voit ce code 1 comme un échec et déclenche le `|| echo 0`, qui s'ajoute à la
sortie de `grep` au lieu de la remplacer : la ligne `FUNCTIONS_REQUIRE_COUNT`
sortait dédoublée (`0` puis, sur la ligne suivante, un second `0` orphelin),
cassant le compteur pour tout ce qui venait après dans le flux `CLE=valeur`.
Trouvé en testant le mode `functions-facts` contre le bac à sable, avant tout
usage réel, corrigé par une fonction `count_line()` qui n'enchaîne jamais un
`||` sur le code de sortie de `grep -c`.

---

## 4. Méthode de test, sans toucher aux deux sites réels

- **Préalables (lecture seule) :** exécutés pour de vrai contre
  `staging13.linstantcle.ch` et contre `linstantcle.ch`. Résultat le 21
  septembre 2026 : tous verts sur les deux environnements, **sauf** VikStripe
  sur la préproduction, toujours en clés `sk_live_` — le script refuse
  `--appliquer` sur ce seul point, exactement comme demandé, et c'est l'état
  réel du serveur au moment de ce constat, pas une simulation.
- **Écriture, fusion, levier, retour arrière :** exécutés pour de vrai contre
  un bac à sable créé sous `~/www/icl-dev-b9-sandbox/public_html/` sur le
  même serveur — un `wp-config.php` et un `functions.php` factices reprenant
  exactement la forme des vrais fichiers (même ligne d'ancrage, même taille
  de référence), mais aucune installation WordPress fonctionnelle dessous.
  Chaque mode du script y a été rejoué individuellement (sauvegarde, copie
  `rsync --files-from`, fusion, idempotence de la fusion, pose du levier,
  idempotence de la pose, refus de remplacement sans `--forcer-override`,
  remplacement avec, retrait du levier, retrait de l'arborescence, liste des
  sauvegardes, restauration), puis le `--rollback` du script lui-même a été
  lancé pour de vrai contre ce bac à sable et a restauré l'état
  d'origine à l'octet près. Le bac à sable et ses sauvegardes ont été
  supprimés avant la fin de la session ; `staging13.linstantcle.ch` a été
  revérifié intact immédiatement après (même taille de `functions.php`,
  mêmes deux fichiers dans `mu-plugins/`, aucune ligne de levier).
- **Fumée HTTP :** `curl` contre `https://staging13.linstantcle.ch/` (301,
  accepté) et recherche du jeton `--srlm-` (absent aujourd'hui, attendu
  puisque le levier n'est pas encore posé) — les deux branches de la
  vérification du chapitre 6 ont donc été exercées contre le vrai serveur,
  pas seulement lues dans le code.
- **`--appliquer` en production n'a jamais été exécuté**, ni pour de vrai ni
  en bac à sable : ce geste reste, dans cette session comme dans toutes les
  suivantes, celui de Thomas.

---

## 5. Ce que ce script ne couvre pas

- **La recette de bout en bout** (les huit lignes du chapitre B9/B8 de
  `plan-de-marche.md`) reste à faire après un déploiement réussi : filtrage
  des chambres par marque, garde de réservation, e-mails par marque, session
  Stripe, page de confirmation, rappel avant séjour, liste blanche d'hôte.
  Ce script prouve que le code est en place et que le site n'est pas cassé,
  pas que chaque parcours fonctionnel se comporte correctement.
- **L'écran de santé lme-brands** (Outils → Santé lme-brands, chapitre 6.2 du
  constat de déploiement) exige une session d'administration authentifiée :
  resté un contrôle à l'œil, dans un navigateur, comme le décrit le constat
  de déploiement.
- **Le cas de l'hôte inconnu ne résolvant aucune marque** (recette n°1) reste
  couvert par les tests unitaires de `tests/test-core.php`, pas rejoué ici :
  le simuler par HTTP demanderait de forger un en-tête `Host` contre un
  certificat qui ne le couvre pas, pour un cas déjà démontré autrement.
- **`--override-host` pour tester la marque `linstantcle.ch`** en
  préproduction est possible (`--forcer-override`) mais reste un geste
  explicite de qui mène la recette, jamais une valeur que ce script devine.
- **La bascule des clés Stripe de préproduction sur les clés de test** reste,
  comme toujours, un geste de Thomas — ce script le constate et refuse tant
  qu'il n'est pas fait, il ne le fait jamais à sa place.
- **Toute dérive légitime de `functions.php`** en dehors de ce chantier (une
  nouvelle ligne de suivi publicitaire ajoutée par Thomas, par exemple)
  ferait refuser le script au chapitre « ressemble au thème réel » tant que
  la constante `FUNCTIONS_BASELINE_SIZE` (actuellement `7390`, chapitre 1 du
  constat de déploiement) n'est pas mise à jour à la main dans
  `deployer-moteur.sh`, en toute connaissance de cause.
- **`--rollback` ne défait pas un remplacement forcé du levier** vers une
  autre valeur que celle posée par ce chantier : il retire la ligne, quelle
  que soit sa valeur au moment du retour arrière, ce qui est le comportement
  voulu (une seule ligne possible, jamais deux marques en même temps) mais
  ne restitue pas une éventuelle valeur antérieure à ce chantier — il n'y en
  a jamais eu.

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-21 | Création. Script `deployer-moteur.sh`, testé en lecture seule contre la préproduction et la production réelles, et en écriture contre un bac à sable jetable sur le même serveur. Trois trouvailles : la traduction automatique de la sortie `wp-cli`, l'accès refusé à la base de préproduction pour l'utilisateur MySQL en lecture seule (contourné par `wp-cli`, jamais par une nouvelle lecture d'identifiants), et un piège de code de sortie sur `grep -c` corrigé avant tout usage réel. |
