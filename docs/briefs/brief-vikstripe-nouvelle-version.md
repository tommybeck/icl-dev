# Brief — examiner la nouvelle version de VikStripe avant d'écrire à E4J

Version 1, 18 septembre 2026. Rédaction Cowork, exécution Code.

**Destination : `icl-dev/docs/briefs/brief-vikstripe-nouvelle-version.md`.** Écrit hors du dépôt faute d'accès en écriture depuis Cowork. **À déplacer, pas à recopier** : une fois dans `docs/briefs/`, cette copie disparaît.

**Ligne à ajouter au tableau du chantier B de `plan-de-marche.md`, entre B4a et B4 :**

```
| B4b | Examiner la nouvelle version de VikStripe : ce qui a dysfonctionné, et si elle résout la réconciliation | **Opus 5, élevé** | rien, lecture seule |
```

---

## 1. Pourquoi cette lecture précède le signalement à E4J

`constat-incident-1818.md` §1.d établit la cause première du défaut de réconciliation : à `stripe.php:365`, le test de réutilisation de session compare un montant arrondi à deux décimales à un montant qui ne l'est pas. L'égalité échoue dès que le total à payer porte trois décimales, le greffon efface l'option et crée une session neuve **à chaque rendu** de la page de paiement. Deux lignes suffiraient à corriger, et la règle absolue n°1 interdit d'y toucher.

La conclusion était : signaler à E4J. **Mais une version plus récente existe et n'a pas été lue.** Écrire à l'éditeur pour un défaut qu'il a peut-être déjà corrigé serait au mieux inutile, au pire embarrassant. Et si elle le corrige, la question devient de savoir pourquoi elle ne tient pas sur cette installation, ce qui est un tout autre sujet.

**Thomas a installé cette version, elle a dysfonctionné, il est revenu à la précédente, et il ne se rappelle pas comment elle échouait.** Le code et les journaux le savent, sa mémoire non : c'est exactement le genre de fait qui se rétablit par lecture, pas par souvenir.

---

## 2. Les deux copies

| Chemin | Version | Rôle |
|---|---|---|
| `.local/wp-vikstripe/` | **2.2.4**, confirmée par B4a | celle qui tourne en production aujourd'hui |
| `.local/wp-vikstripe-new/` | à établir | celle qui a dysfonctionné, déposée le 18 septembre 2026 |

`.local/` est en lecture seule, et le reste. **Aucune des deux copies n'est modifiée, aucun greffon n'est installé, désinstallé ni déployé par ce travail.**

---

## 3. Ce qu'il faut établir

### 3.a Le numéro de version, d'abord

À sa source dans le fichier, comme pour B4a, et pas d'après le nom du dossier. Relever aussi la version de l'API Stripe que le greffon demande, et la version minimale de Vik Booking qu'il exige, s'il en déclare une.

### 3.b Le défaut de réconciliation est-il corrigé ?

Trois questions, dans cet ordre, chacune avec ses lignes citées **des deux versions en regard** :

1. **Le test de réutilisation de `createSession()`** compare-t-il encore un montant arrondi à un montant qui ne l'est pas ? Si la comparaison a changé, dire exactement comment, et si un total à trois décimales la fait encore échouer.
2. **L'option `stripe_order_<id>`** existe-t-elle encore, et est-elle toujours une case unique qu'un nouveau rendu écrase ? Ou la nouvelle version garde-t-elle plusieurs sessions par commande ?
3. **Un point de terminaison de webhook** est-il enfin appelé ? `constat-perimetre-tunnel.md` §F établit que la 2.2.4 embarque `Stripe/lib/WebhookEndpoint.php` sans jamais l'appeler. Dire si la nouvelle version l'utilise, sur quels événements, et si `checkout.session.completed` en fait partie. C'est la question qui décide si la parade K reste nécessaire.

### 3.c Comment et pourquoi elle a dysfonctionné

**Ne pas se fier au souvenir de Thomas, qui est vide, ni au mien.** Rétablir le fait :

- **Par le code**, en diffant les deux versions : un nom de crochet changé, un crochet supprimé, une signature de rappel modifiée, une version d'API Stripe incompatible avec les réglages du compte, une exigence de version de Vik Booking supérieure à 1.8.14, un appel à une fonction absente sous PHP 8.2.33. `payment_on_after_validation_vikbooking` et `payment_before_begin_transaction_vikbooking` méritent une attention particulière : le premier porte le retour de paiement, le second est utilisé par `lme-brands/includes/payment-brand.php`.
- **Par les journaux**, en établissant d'abord la fenêtre pendant laquelle la nouvelle version a tourné. Les journaux d'accès et d'erreurs de `~/www/linstantcle.ch/logs/` ont déjà servi au constat de la 1818. Chercher les erreurs PHP fatales, les 500, et les échecs d'appel à l'API Stripe sur cette fenêtre.
- **Si la fenêtre ne se retrouve pas**, le dire, et s'en tenir au diff. Une conclusion non étayée vaut moins que l'aveu qu'on ne sait pas.

### 3.d Deux questions ouvertes que cette lecture peut refermer au passage

Sans en faire le cœur du travail, et seulement si les réponses tombent sous les yeux :

- **Le critère de recette n°8 de la phase 4**, laissé ouvert par `constat-phase-4-paiement.md` §2 : la nouvelle version ouvre-t-elle un canal pour `payment_intent_data.metadata` par transaction, ou pour `statement_descriptor_suffix` ? Si oui, le critère redevient atteignable sans appel à l'API.
- **La réserve de paiement** de `constat-reserve-paiement.md` : la nouvelle version rattrape-t-elle un paiement dont le navigateur ne revient jamais ?

---

## 4. Ce que le constat doit conclure

Une recommandation en une phrase, parmi quatre, sans en choisir plus d'une :

1. **Mettre à jour**, le défaut est corrigé et le dysfonctionnement était circonstanciel ou corrigible par un réglage.
2. **Ne pas mettre à jour**, le dysfonctionnement est structurel sur cette installation, et écrire à E4J sur les deux sujets à la fois, le défaut de la 2.2.4 et l'incompatibilité de la nouvelle.
3. **Ne pas mettre à jour**, le défaut n'est pas corrigé, et écrire à E4J en citant la ligne.
4. **Ne pas conclure**, il manque un élément que seul Thomas peut fournir, et dire lequel.

Si la recommandation est de mettre à jour, **elle n'autorise pas à le faire** : une mise à jour de greffon de paiement en production est un geste de Thomas, en préproduction d'abord, avec sauvegarde.

---

## 5. Prompt de lancement

À coller dans Claude Code depuis `~/Documents/icl-dev`. **Opus 5, effort élevé.** Une conclusion fausse ici envoie un signalement à côté de la plaque, ou fait redéployer une version qui casse les paiements.

> Lis `CLAUDE.md`, puis `docs/briefs/brief-vikstripe-nouvelle-version.md` en entier, puis `docs/briefs/constat-incident-1818.md` §1 et §5, `docs/briefs/constat-reserve-paiement.md` §0, et `docs/briefs/constat-perimetre-tunnel.md` §F. La 2.2.4 a déjà été lue trois fois : ne la relis pas en entier, appuie-toi sur ces constats et n'y retourne que pour les lignes que tu compares.
>
> Deux copies coexistent dans `.local/`, en lecture seule : `wp-vikstripe/` qui est la 2.2.4 en production, et `wp-vikstripe-new/` que Thomas a installée, qui a dysfonctionné, et qu'il a remplacée sans se souvenir du symptôme. **Établis la version de la seconde à sa source avant toute autre chose.**
>
> Réponds ensuite aux trois questions du chapitre 3.b, lignes citées des deux versions en regard : le test de réutilisation de `createSession()` compare-t-il encore un arrondi à un non-arrondi ; l'option `stripe_order_<id>` est-elle encore une case unique écrasée à chaque rendu ; un webhook est-il enfin appelé, et sur quels événements.
>
> Puis établis comment et pourquoi la nouvelle version a dysfonctionné, par le diff des deux copies et par les journaux du serveur, en commençant par retrouver la fenêtre pendant laquelle elle tournait. **Si tu ne retrouves pas cette fenêtre, dis-le et tiens-t'en au diff** : n'invente pas un symptôme plausible.
>
> Termine par une recommandation unique parmi les quatre du chapitre 4. **Lecture seule de bout en bout** : aucun fichier de `.local/` modifié, aucun greffon installé ou désinstallé, aucune écriture en base, aucun déploiement, aucune clé Stripe lue ni journalisée. Écris dans `docs/briefs/constat-vikstripe-nouvelle-version.md`.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-18 | Création, après la demande de Thomas d'examiner la nouvelle version avant d'écrire à E4J. |
