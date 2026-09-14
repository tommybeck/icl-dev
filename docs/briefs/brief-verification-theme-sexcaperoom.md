# Brief — vérification du thème et de la structure de sexcaperoom.ch

Écrit le 10 septembre 2026. Destinataire : une conversation Cowork neuve, disposant des outils EMCP sur sexcaperoom.ch.
Objet : établir si les six défauts relevés le 9 septembre 2026 ont été corrigés. **Vérifier, pas réparer.**

---

## 1. Ce que cette conversation fait, et ce qu'elle ne fait pas

**Elle mesure.** Chaque défaut porte un critère de correction falsifiable et une méthode de mesure. Le verdict se prononce sur la mesure du jour, jamais sur le relevé d'origine ni sur une impression.

**Elle n'écrit rien sur le site.** Aucun appel d'outil destructif, aucune écriture de réglage, aucune correction même évidente. Une correction proposée dans le rapport, exécutée dans une autre conversation, reste vérifiable ; une correction glissée en passant ne l'est plus.

**Elle ne traite pas le référencement.** Ni titre, ni description, ni canonique, ni image de partage, ni texte alternatif. Ces points font l'objet d'un chantier distinct. S'ils apparaissent en chemin, les noter en annexe et passer.

**Elle ne traite pas non plus** le contenu rédactionnel, l'intégration du moteur de réservation, ni l'accessibilité au sens large. Cette dernière mérite son propre chantier : le signaler si le sujet affleure, ne pas l'ouvrir ici.

### Ne pas faire confiance au relevé d'origine

Le relevé du 9 septembre a été obtenu par **recherches ciblées** dans deux fichiers de 60 et 67 ko, pas par lecture intégrale. Ses valeurs sont exactes, sa liste peut être incomplète. Chaque vérification ci-dessous **remesure depuis zéro** et ne se contente jamais de confirmer.

---

## 2. Contexte minimal

sexcaperoom.ch, WordPress 7.1, PHP 8.2.33, Elementor 4.2.4 et Pro 4.2.3, thème Astra, hébergé sur `gvam1277.siteground.biz`. Site distinct de linstantcle.ch, sur une autre machine.

Page d'accueil : `post_id` **130**, titre « Sexcape Room », URL `https://sexcaperoom.ch/fr/`, construite sous Elementor, 85 conteneurs et 93 widgets. Cinq autres pages existent : Bons cadeaux (34), Contact (36), Conditions (38), Mentions légales (40), Confidentialité (42).

L'identité visuelle vit dans le **CSS personnalisé du kit Elementor**, sous le préfixe de variables `--srlm-`.

---

## 3. Les six défauts à vérifier

### D1. Les polices déclarées ne sont pas chargées

**Constat d'origine.** Le CSS du kit déclare trois piles : `--srlm-serif` sur `'Marcellus','Hoefler Text','Times New Roman',serif`, `--srlm-sans` sur `'Jost','Avenir Next','Helvetica Neue',Arial,sans-serif`, `--srlm-voix` sur `'Lora','Iowan Old Style','Georgia',serif`. Le HTML rendu de la page 130 ne chargeait qu'une seule police web, **Roboto**, celle du thème. Aucune occurrence de `marcellus`, `jost` ni `lora` sur `fonts.gstatic.com`, aucun `@font-face`, aucun `@import` dans le CSS du kit.

**Pourquoi c'est grave.** Le site s'affiche dans ses replis. Sur macOS ces replis existent et sont élégants, donc le défaut est invisible pour son propriétaire. Sur Windows il retombe sur Times New Roman, Arial et Georgia ; sur Android sur des génériques. La typographie conçue n'est visible que par les visiteurs Apple.

**Critère de correction.** Les trois familles sont effectivement chargées par le site, sur toutes les plateformes, par un mécanisme présent dans le HTML rendu : soit une feuille Google Fonts, soit des `@font-face` pointant des fichiers hébergés par sexcaperoom.ch.

**Mesure.**

1. `emcp-tools/get-page-html` sur `post_id: 130`. Chercher dans le HTML rendu, sans distinction de casse : `marcellus`, `jost`, `lora`, `@font-face`, `fonts.googleapis`, `gstatic.com/s/`.
2. Relever **toutes** les familles servies depuis `gstatic.com/s/<famille>/`, et pas seulement les trois attendues.
3. Chercher un `@font-face` dans le CSS du kit (`emcp-tools/get-global-settings`) et dans le thème enfant s'il existe.

**Trois pièges.**

- Une police auto-hébergée par le thème peut n'apparaître ni dans le kit ni dans le HTML inspecté. Si les trois familles restent introuvables, chercher aussi dans les fichiers du thème avec `emcp-tools/search-files` sur `@font-face`.
- Corriger D1 doit normalement faire **disparaître Roboto** du chargement : une police web chargée et jamais utilisée est une requête perdue. Si Roboto est toujours servie, le noter sans en faire un échec.
- Un chargement présent ne prouve pas un affichage correct. Si le doute persiste, demander à Thomas une capture depuis une machine Windows : c'est la seule preuve directe.

---

### D2. Le kit Elementor ne porte aucun jeton global

**Constat d'origine.** `emcp-tools/get-page-snapshot` sur la page 130 renvoyait `global_colors: []`, `global_typography: []`, `fonts_in_use: []`. Les créneaux de typographie du kit portaient les valeurs par défaut d'Elementor, Roboto et Roboto Slab, jamais modifiées. Toute l'identité passait par le CSS personnalisé.

**Pourquoi c'est un défaut, et pourquoi c'est discutable.** Le CSS personnalisé est une source de vérité parfaitement valable, et le préfixe `--srlm-` est bien tenu. Le problème n'est pas le choix, il est la **coexistence de deux sources** : quiconque modifiera une couleur depuis l'interface d'Elementor la changera au mauvais endroit, sans effet visible, et conclura à un bug.

**Critère de correction.** L'un des deux est vrai, et pas un mélange des deux :

- soit les jetons Elementor reflètent les variables `--srlm-` et le CSS personnalisé les consomme plutôt que de les redéfinir ;
- soit les jetons Elementor restent vides et **une note écrite dans le site** dit où vit la vérité, par exemple dans le CSS personnalisé du kit en commentaire de tête.

**Mesure.** `emcp-tools/get-page-snapshot` sur 130, lire `tokens`. Puis `emcp-tools/get-global-settings` et `emcp-tools/list-variables` : les Variables globales d'Elementor sont l'endroit prévu pour ce genre de jetons, vérifier si elles ont été peuplées depuis.

**Verdict à nuancer.** Si rien n'a changé mais que Thomas assume le CSS personnalisé comme source unique, le verdict est « non corrigé, sans gravité, à documenter » et non « non corrigé ».

---

### D3. Aucune règle responsive dans Elementor

**Constat d'origine.** `responsive.overrides: []`, et les 178 éléments comptés en `desktop_only`. Le comportement responsive vivait entièrement dans les requêtes de média du CSS personnalisé, sur `--srlm-arc`.

**Critère de correction.** Le site se tient à trois largeurs, quelle que soit la technique. Ce n'est pas un défaut d'avoir zéro règle Elementor si le CSS fait le travail : le défaut serait que **personne** ne le fasse.

**Mesure.**

1. `emcp-tools/get-page-snapshot` sur 130 : relire `responsive.overrides` et `responsive.counts`.
2. Compter les requêtes de média dans le CSS personnalisé du kit et relever les points de rupture qu'elles visent.
3. Comparer ces points de rupture aux `viewport_md` et `viewport_lg` déclarés dans les réglages du kit. **Des points de rupture CSS qui ne coïncident pas avec ceux d'Elementor produisent une bande de largeurs où les deux se contredisent** : c'est le défaut à chercher, plus que l'absence de règles.

Si un outil de rendu est disponible, regarder la page à 375, 768 et 1440 pixels de large. Sinon, dire que la vérification est structurelle et non visuelle.

---

### D4. Imbrication profonde et conteneurs vides

**Constat d'origine.** `get-page-snapshot` sur 130 renvoyait deux avertissements : `deep_nesting`, imbrication à six niveaux ou plus, profondeur maximale relevée à 8 ; et `empty_container`, un ou plusieurs conteneurs sans enfant.

**Pourquoi ça compte.** La profondeur ralentit le rendu et rend chaque modification risquée. Les conteneurs vides sont soit des emplacements en attente, soit des restes. Les premiers sont légitimes et se reconnaissent à leur étiquette, les seconds sont du déchet.

**Critère de correction.** Profondeur maximale ramenée à 6 ou moins, ou justification écrite. Et **chaque** conteneur vide restant porte une étiquette qui dit pourquoi il est vide.

**Mesure.** `emcp-tools/get-page-snapshot` sur 130 : relire `counts.max_depth` et les `warnings`. Puis `emcp-tools/get-page-structure` pour lister les conteneurs sans enfant, avec leur étiquette et leur chemin.

**À savoir avant de juger.** Plusieurs conteneurs vides étaient nommés « Photo — déposer l'image ici », « Clé de voûte (décor, ne rien y mettre) » ou « Motif de clé ». Les premiers sont des emplacements en attente de média, les seconds sont **décoratifs par construction** et doivent rester vides. Ne pas les compter comme des défauts.

---

### D5. Les emplacements photo sont toujours vides

**Constat d'origine.** La page 130 comptait **une seule image** pour 866 mots, et six blocs « Photo à venir » avec leur mention de cadrage. Les trois pièces, la façade et le héros attendaient toutes leur photo.

**Pourquoi c'est ici et pas dans un chantier de contenu.** Un site qui vend une expérience sensorielle avec une image sur sept sections ne souffre pas d'un manque de contenu, il souffre d'un défaut de préparation à la mise en ligne. C'est structurel.

**Critère de correction.** Chaque bloc « Photo à venir » a reçu son image, ou le bloc a été retiré. Aucun texte « Photo à venir » ni « déposer l'image ici » ne subsiste sur une page publiée.

**Mesure.** `emcp-tools/get-page-snapshot` sur 130 : lire `content.image_count` et chercher dans `content.headings` les mentions « Photo à venir ». Répéter sur les cinq autres pages. `emcp-tools/list-media` donne l'état de la médiathèque.

Le texte alternatif des images relève du chantier référencement et accessibilité : le compter, ne pas le juger.

---

### D6. L'îlot du moteur de réservation

**Constat d'origine.** La section 5 « Réserver » de la page 130 contient un widget HTML étiqueté « Îlot — cadre du moteur (inerte, à remplacer au branchement) », `element_id: 46f8434`.

**Pourquoi le vérifier.** Le site attend le moteur **à cet endroit, sur sexcaperoom.ch**, alors que l'architecture arrêtée le sert depuis `reservation.sexcaperoom.ch`. Deux voies : un bouton qui mène au tunnel, simple et robuste ; ou un cadre intégré pointant vers l'hôte de réservation, ce qui rouvre la question des cookies tiers dans un cadre. La première est recommandée.

**Critère de correction.** Aucun, à ce stade : l'îlot **doit** rester inerte jusqu'au branchement. Ce qui se vérifie ici, c'est qu'il n'a pas été remplacé par un cadre intégré ou par un lien mort en attendant.

**Mesure.** `emcp-tools/get-element-settings` sur la page 130, élément `46f8434`. Relever s'il contient une balise `iframe`, un lien sortant, ou un formulaire. Signaler tout changement d'état.

---

## 4. Hygiène du thème, à relever au passage

Ces points n'étaient pas des défauts constatés mais n'ont pas été inspectés. Les établir une fois pour toutes évite d'y revenir.

1. **Un thème enfant existe-t-il ?** `emcp-tools/list-themes`. Si le thème actif est Astra sans enfant, toute personnalisation de fichier serait effacée à la prochaine mise à jour. Où vit le CSS personnalisé compte autant que son contenu.
2. **Gabarits Themer** : `emcp-tools/list-theme-templates`. En-tête et pied de page existent-ils, et avec quelles conditions d'affichage ? Un gabarit sans portée explicite reçoit « tout le site » sans que rien ne le signale.
3. **Le slug de la page d'accueil** est `sexcape-room-variante-controles-standards`, un nom de travail resté en place. Sans conséquence tant que c'est la page d'accueil, gênant le jour où elle ne l'est plus. La question de l'URL appartient au chantier référencement ; la présence du nom de travail appartient à l'hygiène.
4. **Extensions actives** : `emcp-tools/list-plugins`. Relever ce qui tourne, et notamment si un cache est en place sur ce site, ce qui changerait la lecture de toute mesure faite sur le HTML rendu.

---

## 5. Forme du rapport

Un fichier, `docs/briefs/revue-theme-sexcaperoom.md` dans le dépôt `icl-dev` si le dossier est accessible ; sinon le rapport rendu dans la conversation, Thomas le déposera.

Une section par défaut, D1 à D6, chacune avec quatre lignes :

- **Verdict** : Corrigé, Partiellement corrigé, Non corrigé, ou Non vérifiable.
- **Preuve** : l'appel d'outil et ce qu'il a renvoyé. Une valeur, pas une paraphrase.
- **Écart** : ce qui manque pour atteindre le critère de correction.
- **Recommandation** : quoi faire, en une phrase. Sans l'exécuter.

Puis la section hygiène, puis une annexe listant ce qui a été aperçu et renvoyé à un autre chantier, référencement ou accessibilité.

**Un verdict « Non vérifiable » est un résultat honnête**, à préférer à une conclusion tirée d'une mesure partielle. Dire ce qui a été lu et ce qui ne l'a pas été.

---

## 6. Ce qui ferait échouer cette vérification

- Confirmer le relevé d'origine sans remesurer. C'est le risque principal : le relevé est là pour être contredit, pas recopié.
- Corriger un défaut en passant, ce qui rend le verdict incontrôlable.
- Juger l'apparence depuis un Mac et conclure que D1 est corrigé. Les replis y sont trop beaux pour révéler le défaut.
- Compter comme défauts les conteneurs décoratifs volontairement vides.
- Déborder sur le référencement, qui a son chantier.
