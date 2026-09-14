# Brief — habillage Sexcape Room des pages du tunnel

Chantier D du plan de marche. Relevé tenu par Cowork, implémentation par Claude Code.

---

## Pourquoi ce chantier existe

Les deux sites vivent sur des serveurs distincts : linstantcle.ch sur `gfram1004`, sexcaperoom.ch sur `gvam1277`. Le tunnel de réservation est servi par l'installation linstantcle.ch sous l'hôte `reservation.sexcaperoom.ch`. Il **ne partage donc rien** avec le site sexcaperoom.ch : ni thème, ni gabarits Elementor, ni médiathèque, ni feuille de style.

Le visiteur, lui, ne voit qu'un enchaînement de pages. Si la page où il choisit ses dates ne ressemble pas à celle d'où il vient, il n'y voit pas une prouesse d'architecture, il y voit un site tiers à qui il s'apprête à donner sa carte. **La continuité visuelle n'est pas de l'esthétique ici, c'est de la confiance au moment de payer.**

## Ce que ce chantier n'est pas

Ce n'est pas une refonte du tunnel, ni une amélioration de son ergonomie. On reproduit une identité, on ne la réinvente pas. Toute idée d'amélioration du parcours se note ailleurs et attend son tour.

---

## Périmètre exact

Les écrans à habiller sont ceux que le visiteur Sexcape Room traverse, et eux seuls :

1. recherche de dates et de disponibilité ;
2. résultats et choix de l'expérience ;
3. fiche de l'expérience, si elle est servie par le tunnel ;
4. saisie des coordonnées ;
5. récapitulatif et départ vers Stripe ;
6. page de confirmation après paiement ;
7. page d'erreur ou de paiement abandonné, souvent oubliée et vue au pire moment.

Les pages de l'installation servies sous l'hôte L'Instant Clé ne changent pas. L'habillage est **conditionné à l'hôte**, et cette condition se lit dans le registre des marques, jamais dans une comparaison de chaîne écrite en dur.

---

## Le relevé, fait le 9 septembre 2026

**Bonne nouvelle : le système de design existe déjà, sous forme de variables CSS.** Il vit dans le CSS personnalisé du kit Elementor de sexcaperoom.ch, sous le préfixe `--srlm-`. L'habillage du tunnel n'est donc pas un travail d'oeil et d'approximation, c'est la **copie d'un bloc de variables** et des règles de composant qui s'y adossent.

Point d'attention sur la méthode : les valeurs ci-dessous ont été extraites par recherche ciblée dans le réglage global du kit, pas par lecture intégrale. **Elles sont exactes, la liste peut être incomplète.** Claude Code doit extraire le bloc `:root` en entier avant d'implémenter, plutôt que de repartir de ce tableau.

### Ce que le kit Elementor ne porte pas

`get-page-snapshot` sur la page d'accueil renvoie `global_colors: []`, `global_typography: []` et `fonts_in_use: []`. Les créneaux de typographie du kit sont restés sur les valeurs par défaut d'Elementor, Roboto et Roboto Slab, jamais touchées. Toute l'identité passe par le CSS personnalisé, pas par les jetons Elementor. Conséquence : ne pas chercher la marque dans les réglages globaux d'Elementor, elle n'y est pas.

Autre relevé de la même source : `responsive.overrides: []`, les 178 éléments sont en `desktop_only`. Le comportement responsive vit donc lui aussi dans le CSS personnalisé, par requêtes de média sur `--srlm-arc`. Bonne nouvelle pour le tunnel : une feuille de style copiée emporte l'essentiel du travail.

### Couleurs

| Variable | Valeur | Rôle apparent |
|---|---|---|
| `--srlm-noir` | `#0C0A09` | fond principal, seule couleur relevée en usage direct sur la page |
| `--srlm-laque` | `#16110E` | fond de surface |
| `--srlm-laque-haute` | `#1E1712` | surface en relief |
| `--srlm-laque-basse` | `#100C0A` | surface en creux |
| `--srlm-puits` | `#0A0807` | fond le plus sombre |
| `--srlm-or-haut` | `#F0BE5A` | or clair |
| `--srlm-or` | `#E3AA3E` | or de référence, accent |
| `--srlm-or-bas` | `#A87C22` | or sombre |
| `--srlm-or-poli` | `linear-gradient(158deg,#F0BE5A 0%,#E3AA3E 46%,#A87C22 100%)` | dégradé d'or, boutons et filets |
| `--srlm-or-poli-vif` | `linear-gradient(158deg,#F7CC72 0%,#EDB94E 45%,#BC8C28 100%)` | dégradé au survol |
| `--srlm-or-sombre` | `#241802` | or très sombre |
| `--srlm-laiton` | `#B08A2E` | laiton |
| `--srlm-laiton-mat` | `#7A5E1E` | laiton mat |
| `--srlm-ivoire` | `#F2EDE4` | texte clair |
| `--srlm-etain` | `#B6ADA2` | texte secondaire |
| `--srlm-focus` | `#F2EDE4`, et `#0C0A09` selon le contexte | anneau de focus |

### Rayons

| Variable | Valeur |
|---|---|
| `--srlm-arc` | `150px` de base, redéfini par requête de média entre `140px` et `395px` selon la taille d'écran et la classe de cadre |
| `--srlm-arc-btn` | `18px 18px 4px 4px` |
| `--srlm-arc-puce` | `14px 14px 3px 3px` |
| `--srlm-arc-champ` | `10px 10px 3px 3px` |

Le rayon des boutons et des champs n'est pas uniforme : haut arrondi, bas presque droit. C'est une signature, elle se remarque si elle manque.

### Typographie

| Variable | Pile déclarée | Usage |
|---|---|---|
| `--srlm-serif` | `'Marcellus','Hoefler Text','Times New Roman',serif` | titres |
| `--srlm-sans` | `'Jost','Avenir Next','Helvetica Neue',Arial,sans-serif` | interface, surtitres, boutons |
| `--srlm-voix` | `'Lora','Iowan Old Style','Georgia',serif` | textes de voix, citations |

### Fausse alerte du 9 septembre, corrigée le 12

Le relevé initial concluait que Marcellus, Jost et Lora n'étaient chargées nulle part, sur la seule foi d'une lecture de `get-page-html` limitée à ses premiers 65 Ko sur les 264 Ko que pèse la page. **La conclusion était fausse : le bloc qui charge ces polices vit plus loin dans le document, hors de ce qui avait été lu.**

En relisant le code source complet, les trois familles sont bien déclarées par des règles `@font-face` avec chargement `woff2`, mais **différées** : elles vivent dans un `<noscript id="nitro-deferred-styles">` que NitroPack dépaquette lui-même en JavaScript une fois le rendu critique terminé, plutôt que d'apparaître dans le HTML initial ou dans un lien classique dans `<head>`. C'est un mécanisme d'optimisation de performance standard, pas un défaut du site.

Les sept fichiers, auto-hébergés dans `wp-content/uploads/2026/09/` de sexcaperoom.ch et servis en pratique par le CDN de NitroPack :

- `marcellus-v14-latin_latin-ext-regular.woff2`
- `lora-v37-latin_latin-ext-regular.woff2` et `lora-v37-latin_latin-ext-italic.woff2`
- `jost-v20-latin_latin-ext-regular.woff2`, `-500.woff2`, `-600.woff2`, `-700.woff2`

**Conséquence pour ce chantier : rien à corriger sur sexcaperoom.ch.** Le tunnel doit héberger ses propres copies de ces sept fichiers, dans le thème enfant ou le mu-plugin de linstantcle.ch, avec des règles `@font-face` équivalentes. Pas de lien vers le CDN de sexcaperoom.ch : la contrainte d'implémentation n° 4 plus bas l'interdit déjà, et une bonne raison de plus s'ajoute maintenant qu'on sait que ce CDN dépend d'une révision NitroPack (`rev-861690a`) propre à ce site, qui peut changer sans préavis.

**Ces fichiers ne sont pas déposés dans `.local/` : Cowork n'a pas d'accès réseau sortant pour les récupérer en binaire dans cet environnement.** Code, qui tourne avec un accès internet complet, les télécharge lui-même au moment de construire cette phase, aux URL suivantes (à figer en copie locale dès le téléchargement, ces liens dépendant du cache NitroPack de sexcaperoom.ch et pouvant expirer) :

```
https://cdn-ilephlh.nitrocdn.com/vdEzHfvIUuFDDmKxBuqoUEcGqeZpvQOw/assets/static/source/rev-861690a/sexcaperoom.ch/wp-content/uploads/2026/09/marcellus-v14-latin_latin-ext-regular.woff2
https://cdn-ilephlh.nitrocdn.com/vdEzHfvIUuFDDmKxBuqoUEcGqeZpvQOw/assets/static/source/rev-861690a/sexcaperoom.ch/wp-content/uploads/2026/09/lora-v37-latin_latin-ext-regular.woff2
https://cdn-ilephlh.nitrocdn.com/vdEzHfvIUuFDDmKxBuqoUEcGqeZpvQOw/assets/static/source/rev-861690a/sexcaperoom.ch/wp-content/uploads/2026/09/lora-v37-latin_latin-ext-italic.woff2
https://cdn-ilephlh.nitrocdn.com/vdEzHfvIUuFDDmKxBuqoUEcGqeZpvQOw/assets/static/source/rev-861690a/sexcaperoom.ch/wp-content/uploads/2026/09/jost-v20-latin_latin-ext-regular.woff2
https://cdn-ilephlh.nitrocdn.com/vdEzHfvIUuFDDmKxBuqoUEcGqeZpvQOw/assets/static/source/rev-861690a/sexcaperoom.ch/wp-content/uploads/2026/09/jost-v20-latin_latin-ext-500.woff2
https://cdn-ilephlh.nitrocdn.com/vdEzHfvIUuFDDmKxBuqoUEcGqeZpvQOw/assets/static/source/rev-861690a/sexcaperoom.ch/wp-content/uploads/2026/09/jost-v20-latin_latin-ext-600.woff2
https://cdn-ilephlh.nitrocdn.com/vdEzHfvIUuFDDmKxBuqoUEcGqeZpvQOw/assets/static/source/rev-861690a/sexcaperoom.ch/wp-content/uploads/2026/09/jost-v20-latin_latin-ext-700.woff2
```

Repli si ces liens ont expiré d'ici la construction : ce sont les versions `google-webfonts-helper` standard de Marcellus v14, Lora v37 et Jost v20, régénérables depuis la source Google Fonts.

### Relevé complété le 11 septembre 2026

**En-tête et pied de page.** Pas de gabarits EMCP Themer sur ce site (`list-theme-templates` renvoie une liste vide) : l'en-tête et le pied de page sont deux templates natifs du Theme Builder Elementor Pro, `Sexcape Room Header - Global` (id 21) et `Sexcape Room Footer - Global` (id 23), chacun un unique widget HTML portant tout le balisage. Une sauvegarde du 10 septembre, `SAUVEGARDE en-tête avant refonte nav`, atteste d'une refonte de la navigation la veille : ce qui suit décrit l'état d'après refonte.

En-tête : lien d'évitement, nav à six ancres (`Le lieu`, `Les pièces`, `L'arrivée`, `Réserver`, `Bons cadeaux`, `Accès`), un bouton `Réserver` en style or. **Aucun logo, aucune image.** Le conteneur porte les classes `srlm srlm-hote`, tout le style vient du CSS personnalisé du kit, rien en dur sur l'élément.

Pied de page : trois blocs (identité et accroche, informations d'accès et de trajet avec les conditions d'annulation, liens de la maison), puis une ligne de bas de page « réservé aux personnes majeures ». Le nom de marque n'y apparaît qu'en texte, `<span class="srlm-marque-nom">Sexcape Room</span>` : **c'est la seule occurrence du nom de marque sur les deux gabarits, et ce n'est pas un logo.**

**Logo et favicon : aucun des deux n'existe.** La médiathèque du site ne contient que trois fichiers : deux captures d'écran Elementor générées automatiquement le 11 septembre (les aperçus des templates 21 et 23) et une photo de chambre du 7 septembre. Aucun fichier logo, aucune icône, aucun favicon. **Ce n'est pas un oubli du relevé, c'est l'état réel du site : la marque n'a pas de marque graphique aujourd'hui, seulement un nom en toutes lettres.** Point à trancher par Thomas avant D2, voir plus bas.

**Styles de champ.** Une seule famille de champs existe dans le CSS du kit, `.srlm-champ`, utilisée en grille de 2 ou 4 colonnes (`.srlm-champs`) : c'est le bloc de recherche du moteur de réservation (dates, chambre), pas un formulaire de contact. WPForms n'a aucune présence sur ce site : la page Contact ne porte aucun widget de formulaire, donc aucun style de champ WPForms n'existe à copier.

État au repos, seul état défini :
```css
.srlm-champ label{font-family:var(--srlm-sans);font-weight:600;font-size:.8rem;letter-spacing:.16em;text-transform:uppercase;color:var(--srlm-or)}
.srlm-champ input,.srlm-champ select{width:100%;min-width:0;background:var(--srlm-puits);color:var(--srlm-ivoire);border:2px solid var(--srlm-laiton);border-radius:var(--srlm-arc-champ);padding:.78em .8em;font-family:var(--srlm-sans);font-weight:500;font-size:1.02rem;font-variant-numeric:tabular-nums}
.srlm-champ select{-webkit-appearance:none;appearance:none;background-image:linear-gradient(45deg,transparent 50%,var(--srlm-or) 50%),linear-gradient(135deg,var(--srlm-or) 50%,transparent 50%);background-position:calc(100% - 19px) calc(50% + 2px),calc(100% - 13px) calc(50% + 2px);background-size:7px 7px,7px 7px;background-repeat:no-repeat;padding-right:2.6em}
```

**Aucun état de focus ni d'erreur n'est défini pour `.srlm-champ`.** Le site porte un `:focus-visible` générique (anneau `var(--srlm-focus)`, décalage 3px) qui s'appliquerait par défaut, mais rien de propre au champ : pas de bordure ou de fond distinct au focus, aucune classe ni couleur d'erreur nulle part dans le CSS du kit. Les formulaires du tunnel Vik ont pourtant besoin des trois états.

**Tranché le 12 septembre 2026, avec Thomas.** Focus : bordure `--srlm-or`. Erreur : deux couleurs nouvelles, absentes de la palette relevée, à ajouter au registre au même titre que le reste.

```css
--srlm-alerte: #C9463C;       /* bordure de champ en erreur, titre de bandeau */
--srlm-alerte-clair: #E2776A; /* message d'erreur sous le champ, accent de bandeau */
```

Deux patrons, pas un seul, parce que le tunnel rencontre deux natures d'erreur distinctes :

1. **Erreur de champ**, rattachée à une saisie (date invalide, champ requis). Même gabarit que repos et focus — fond `--srlm-puits`, rayon `--srlm-arc-champ`, bordure 2px — seule la couleur change : bordure et libellé en `--srlm-alerte`, message sous le champ en `--srlm-alerte-clair`, taille ~12.5px.
   ```css
   .srlm-champ.est-en-erreur label{color:var(--srlm-alerte)}
   .srlm-champ.est-en-erreur input,.srlm-champ.est-en-erreur select{border-color:var(--srlm-alerte)}
   .srlm-champ-erreur-message{margin-top:.4em;font-size:.78rem;color:var(--srlm-alerte-clair)}
   ```
2. **Bandeau d'alerte de page**, pour une erreur qui n'est rattachée à aucun champ — chambre devenue indisponible entre l'affichage et la soumission, session expirée, échec avant le départ vers Stripe. Fond `--srlm-laque-haute`, bordure 2px `--srlm-alerte`, même rayon que les boutons (`--srlm-arc-btn`, haut arrondi bas presque droit, pas `--srlm-arc-champ`), titre en `--srlm-alerte-clair`, corps en `--srlm-ivoire`, action de sortie en bouton or existant (`--srlm-or-poli`), jamais un nouveau style de bouton pour l'occasion.
   ```css
   .srlm-bandeau-alerte{background:var(--srlm-laque-haute);border:2px solid var(--srlm-alerte);border-radius:var(--srlm-arc-btn);padding:1em 1.1em;display:flex;gap:12px;align-items:flex-start}
   .srlm-bandeau-alerte__titre{font-family:var(--srlm-sans);font-weight:600;font-size:.75rem;letter-spacing:.14em;text-transform:uppercase;color:var(--srlm-alerte-clair);margin-bottom:.25em}
   .srlm-bandeau-alerte__corps{font-family:var(--srlm-sans);font-size:.9rem;line-height:1.5;color:var(--srlm-ivoire)}
   ```

**Emplacement de ces règles.** EMCP Tools n'expose pas d'écriture sur le CSS personnalisé du kit lui-même (seulement du CSS de page ou un bloc Custom Code Elementor Pro, deux emplacements différents du kit). Thomas les a collées à la main dans Réglages du site → CSS personnalisé, à la suite du bloc `--srlm-*` existant, le 12 septembre 2026 : une seule source pour le système de design, pas une deuxième à suivre.

Cette page d'erreur/paiement abandonné du périmètre (point 7) réutilise ce second patron comme bloc principal, plutôt qu'un troisième traitement.

**Largeur et gouttières.** Une seule règle porte tout le site :
```css
.srlm-shell{max-width:1180px;margin:0 auto;padding:0 22px}
```

**Chargement des polices : résolu, voir plus haut.** Le relevé du 9 septembre se trompait, corrigé le 12 : les trois familles sont bien chargées, en `woff2` auto-hébergé, différées via NitroPack. Rien à corriger sur sexcaperoom.ch ; le tunnel doit héberger ses propres copies des sept fichiers.

### Ce qui ne se copie pas

Le site porte une ornementation forte, en îlots HTML : arches en plein cintre, clés de voûte, reflets, motifs de clé. **Le tunnel n'en reprend rien.** Il reprend les variables, les rayons, les polices et les styles de bouton et de champ. Un tunnel de paiement chargé d'ornements décoratifs distrait au moment où le client doit se concentrer, et double la dette d'entretien pour rien.

---

## Contraintes d'implémentation, pour Claude Code

1. **Rien en dur.** Couleurs, polices, chemins de logo vivent dans le registre des marques du §4.2 du brief principal, comme le reste. Une marque supplémentaire un jour ne doit pas demander de rouvrir une feuille de style.
2. **Sous condition d'hôte, jamais globalement.** Un style qui fuit sur les pages L'Instant Clé est un défaut, même s'il est joli.
3. **Ni Elementor, ni gabarit de thème pour ces écrans.** Ce sont des vues de Vik : les habiller par une feuille de style chargée sous condition d'hôte, et non en reconstruisant les pages.
4. **Pas de dépendance à un domaine tiers.** Ni police, ni image, ni feuille de style appelée depuis sexcaperoom.ch. Tout est servi par l'installation linstantcle.ch.
5. **Exclusion du cache.** Ces pages sont hors NitroPack et hors cache dynamique SiteGround, comme le reste du tunnel.

---

## Qui construit les en-têtes sur linstantcle.ch

Décidé le 10 septembre 2026, après un avertissement d'EMCP à son activation : **EMCP Themer et Ultimate Addons for Elementor construisent tous deux les en-têtes et les pieds de page, et s'injectent au même endroit.** Tant que les deux modules sont actifs, EMCP Themer l'emporte partout où il possède un gabarit correspondant, et Ultimate Addons remplit les créneaux restants.

**Décision : couper le module EMCP Themer, garder Ultimate Addons.** Ultimate Addons porte l'en-tête et le pied de page actuels avec leurs conditions d'affichage ; EMCP a été activé comme outillage, pour la médiathèque, les redirections et la lecture, pas comme constructeur de gabarits. On ne change pas de système d'en-tête par effet de bord d'une installation d'outil.

Le danger n'est pas l'état présent, c'est la règle de priorité : un gabarit Themer créé plus tard, par quiconque, remplacerait silencieusement le vrai en-tête sur une partie des pages. Une panne différée, silencieuse, et qui se découvrirait d'abord sur une page en cache, sur Safari.

**Conséquence pour ce chantier :** l'en-tête et le pied de page Sexcape Room du tunnel se construisent dans **Ultimate Addons**, avec une condition d'affichage sur l'hôte de réservation, et jamais avec `create-theme-template` d'EMCP.

Deux vérifications avant de couper, et une réserve. Vérifier qu'EMCP Themer ne possède encore aucun gabarit, par `list-theme-templates` : s'il en possède, couper le module changerait l'affichage. Vérifier ensuite par `resolve-template` ce qui remplit réellement les créneaux d'en-tête et de pied de page sur une page publique. La réserve : Elementor Pro embarque lui aussi un Theme Builder, ce qui ferait trois systèmes candidats. Si l'en-tête vient en fait d'Elementor Pro, c'est Ultimate Addons qui est de trop, et ce ménage-là se fait à part, jamais pendant ce chantier.

## Recette

1. Une page de sexcaperoom.ch et une page du tunnel, côte à côte sur le même écran : mêmes couleurs, même logo, mêmes polices, même allure de boutons. Comparaison faite à l'oeil, et par prélèvement des valeurs dans l'inspecteur.
2. Le code source d'une page du tunnel ne contient **aucune** occurrence de `linstantcle`, ni dans une URL d'image, ni dans une police, ni dans un commentaire.
3. Aucune requête réseau du tunnel ne part vers un autre domaine que `reservation.sexcaperoom.ch` et Stripe.
4. Les pages L'Instant Clé sont inchangées, vérifié par comparaison avant et après.
5. Vérifié sur Safari et sur iPhone, après purge des deux caches. Sur ces deux-là en particulier, parce que c'est là que les défauts de cette pile apparaissent.
6. La page d'erreur de paiement est habillée elle aussi. C'est celle qu'on oublie et celle qui se voit au plus mauvais moment.

---

## Dette assumée

Cette apparence est une **copie**, pas un partage. Toute évolution graphique de sexcaperoom.ch devra être reportée ici à la main, sinon les deux dériveront lentement l'une de l'autre.

Deux parades, à décider quand l'habillage existera : inscrire ce report dans la procédure de mise à jour du site, ou faire de ce relevé un document vivant qu'on relit à chaque refonte. La seconde suppose que quelqu'un pense à le relire, donc préférer la première.

---

## État au 12 septembre 2026

Le relevé, D1, est **complet**. Le chargement des polices, un temps cru cassé, est résolu (voir plus haut : fausse alerte du 9, corrigée le 12). Deux points restent à trancher par Thomas avant de lancer D2, et non par Cowork ni par Code :

1. **Il n'existe ni logo ni favicon**, sur sexcaperoom.ch ni ailleurs : la marque n'a qu'un nom en toutes lettres. Le brief principal (§4.2) prévoit un chemin de logo par marque dans le registre des marques ; ce chemin n'a rien à pointer aujourd'hui. Deux options : le tunnel se passe de logo et affiche le nom en typographie, comme le fait déjà le pied de page du site, ou un logo est produit avant D2. Ne pas laisser Code deviner.
2. ~~Aucun état d'erreur n'existe pour un champ de formulaire~~ **Tranché le 12 septembre 2026**, voir plus haut : `--srlm-alerte` et `--srlm-alerte-clair`, patron de champ et patron de bandeau de page.

Une fois le point du logo tranché, le prompt D2 du plan de marche peut s'exécuter tel quel : Code télécharge lui-même les sept fichiers `woff2`, aux URL données plus haut (§ Fausse alerte du 9 septembre), pour les héberger dans le tunnel sans dépendre du CDN de sexcaperoom.ch.

**Un point à noter pour la phase de branchement.** La page d'accueil porte déjà, en section 5, un îlot HTML nommé « cadre du moteur (inerte, à remplacer au branchement) ». Le site attend donc le moteur à cet endroit précis, sur sexcaperoom.ch même. Or l'architecture retenue sert le tunnel depuis `reservation.sexcaperoom.ch`. À arbitrer avant la phase 6 : soit cet îlot devient un bouton qui mène au tunnel, soit il accueille un cadre intégré pointant vers l'hôte de réservation, ce qui rouvrirait la question des cookies tiers dans un cadre. La première voie est la plus simple et la plus robuste.
