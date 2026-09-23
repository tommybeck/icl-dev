# Brief — automatiser la recette du moteur

Version 1, 23 septembre 2026. Rédaction Cowork, exécution Code puis Cowork. **À déplacer dans `icl-dev/docs/briefs/`, pas à recopier.**

Objectif : mener les huit vérifications de la recette sans que Thomas ait autre chose à faire que poser deux constantes dans le `wp-config.php` de la préproduction, une fois.

**Le principe.** Ce que la recette vérifie est observable par HTTP, par journal serveur et par requête en lecture. Rien n'exige un œil humain, sauf un formulaire de carte hébergé chez Stripe, que Cowork remplit au navigateur. Ce qui a fait de la recette un travail manuel jusqu'ici, ce n'est pas sa nature, c'est qu'elle n'a jamais été écrite comme un programme.

---

## 1. Ce que Thomas fait, une fois, et plus jamais

| # | Geste | Pourquoi lui seul |
|---|---|---|
| T1 | `define( 'LME_MAIL_GUARD_CATCHALL_EMAIL', '…' )` dans le `wp-config.php` de la préproduction | secret de configuration, déjà à son programme |
| T2 | Basculer VikStripe de la préproduction sur les **clés de test** | clés Stripe, déjà à son programme |

**Et rien d'autre.** Le basculement du levier entre les deux passes, la création des réservations d'essai, la lecture des en-têtes, le déclenchement du rappel : tout cela passe dans le script ou dans le navigateur de Cowork.

---

## 2. Un prérequis technique : la transcription d'enveloppe

Les vérifications 5 et 7 portent sur des **en-têtes d'e-mail**. Les lire aujourd'hui supposerait d'ouvrir la boîte fourre-tout, donc un accès de messagerie que ni Code ni Cowork n'ont, et qu'il n'est pas souhaitable de leur donner.

**Bien plus simple : `lme-mail-guard` écrit l'enveloppe dans un journal, en préproduction seulement.** Le greffon voit déjà passer chaque message au moment du détournement. Qu'il consigne `To` d'origine, `From`, `Sender`, `Reply-To`, l'objet et l'hôte, dans un fichier lisible par SSH, et les deux vérifications deviennent une lecture de fichier.

**Trois gardes.** La transcription ne s'active **jamais** quand le contexte est reconnu comme production, par la même fonction qui décide du détournement, pas par un second test qui pourrait diverger. Elle ne consigne **ni le corps ni les pièces jointes**. Et elle porte une donnée personnelle, l'adresse réelle du destinataire, issue d'une base copiée de la production : à purger avec la préproduction, à ne jamais sortir du serveur.

---

## 3. Le script de recette

`recetter-moteur.sh`, à la racine du dépôt, aux conventions de `deployer-moteur.sh` : **simulation par défaut**, préalables refusants, sortie en erreur au premier contrôle rouge, rapport lisible à la fin.

**Il refuse de tourner ailleurs qu'en préproduction.** `wp_get_environment_type()` doit valoir `staging`, et l'hôte visé ne doit être aucun hôte de production de `lme-mail-guard`. Ce n'est pas une précaution de style : ce script crée des réservations et bascule une constante.

**Il mène les deux passes lui-même**, en posant `LME_BRANDS_HOST_OVERRIDE` par le même mécanisme que `deployer-moteur.sh`, jamais à la main : passe Sexcape Room, puis passe L'Instant Clé, puis remise à la valeur d'entrée quoi qu'il arrive, y compris en cas d'échec.

### Ce qu'il vérifie, et comment

| # | Méthode | Automatisable |
|---|---|---|
| 1 | Marque résolue lue dans la page, et un hôte de substitution inconnu ne résout aucune marque | **entièrement** |
| 2 | Requête sur les quatre vues publiques avec un identifiant de chambre étrangère, assertion d'absence | **entièrement** |
| 3 | Tentative de création de réservation d'une chambre hors marque, puis d'une chambre désactivée : un `403` est attendu, donc **rien n'est créé** | **entièrement** |
| 4 | Présence des jetons `--srlm-*` en passe Sexcape Room, absence en passe L'Instant Clé | **entièrement**, déjà fait par le script de déploiement |
| 5 | Réservation d'essai menée jusqu'à la confirmation, en-têtes relus dans la transcription du §2 | **semi**, voir §4 |
| 6 | Métadonnée de marque et URL de retour relevées à l'émission, dans le journal de `payment-brand.php` ; page de confirmation vérifiée par requête | **entièrement côté site**, voir la réserve ci-dessous |
| 7 | Rappel avant séjour déclenché par `wp cron event run`, en-têtes relus dans la transcription | **entièrement**, après la revue de B5 |
| 8 | Requête sur une URL hors liste blanche, `302` attendu vers `sexcaperoom.ch` | **entièrement**, après G2 |

**Réserve sur la vérification 6.** Le script établit ce que **nous envoyons** à Stripe, ce qui est la seule moitié qui dépende de notre code. Que Stripe l'ait bien **stocké** ne se lit que chez Stripe, donc avec une clé. Le brief ne demande pas d'aller la chercher : un coup d'œil de Thomas au tableau de bord Stripe en mode test, une fois, suffit à refermer ce point, et il n'est pas bloquant.

### Ce que le script laisse derrière, et comment il le reprend

Les vérifications 5, 6 et 7 créent de vraies réservations dans le Vik de la préproduction. **Le script tient la liste des identifiants qu'il crée** et porte un `--nettoyer` qui les annule **par le contrôleur de Vik, jamais par SQL**. La règle de E5 vaut ici : le nettoyage a lieu **y compris quand le test échoue**.

Il marque ses réservations d'un nom et d'une adresse reconnaissables, pour qu'une trace oubliée se voie du premier coup d'œil et ne se confonde jamais avec une vraie.

---

## 4. La seule étape qui reste humaine, et elle est pour Cowork

Le formulaire de carte de Stripe Checkout est une page hébergée chez Stripe. Le script mène la réservation jusqu'à la redirection vers cette page, puis **s'arrête et rend l'URL**.

**Cowork la reprend au navigateur**, saisit la carte de test `4242 4242 4242 4242`, valide, et relance le script en mode « reprise » pour la suite : retour, confirmation, en-têtes, métadonnée. **Ce n'est pas un geste de Thomas.**

Deux garde-fous : cette étape n'existe qu'en mode test, le script refusant de rendre une URL de paiement si VikStripe n'est pas en clés de test, vérifié par le seul préfixe ; et aucun numéro de carte réel n'entre jamais dans ce parcours, la carte de test de Stripe étant publique et sans valeur.

---

## 5. Ce que ce brief ne demande pas

Ni de lire une clé Stripe, ni d'écrire en base par SQL, ni de toucher à la production, ni de remplacer le jugement humain sur ce que le rapport dit. Le script **constate**, il ne conclut pas : la revue de ce qu'il rend reste un travail de Cowork, comme pour chaque phase.

Et il ne dispense pas de la recette en production. Les vérifications 4, 5 et 8 s'y rejouent, à la main et sur une vraie réservation, au moment de B10.

---

## 6. Prompt de lancement

À coller dans Claude Code depuis `~/Documents/icl-dev`. **Sonnet 5, effort moyen.**

> Lis `CLAUDE.md`, `docs/briefs/brief-recette-automatisee.md` en entier, `docs/briefs/constat-script-deploiement.md`, `docs/briefs/constat-mail-guard.md` et le chapitre « La recette du moteur » de `docs/briefs/plan-de-marche.md`.
>
> **Un.** Ajoute à `mu-plugins/lme-mail-guard/` la transcription d'enveloppe du chapitre 2 : `To` d'origine, `From`, `Sender`, `Reply-To`, objet et hôte, dans un journal lisible par SSH, **jamais le corps ni les pièces jointes**, et **jamais** quand le contexte est reconnu comme production — par la même fonction qui décide du détournement, pas par un second test. Tests unitaires purs pour la décision d'activation et pour le format d'une ligne.
>
> **Deux.** Écris `recetter-moteur.sh`, aux conventions de `deployer-moteur.sh` : simulation par défaut, préalables refusants, sortie en erreur au premier contrôle rouge, rapport final. **Il refuse de tourner si `wp_get_environment_type()` ne vaut pas `staging`**, ou si l'hôte visé est un hôte de production. Il mène les deux passes en posant lui-même `LME_BRANDS_HOST_OVERRIDE`, et **restaure la valeur d'entrée quoi qu'il arrive**, échec compris.
>
> Couvre les vérifications 1, 2, 3, 4 et 6 de bout en bout. Pour 5 et 7, appuie-toi sur la transcription. Pour 8, écris le contrôle et laisse-le en échec annoncé tant que G2 n'existe pas, plutôt que de le taire.
>
> **Trois.** Les vérifications 5, 6 et 7 créent de vraies réservations : tiens la liste des identifiants créés, marque-les d'un nom reconnaissable, et porte un `--nettoyer` qui les annule **par le contrôleur de Vik, jamais par SQL**, et qui s'exécute aussi quand le test a échoué.
>
> **Quatre.** À la redirection vers Stripe Checkout, arrête-toi et rends l'URL : un humain saisit la carte de test, puis relance en mode reprise. Refuse de rendre cette URL si VikStripe n'est pas en clés de test, vérifié **par le seul préfixe**, jamais par la valeur.
>
> **Ne déploie rien en production, ne lis aucune clé, n'écris rien en base par SQL.** Tu peux exécuter le script sur la préproduction. Écris `docs/briefs/constat-recette-automatisee.md` : ce que le script couvre, ce qu'il refuse, ce qu'il laisse à l'humain. Commit et poussée après revue.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-23 | Création. Transcription d'enveloppe, script de recette en deux passes, nettoyage des réservations d'essai, et la seule étape humaine ramenée au navigateur de Cowork. |
