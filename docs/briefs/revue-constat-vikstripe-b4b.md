# Revue — constat B4b, la nouvelle version de VikStripe

Version 1, 19 septembre 2026. Rédaction Cowork, revue du constat rendu par Code au commit `6fe7ef0`.

**Destination : `icl-dev/docs/briefs/revue-constat-vikstripe-b4b.md`.** Écrit hors du dépôt faute d'accès en écriture depuis Cowork. **À déplacer, pas à recopier** : une fois dans `docs/briefs/`, cette copie disparaît.

---

## Verdict

**Trois des quatre questions du brief sont tenues, et bien tenues. La quatrième est surinterprétée.**

Le constat établit, preuves à l'appui et lignes en regard, que la copie installée est la même 2.2.4, que la ligne 365 est intacte, que l'option reste une case unique et qu'aucun webhook n'est appelé. Cette partie est solide et se cite telle quelle.

En revanche, **« elle n'a jamais tourné » n'est pas démontré**, et le constat le présente comme le fait central, établi « par trois voies indépendantes ». Les trois voies n'en font qu'une : *le dossier du greffon contient aujourd'hui l'état d'avant le 15 septembre*. Une restauration de sauvegarde de fichiers produit exactement la même observation, et **le corpus de ce chantier documente des restaurations de sauvegarde ce jour-là précisément**.

**La recommandation unique tient quand même**, mais pas par l'argument sur lequel le constat s'appuie le plus. Détail au §5.

---

## 1. La version est établie à sa source. Acquis.

`vikstripe.php:5` et `:21` relevés des deux côtés, en-tête et constante concordants, plus un tableau des autres déclarations, bibliothèque Stripe, version d'API, minima WordPress et PHP. L'hypothèse d'une exigence de Vik Booking est écartée sur constat d'absence, pas par oubli. Rien à redire.

Un point à nommer, que le constat laisse implicite : **il n'est pas établi que l'archive déposée dans `.local/` le 18 septembre soit celle téléversée le 15.** Les journaux donnent `package=6609`, un identifiant interne à WordPress, qui ne se rattache à aucun fichier du poste de Thomas. Les dates internes uniformes du 26 mai 2026 disent que c'est bien une archive d'éditeur, non une copie de serveur, ce qui suffit pour répondre à la question qui importe à E4J, « l'éditeur a-t-il corrigé ». Cela ne suffit pas pour affirmer « voici ce que Thomas a installé ». **Une question à Thomas règle le point : cette archive vient-elle de son téléchargement de septembre, ou l'a-t-il reprise chez vikwp.com le 18 pour ce travail ?**

## 2. Le défaut de la ligne 365 n'est pas corrigé. Acquis, et c'est la meilleure partie du constat.

Les deux fichiers sont identiques jusqu'à la ligne 470, la numérotation comprise, ce qui rend toutes les citations de `constat-incident-1818.md` valables des deux côtés. Le `diff` complet ne rend que deux écarts, une virgule finale et un saut de ligne, tous deux sans effet sous PHP 8.2. La comparaison d'un arrondi à un non arrondi est là, au même numéro, caractère pour caractère.

L'apport le plus utile du constat pour la suite est ailleurs, au §4.6 : **la ligne fautive de production est mot pour mot celle de l'archive de l'éditeur.** Sans cela, l'écriture isolée du 11 décembre 2025 sur `stripe.php` aurait rendu tout signalement fragile, E4J pouvant répondre que le fichier a été modifié chez nous. Ce point était nécessaire et il a été fait.

## 3. Aucun webhook. Acquis, mais pas par la commande affichée.

La conclusion est bonne, et elle tient par le chemin fort : les quatre fichiers de webhook de la bibliothèque sont **identiques à l'octet près** à ceux de production, dont `constat-perimetre-tunnel.md` §F établissait déjà qu'ils ne sont jamais appelés. L'identité suffit, la recherche est surabondante.

Elle est heureusement surabondante, parce que **la commande imprimée au §2.3 ne prouve rien** :

```
grep -rn "Webhook|constructEvent|register_rest_route|wp_ajax_|admin_post_" …
```

C'est une alternance de `grep -E` écrite dans une expression de base. Telle quelle, `grep` cherche la chaîne littérale barres verticales comprises, et ne trouve évidemment rien. La commande réellement exécutée figure en annexe, correctement échappée en `\|`. **C'est la version fautive qui est affichée à l'endroit où le lecteur vérifie.** À corriger dans le constat : un jour quelqu'un reprendra cette ligne pour contrôler, obtiendra un silence, et le prendra pour une confirmation.

Deuxième réserve, mineure et sans conséquence ici : la recherche ne couvre que la racine, `vikbooking/` et `tmpl/`. Elle n'énumère pas les autres dossiers du greffon. L'argument d'identité la rattrape.

## 4. La cause du dysfonctionnement n'est pas démontrée. C'est le point de cette revue.

### 4.1 Ce que le constat démontre réellement

Que le dossier `wp-vikstripe/` en production porte aujourd'hui, et portait déjà au rapatriement SFTP du 15 septembre à 12 h 26, le code d'avant la manœuvre : fichiers datés du 23 octobre 2025, journaux `.tx` remontant au 25 octobre 2025, taille de `vikstripe.php` à 9 335 octets et non 9 744.

C'est exact, et c'est utile. Cela établit que **le code de l'archive n'est pas en place**, et qu'il ne l'était déjà plus trois heures après la manœuvre.

### 4.2 Ce qu'il en conclut à tort

Que le remplacement n'a jamais eu lieu. Les trois preuves avancées, dates des fichiers, continuité des `.tx`, dossier jamais détruit, ne sont pas indépendantes : elles constatent toutes le même état présent. **Deux histoires produisent cet état :**

- **A.** Le remplacement a échoué et WordPress a remis le dossier en place depuis `upgrade-temp-backup`. C'est la lecture du constat.
- **B.** Le remplacement a réussi, le greffon a tourné quelques minutes, puis **une restauration de sauvegarde de fichiers a rendu le dossier à son état antérieur**, horodatages compris.

### 4.3 Pourquoi l'histoire B n'est pas une vue de l'esprit

`revue-encaissements-non-confirmes.md` 1.1, dans ce dossier, établit **des restaurations de sauvegarde le 15 septembre 2026** : « les collisions observées le 15 septembre, sur 1814, 1815 et 1816, s'expliquent par les restaurations de sauvegarde de cette journée-là, qui ont effacé des commandes et libéré leurs identifiants ». Le pluriel est dans le texte. C'est aussi la journée où une vraie réservation a disparu et a été recréée sous l'identifiant 1819.

Autrement dit : le jour même de la manœuvre, à quelques dizaines de minutes près, Thomas a restauré au moins une sauvegarde. Et `journal-vik.md` porte en règle permanente « instantané de base de données avant toute série de modifications de configuration » : prendre un instantané juste avant de remplacer un greffon de paiement est exactement ce que la maison prescrit.

L'histoire B recolle par ailleurs le souvenir de Thomas, que le constat doit sinon écarter comme une confusion. Il se rappelle avoir installé, vu quelque chose de cassé, et être revenu en arrière. Le constat cherche ce retour en arrière **dans l'interface des greffons** et n'en trouve pas, ce qui est juste : une restauration Site Tools ne laisse aucune trace dans `wp-admin`.

### 4.4 Ce qui contraint l'histoire B, et ce qui ne la contraint pas

Une restauration de fichiers devrait avoir effacé ce qui a été écrit entre l'instantané et elle. Deux repères bornent donc l'instantané :

- le journal `Stripe/2033980266-1813.tx`, **15 septembre 03 h 57**, est présent : l'instantané lui est postérieur ;
- le greffon `emcp-pro`, mis à jour le **15 septembre à 06 h 39 36**, a survécu : l'instantané lui est postérieur aussi.

La fenêtre pour l'instantané est donc 06 h 39 36 à 09 h 44 05. Étroite, et c'est une objection sérieuse à l'histoire B **si la sauvegarde est la sauvegarde quotidienne de SiteGround**. Elle ne l'est plus du tout si Thomas a pris un instantané manuel avant de toucher au greffon, ce que la règle du journal lui demande de faire.

Et un élément que le constat verse au crédit de l'histoire A joue en réalité pour les deux : **le trou de `debug.log` entre 06 h 39 34 et 10 h 11 42**, qui encadre la manœuvre. Le constat y lit l'absence d'incident. Une restauration de fichiers aurait rendu `debug.log` à son état de l'instantané et effacé précisément ces lignes. **Ce silence est donc fabriqué par l'histoire B autant qu'il est attendu par l'histoire A. Il ne départage rien, et le constat s'en sert comme s'il départageait.**

### 4.5 Une tension interne, tant qu'on y est

Le §4.2 fonde sa démonstration sur les horodatages. Le §4.6 relève que `staging10` porte le même `stripe.php` **à la nanoseconde près** que la production, ce qui est la signature d'une copie qui préserve les dates. Le constat prouve donc lui-même que, sur ce compte, des opérations déplacent des fichiers sans toucher à leurs dates. **Un argument d'horodatage ne peut pas être décisif dans un environnement où l'on vient d'établir que les horodatages voyagent.**

### 4.6 Ce qui tranche, et que personne n'a encore regardé

**L'historique des restaurations dans Site Tools.** Il donne la date, l'heure et la portée de chaque restauration, fichiers, bases, ou les deux. Lecture seule, quelques clics, geste de Thomas.

- Aucune restauration le 15 septembre, ou une restauration de bases seules : l'histoire A est la bonne, le constat a raison, et il suffit d'en retirer le mot « démontré » appliqué aux trois voies.
- Une restauration de fichiers entre 09 h 44 et 12 h 26 : le §4 du constat est à réécrire, et le souvenir de Thomas était juste.

**Tant que cette lecture n'est pas faite, la formulation qui tient est : le code de l'archive n'est pas en place et ne l'était déjà plus trois heures après la manœuvre. Rien de plus.**

### 4.7 Deux imprécisions à corriger au passage

- **110 ou 113 fichiers `.tx`.** Le §2 en annonce 110, le §4.2 en compte 113. L'écart vient sans doute de ce que la copie `.local/` date du 15 septembre à 12 h 26 et que le serveur en a écrit depuis, mais **le constat ne documente qu'un seul `.tx` postérieur à ce rapatriement**, celui de la 1818 du 17 septembre. Les deux autres ne sont pas expliqués, et le lecteur bute. À éclaircir en une ligne.
- **§4.4, « le greffon fonctionnait normalement le soir du 15 septembre, avec le code qu'il avait le matin ».** La seconde moitié ne suit pas. L'avertissement cité tombe à `stripe.php:326`, et la numérotation est identique dans les deux copies jusqu'à la ligne 470 : il serait le même avec l'un ou l'autre code. Il établit que le greffon tournait, pas lequel.

---

## 5. La recommandation tient, et voici sur quelle jambe

**Recommandation n°3, ne pas mettre à jour et écrire à E4J en citant la ligne : retenue.**

Elle repose sur deux arguments, l'un solide et l'autre qui vient d'être fragilisé.

- **Solide, et suffisant à lui seul :** les deux copies sont fonctionnellement identiques sur cette installation. Rien à gagner à installer l'archive, et rien qui permette d'imputer une panne à son code, puisqu'il n'y a pas de chemin d'exécution qui diffère. Cela exclut la recommandation n°2 sans avoir besoin de savoir si l'archive a tourné.
- **Fragilisé, et à ne plus invoquer :** « le code n'a jamais été en place, donc aucune incompatibilité n'a pu s'y manifester ». Si l'histoire B est la bonne, la prémisse tombe. La conclusion, elle, ne tombe pas, parce que l'argument d'identité la porte déjà.

**Le constat exclut la n°2 en citant les deux arguments comme s'ils se renforçaient. Il faut garder le premier et retirer le second.** C'est la seule correction que la recommandation elle-même appelle.

La n°4, ne pas conclure, ne se rouvre pas non plus : la question que le brief posait, « l'éditeur a-t-il déjà corrigé », est répondue sans réserve, et c'est elle qui bloquait le signalement. Ce qui reste ouvert, la restauration du 15 septembre, ne change ni ce qu'on écrit à E4J ni ce qu'on décide de faire du greffon.

---

## 6. Ce que cela implique pour le signalement à E4J

**Le signalement part, et il n'attend pas la réponse sur la restauration.**

**Ce qu'il contient, par ordre :**

1. **Le défaut, à la ligne.** `stripe.php:365` de la 2.2.4 : `$checkout_session->amount_total / 100 == $this->get('total_to_pay')` compare un montant arrondi à deux décimales à un montant qui ne l'est pas. Dès que le total porte trois décimales, l'égalité échoue, l'option est effacée et une session neuve est créée **à chaque rendu de la page de paiement**. Cas reproductible fourni : coût 253.30, remise 250.767, total 2.533, session à 253 centimes, `253 / 100 == 2.533` faux. Correctif de deux lignes proposé, comparaison de centimes entiers des deux côtés.
2. **L'empreinte de la construction visée**, et pourquoi elle est nécessaire. Deux constructions distinctes circulent sous le numéro 2.2.4, le `changelog.md` s'arrêtant à la 2.2.3 dans les deux. Donner la somme `sha256` de `stripe.php` de l'archive du 26 mai 2026, et non celle de notre fichier de production, que le §4.6 montre avoir été réécrit seul le 11 décembre 2025. Sans cette empreinte, E4J ne saura pas de quel fichier on parle.
3. **Les deux amplificateurs, séparément et comme demandes d'évolution, pas comme bogues.** L'option `stripe_order_<id>` est une case unique écrite par `add_option` après `delete_option`, sans historique : la dernière session créée gagne et les précédentes sont perdues pour le site. Et aucun point de terminaison de webhook n'est appelé, la bibliothèque Stripe étant embarquée sans jamais l'être : la confirmation de paiement repose entièrement sur le retour du navigateur du client. **Corriger la ligne 365 seule ne supprime pas la classe de panne « client débité, réservation non confirmée ».** C'est ce que E4J doit comprendre, sans quoi il fermera le ticket sur le correctif d'arrondi.
4. **La pratique de publication**, en une phrase et sans reproche : republier une construction différente sous le même numéro de version, sans entrée au journal, rend impossible de savoir ce qui tourne. C'est une demande légitime et elle coûte peu à formuler.

**Ce que le signalement ne contient pas :** aucune incompatibilité de la nouvelle version, aucun symptôme observé sur notre installation en dehors de la 1818, et rien qui suppose une cause dont nous n'avons pas la preuve. Un signalement qui mélange un défaut établi et un incident mal compris se fait fermer sur le second.

**Ce que le signalement ne change pas.** La parade K reste nécessaire, réconcilier par `metadata.booking_id` et jamais par l'option. Même si E4J corrige demain, nous ne déploierons pas une mise à jour de greffon de paiement sans recette, et la 1818 restera non rattrapée sans parade de notre côté. Le geste H, sortir la page 845 et toute URL portant un `sid` de NitroPack, reste le premier de la liste et ne dépend de personne.

---

## 7. Ce que cette revue demande

| # | Quoi | Qui |
|---|---|---|
| 1 | Lire l'historique des restaurations dans Site Tools pour le 15 septembre 2026 : heure et portée, fichiers ou bases | Thomas |
| 2 | Dire si l'archive de `.local/wp-vikstripe-new` est celle du 15 septembre ou un téléchargement du 18 | Thomas |
| 3 | Corriger dans le constat : la commande du §2.3, l'écart 110 ou 113, la phrase du §4.4, et remplacer « démontré » par la formulation bornée du §4.6 de cette revue | Code |
| 4 | Verser dans le dépôt le fait des restaurations du 15 septembre, aujourd'hui écrit dans le seul dossier Communication | Cowork, fait par cette revue |
| 5 | Rédiger le signalement E4J sur le plan du §6 | Cowork |

**Une leçon de méthode, et elle n'est pas contre Code.** Le fait qui manquait à ce constat, les restaurations du 15 septembre, existait depuis deux jours, mais dans `revue-encaissements-non-confirmes.md`, qui vit dans le dossier Communication et non dans le dépôt. Code ne pouvait pas le connaître. La règle « ce qui n'est pas écrit dans `docs/` n'existe pas » se lit donc aussi dans l'autre sens : **un fait établi par Cowork et gardé hors du dépôt est un fait que Code ignorera, et il conclura sans lui.**

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-19 | Création. Revue du constat B4b : trois questions tenues, la cause surinterprétée, hypothèse concurrente de la restauration de sauvegarde du 15 septembre, recommandation confirmée sur l'argument d'identité, plan du signalement E4J. |
