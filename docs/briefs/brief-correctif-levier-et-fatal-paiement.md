# Brief — deux correctifs après la recette du 21 septembre

Version 1, 21 septembre 2026. Rédaction Cowork, exécution Code. **À déplacer dans `icl-dev/docs/briefs/`, pas à recopier.**

Suite de la recette menée par Thomas sur la préproduction `staging13` après le déploiement de B9. Trois observations remontées, deux constats à en tirer, et un seul vrai défaut.

---

## 1. Ce qui n'est pas un défaut : les chambres et le refus de L'Aparté

**Observé.** Sur `staging13`, une recherche de disponibilité ne rend que L'Indécent, Le Boudoir du Désir et À Huis Clos, les trois expériences Sexcape Room. Une tentative de réservation de L'Aparté est refusée.

**Ce n'est pas une confusion de configuration : c'est le levier qui fait exactement son travail.** `LME_BRANDS_HOST_OVERRIDE` force l'hôte vu par toute la résolution de marque. Tant qu'il vaut `reservation.sexcaperoom.ch`, **la préproduction entière est Sexcape Room**, filtrage de présentation et garde compris. Le refus de L'Aparté est le comportement recherché, sous cet hôte.

**Conséquence pour la recette, à corriger dans le plan de marche.** Le levier ne porte qu'une marque à la fois. Les huit vérifications se mènent donc en **deux passes**, en basculant la valeur de la constante entre les deux :

- passe Sexcape Room, `LME_BRANDS_HOST_OVERRIDE = 'reservation.sexcaperoom.ch'` : les trois expériences sont servies, L'Aparté est refusée ;
- passe L'Instant Clé, `LME_BRANDS_HOST_OVERRIDE = 'linstantcle.ch'`, avec `--forcer-override` : les sept chambres vendues de L'Instant Clé sont servies, une chambre Sexcape Room est refusée, et **aucun jeton `--srlm-*` n'apparaît**.

La vérification n°4 telle qu'elle est écrite, « l'apparence Sexcape Room s'applique, et linstantcle.ch reste intact », **n'est pas vérifiable en une seule passe** et c'est ce qui a induit en erreur.

---

## 2. Le défaut du levier : la préproduction charge ses feuilles de style depuis la production

**Observé par lecture directe du DOM de `https://staging13.linstantcle.ch/fr/`, le 21 septembre :**

```
link[rel=stylesheet] →
  https://reservation.sexcaperoom.ch/wp-content/themes/astra/assets/css/minified/main.min.css
  https://reservation.sexcaperoom.ch/wp-content/themes/astra-child/style.css
  https://reservation.sexcaperoom.ch/wp-content/themes/astra-child/assets/css/sexcaperoom-tunnel.css
  https://reservation.sexcaperoom.ch/wp-content/astra-local-fonts/astra-local-fonts.css
```

Et, pour preuve que le fichier existe bien localement et que ce n'est donc pas un défaut de déploiement :

```
https://staging13.linstantcle.ch/wp-content/themes/astra-child/assets/css/sexcaperoom-tunnel.css
  → 200, 6 962 octets
```

**La cause.** Le levier force l'hôte de résolution, et `includes/url-rewrite.php` réécrit ensuite `content_url`, `option_siteurl` et consorts vers le `host` **déclaré dans le registre** pour la marque résolue. Ce `host` est `reservation.sexcaperoom.ch`, c'est-à-dire **la production**. La préproduction va donc chercher ses ressources sur le site de production, qui ne porte pas le moteur : `sexcaperoom-tunnel.css` n'y existe pas, et `astra-child/style.css` y est la feuille réelle de L'Instant Clé.

**C'est l'explication de l'observation esthétique de Thomas** : un calendrier L'Aparté qui porte certains éléments Sexcape Room et pas les autres. Ce n'est pas une contamination de L'Instant Clé par Sexcape Room, c'est un mélange de deux hôtes. **La vérification n°4 ne prouve donc rien tant que ce défaut n'est pas corrigé.**

**Gravité.** Nulle en production, où le `host` déclaré est le bon. Bloquante en préproduction, où elle fausse toute vérification d'apparence. Et elle a un versant à ne pas négliger : des requêtes partent d'une recette vers le site de production.

**Deux pistes, à trancher par Code sur preuves, sans préférence imposée :**

1. **Découpler.** Le levier n'alimente que la résolution de marque, et `url-rewrite.php` continue de lire l'hôte réel de la requête. Simple, mais la réécriture d'URL n'est alors jamais exercée en préproduction.
2. **Un hôte de substitution par environnement.** Le registre gagne, pour la préproduction seulement, une valeur de `host` qui vaut l'hôte réel de la préproduction. La réécriture reste exercée et devient un no-op vérifiable.

La seconde est la plus fidèle à ce qu'on veut recetter. Établis laquelle tient, et dis pourquoi.

---

## 3. Le vrai défaut : erreur fatale à l'affichage de la page de paiement

**Observé.** Réservation de test du Boudoir du Désir sur `staging13`, une nuit, deux adultes, tarif standard 253 CHF. Le récapitulatif s'affiche entièrement, puis, sous le total : **« Une erreur grave s'est produite sur ce site. »** Le parcours s'arrête là.

C'est une erreur fatale PHP, et elle survient **après** la création de la commande et **pendant** le rendu de la page de paiement. Le message générique est celui de WordPress quand l'affichage des erreurs est coupé ; `debug.log` porte la trace réelle.

**Ce qu'il faut établir, dans cet ordre :**

1. **La trace**, dans `wp-content/debug.log` de la préproduction, à l'horodatage du 21 septembre vers 14 h 38, avec sa pile complète.
2. **Le fichier et la ligne**, et s'ils appartiennent à `lme-brands` — `includes/payment-brand.php` est le premier suspect, puisqu'il s'accroche à `payment_before_begin_transaction_vikbooking`, exactement le point où le rendu bascule vers le paiement.
3. **Si la cause tient au levier ou non.** Question décisive : cette erreur se produirait-elle aussi en production, où l'hôte résout naturellement ? Si elle tient à un état que seul le levier produit, la portée change du tout au tout. **Ne pas conclure sans l'établir.**
4. **L'état laissé derrière.** La commande de test est-elle en `standby` ? Une session Stripe a-t-elle été créée avant l'erreur, en clés de test ? Si oui, elle verrouille une chambre : le dire, et dire comment la libérer par le contrôleur de Vik, jamais par SQL.

**Lecture seule, rien de déployé, aucune clé lue.** Écris `docs/briefs/constat-fatal-page-paiement.md`.

---

## 4. Ce que ce brief ne demande pas

Ni de relancer la recette, ni de toucher à la production, ni de corriger la vérification n°4 dans le plan de marche : Cowork s'en charge. Et **rien sur le montant de 253 CHF** : c'est le tarif standard, pas la remise à trois décimales de la 1818, et il n'y a pas de rapport à chercher là.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-21 | Création, après la recette de Thomas sur `staging13`. Deux observations expliquées par le levier, un défaut de réécriture d'URL en préproduction, une erreur fatale à établir. |
