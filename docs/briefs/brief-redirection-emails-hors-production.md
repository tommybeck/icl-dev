# Brief — redirection des e-mails hors production

Version 1, 22 septembre 2026. Rédaction Cowork, exécution Code. **À déplacer dans `icl-dev/docs/briefs/`, pas à recopier.**

Proposition de Thomas : un mu-plugin qui détourne tout e-mail sortant vers une adresse unique partout sauf en production, pour pouvoir recetter les vérifications 5 et 7 sans écrire à de vrais clients.

**L'idée est bonne et le principe est le bon** : la décision se prend à l'exécution, d'après l'hôte, et non par un interrupteur à basculer à chaque déploiement. Un site fraîchement cloné est sûr avant même que quiconque l'ait configuré. C'est exactement ce que la recette demande.

**Le code proposé ne se déploie pas en l'état** : quatre défauts, dont deux casseraient la production ou la préproduction dès la première minute.

---

## 1. Les quatre défauts

### 1.a `reservation.sexcaperoom.ch` est absent de la liste, au profit d'une faute de frappe

La liste porte `reservations.sexcaperoom.ch`, au pluriel. **L'hôte réel du tunnel est `reservation.sexcaperoom.ch`, au singulier.**

Et ce n'est pas une coquille sans effet, à cause d'un couplage avec le moteur : sur le tunnel, `includes/url-rewrite.php` réécrit `option_home` vers l'hôte réel de la requête, donc `home_url()` rend `reservation.sexcaperoom.ch` pendant tout le rendu d'une réservation Sexcape Room. Cet hôte n'étant pas dans la liste, **la confirmation d'un client Sexcape Room partirait vers l'adresse fourre-tout au lieu du client, en production.**

### 1.b Le tableau contient des liens Markdown, pas des noms d'hôte

```
'[www.linstantcle.ch](https://www.linstantcle.ch)',
```

Quatre entrées sur six sont dans cette forme, vestige d'un copier-coller. Aucune ne correspondra jamais à un nom d'hôte. Seules `linstantcle.ch` et `sexcaperoom.ch` sont utilisables telles quelles.

### 1.c `$args['headers']` vide fait planter l'envoi

```php
if ( ! empty( $args['headers'] ) ) { … }      // sauté quand headers vaut ''
$args['headers'][] = 'X-Original-To: ' . …;   // '' n'est pas un tableau
```

`wp_mail()` reçoit très souvent `headers` à la chaîne vide. Le bloc de normalisation est alors sauté, et l'ajout qui suit s'applique à une chaîne : sous PHP 8, `[] operator not supported for strings` est une **erreur fatale**. Tout envoi casse. C'est la même famille que l'erreur de `payment-brand.php:73` : une forme d'argument supposée plutôt qu'établie. **Normaliser en tableau inconditionnellement, avant toute lecture.**

### 1.d Le Bcc n'est pas retiré, malgré le commentaire

```php
return stripos( trim( $h ), 'cc:' ) !== 0;
```

Sur `Bcc: quelqu'un@example.com`, `stripos` rend `1` et non `0` : la ligne est conservée. **Le commentaire annonce « Strip any Cc/Bcc headers so nothing leaks » et le code ne retire que le Cc.** Un destinataire réel en copie cachée recevrait bien le message depuis la préproduction. C'est le défaut le plus dangereux des quatre, parce qu'il est silencieux.

---

## 2. Deux durcissements, au-delà des correctifs

### 2.a Ne pas faire reposer la détection sur une valeur qu'un autre greffon réécrit

`home_url()` passe par le filtre `option_home` de `lme-brands`. Le correctif B9b le fait pointer vers l'hôte réel de la requête, donc la détection fonctionne aujourd'hui — **mais elle dépend du comportement d'un autre mu-plugin**, et c'est une dépendance que personne ne verra le jour où elle changera.

**Exiger deux conditions pour considérer un envoi comme production** : `wp_get_environment_type() === 'production'` **et** l'hôte dans la liste. Le premier signal est déjà établi et vérifié sur les deux environnements (`constat-deploiement-moteur.md`), et il ne dépend d'aucun de nos greffons. La sécurité tombe alors du bon côté si l'un des deux se trompe.

L'échappatoire `FORCE_EMAIL_REDIRECT` reste utile pour le cas du clone qui garde le domaine de production, et se garde telle quelle.

### 2.b Une redirection en production doit être bruyante

Si les deux conditions échouent sur un hôte que le registre reconnaît comme une marque, le message part vers le fourre-tout alors qu'un client l'attendait. **Journaliser un avertissement dans ce cas**, comme `mail_brand_leak` le fait déjà pour la marque. Règle absolue n°6 : un e-mail client avalé sans bruit est exactement l'échec silencieux qu'elle interdit.

---

## 3. Ce que ce greffon ne doit pas faire, et pourquoi la recette y tient

**Il ne touche ni `From`, ni `Sender`, ni `Reply-To`.** L'identité d'envoi par marque est posée en amont, par `includes/mail-brand.php`, sur les crochets de Vik. La vérification n°5 de la recette lit les **en-têtes** d'une confirmation : elle reste donc valable, le message arrivant simplement dans la boîte fourre-tout au lieu de celle du client. **C'est précisément ce qui rend cette recette possible**, et c'est à écrire dans l'en-tête du fichier pour que personne n'ajoute plus tard une réécriture d'expéditeur ici.

**Une donnée personnelle voyage.** `X-Original-To` porte l'adresse réelle du client, et la préproduction est une copie de la base de production. Acceptable pour une recette, à ne pas oublier au moment de purger la préproduction.

---

## 4. Où il vit, et comment il se déploie

C'est notre code : il vit dans le dépôt, sous `mu-plugins/`, donc `deployer-moteur.sh` le porte sur les deux environnements par sa liste dynamique. **C'est voulu** : en production il est inerte, et le laisser partout est plus sûr que de le poser à la main en préproduction à chaque recréation.

**Mais pas avant que le défaut 1.a soit corrigé** : déployé tel quel en production, il avalerait les confirmations Sexcape Room.

---

## 5. Prompt de lancement

À coller dans Claude Code depuis `~/Documents/icl-dev`. **Sonnet 5, effort faible.**

> Lis `CLAUDE.md`, `docs/briefs/brief-redirection-emails-hors-production.md` en entier, `docs/briefs/constat-signatures-crochets.md` et `docs/briefs/constat-deploiement-moteur.md` §8.
>
> Écris `mu-plugins/lme-mail-guard/` : un mu-plugin qui détourne tout e-mail sortant vers une adresse unique partout sauf en production. Le brief donne le code proposé par Thomas et ses quatre défauts, aux chapitres 1.a à 1.d : corrige-les tous, et établis la forme réelle de `$args['headers']` en lisant `wp_mail()` du cœur plutôt qu'en la supposant, comme B9d l'a fait pour les crochets.
>
> Applique les deux durcissements du chapitre 2 : production seulement si `wp_get_environment_type()` vaut `production` **et** l'hôte figure dans la liste ; et journalise un avertissement quand un message est détourné alors que l'hôte résout une marque du registre.
>
> **Ce greffon ne touche ni `From`, ni `Sender`, ni `Reply-To`** : l'identité par marque vient de `includes/mail-brand.php` et la vérification n°5 de la recette en dépend. Écris-le dans l'en-tête du fichier.
>
> L'adresse fourre-tout est un réglage, pas une valeur en dur dans le code : une constante de `wp-config.php`, avec un repli inerte — si elle n'est pas définie hors production, **ne pas envoyer** plutôt qu'envoyer à une adresse par défaut. Thomas la posera lui-même.
>
> Tests unitaires purs pour la décision « production ou non » et pour la normalisation des en-têtes. **Ne déploie rien, n'écris rien en base, ne lis aucune clé.** Écris `docs/briefs/constat-mail-guard.md`. Commit et poussée après revue.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-22 | Création, à partir de la proposition de Thomas. Principe retenu, quatre défauts relevés, deux durcissements, et le prompt de B9e. |
