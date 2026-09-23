# Revue thème et structure — sexcaperoom.ch

Vérification initiale du 10 septembre 2026, sur la base du brief du même jour. Objet : mesurer si les six défauts relevés le 9 septembre ont été corrigés. Aucune écriture n'a été faite sur le site ; toutes les mesures sont fraîches, aucune n'a été recopiée du relevé d'origine.

**Mise à jour du 11 septembre 2026** — repasse après les changements de Thomas. Par sa demande, les images manquantes et la notice "Photo à venir" (D5) ne sont plus évaluées dans cette repasse. Les verdicts D1 et D2 ci-dessous ont été remesurés et corrigés ; D3, D4 et D6 sont inchangés. Une section "Ce qui reste ouvert au 11/09" résume l'état courant en tête de document.

## Ce qui reste ouvert au 11/09

- **D3 — responsive** : toujours non corrigé, inchangé.
- **D4 — imbrication (profondeur)** : toujours non corrigé, inchangé (l'étiquetage des conteneurs vides, lui, était déjà en ordre).
- **D6 — îlot du moteur de réservation** : toujours non corrigé, inchangé — même formulaire à l'apparence fonctionnelle qui ne mène nulle part.
- **Hygiène — pieds de page en doublon** : toujours deux templates footer dans Elementor Pro (id 23 et id 25, ce dernier avec la faute "Foloter"), impossible de déterminer lequel est assigné avec les outils disponibles.

**Résolu depuis le 10/09 :**

- **D1 — polices** : corrigé. Marcellus, Lora et Jost sont maintenant chargées via `@font-face` auto-hébergés sur `sexcaperoom.ch/wp-content/uploads/2026/09/`, confirmé par les styles calculés en direct sur le site (`Lora`, `Jost` et `Marcellus` s'appliquent réellement, plus seulement en CSS déclaré). Roboto a disparu du chargement, comme attendu.
- **D2 — jetons du kit** : corrigé. Les 4 créneaux de typographie système (`primary`/`secondary`/`text`/`accent`) portent maintenant Marcellus/Marcellus/Lora/Jost, en plus des couleurs déjà synchronisées la veille (Étain/Noir/Ivoire/Or).

**Nouveau, hors des six défauts d'origine, repéré en vérifiant D1 :** la page d'accueil affiche désormais deux balises H1 (le titre Elementor du héros, correctement stylé, et un `entry-title` généré par le thème Astra au-dessus, avec la police par défaut) — probablement un effet de bord du renouvellement de la navigation (un gabarit de sauvegarde "SAUVEGARDE en-tête avant refonte nav 2026-09-10" existe dans la bibliothèque de templates). Je n'ai pas creusé plus loin : ce n'est ni dans le périmètre des six défauts ni dans celui du référencement, mais deux H1 sur une page est le genre de détail qui vaut la peine d'être su.

Par ailleurs, en marge et sans jugement (hors périmètre SEO) : Rank Math SEO et un plugin Redirection ont été activés depuis le 10/09, et l'URL canonique de la page d'accueil est passée de `/fr/` à la racine du domaine — l'ancienne URL `/fr/` renvoie désormais une page 404 en la testant directement.

## D1 — Les polices déclarées ne sont pas chargées

**Verdict au 10/09 : Non corrigé. Verdict au 11/09 : Corrigé.**

**Mise à jour du 11/09.** `@font-face` self-hébergés confirmés dans les feuilles de style chargées par le navigateur pour Marcellus (`marcellus-v14-latin_latin-ext-regular.woff2`), Lora (regular + italic) et Jost (regular, 500, 600, 700), tous servis depuis `sexcaperoom.ch/wp-content/uploads/2026/09/`. Confirmation par styles calculés en direct : le chapô utilise réellement `Lora, "Iowan Old Style", Georgia, serif`, le surtitre `Jost, "Avenir Next", "Helvetica Neue", Arial, sans-serif`, le titre du héros `Marcellus, "Hoefler Text", "Times New Roman", serif` — plus seulement déclaré en CSS, mais bien appliqué. Roboto a disparu du chargement (recherché dans le HTML rendu complet : 0 occurrence hors de la pile système générique d'un `entry-title` du thème, voir plus bas). Aucun objet à signaler ici.

**Constat du 10/09 (pour mémoire) :**

**Preuve.** `get-page-html` sur `post_id 130` (HTML rendu complet) : aucune occurrence de `marcellus`, `jost` ou `lora` en dehors de leur déclaration dans les variables CSS `--srlm-serif`, `--srlm-sans`, `--srlm-voix` du kit. La seule police web réellement chargée est Roboto, via des règles `@font-face` injectées par NitroPack (`<style id="nitro-fonts">`, fichiers servis depuis `fonts.gstatic.com/s/roboto/...`). Aucun `<link>` Google Fonts, aucun `@font-face` pour Marcellus, Jost ou Lora. `search-files` sur `@font-face` dans `wp-content/themes/astra-child` : 0 résultat (le thème enfant ne contient que `functions.php` et un `style.css` de 62 octets).

Confirmation en direct dans un navigateur sur `sexcaperoom.ch` : `getComputedStyle(h1).fontFamily` renvoie bien `Marcellus, "Hoefler Text", "Times New Roman", serif` (la pile CSS est appliquée), mais `document.fonts` ne recense que des descripteurs Roboto / Roboto Slab (ceux du kit Elementor, tous `status: "unloaded"`) — aucun descripteur pour Marcellus, Jost ou Lora, et `document.querySelectorAll('link[href*="font"]')` est vide. Sur cet environnement, l'affichage du H1 reste néanmoins élégant (repli macOS), ce qui est précisément le piège décrit dans le brief : le défaut ne se voit pas ici et resterait à vérifier sur une machine Windows.

**Écart.** Aucun mécanisme de chargement (feuille Google Fonts ou `@font-face` auto-hébergé) n'existe pour les trois familles déclarées.

**Recommandation.** Ajouter une feuille Google Fonts pour Marcellus/Jost/Lora, ou héberger ces polices sur `sexcaperoom.ch` avec des règles `@font-face` dédiées ; vérifier ensuite que le chargement de Roboto disparaît (il ne sert à rien une fois les bonnes polices en place).

## D2 — Le kit Elementor ne porte aucun jeton global

**Verdict au 10/09 : Partiellement corrigé. Verdict au 11/09 : Corrigé.**

**Mise à jour du 11/09.** Les 4 créneaux de typographie système du kit (`primary`, `secondary`, `text`, `accent`) portent maintenant Marcellus/Marcellus/Lora/Jost — cohérents avec `--srlm-serif/--srlm-sans/--srlm-voix`. C'est la pièce qui manquait la veille (seules les couleurs étaient synchronisées). Point résiduel mineur, non bloquant : le CSS personnalisé continue de redéfinir ses propres variables plutôt que de consommer les jetons Elementor (`tokens.global_typography` et `global_colors` restent vides côté page), et aucune Variable globale Elementor n'a été créée (`list-variables` : 0) — donc si un jour les deux séries de valeurs divergent, rien ne le signalera automatiquement. À documenter si cela reste l'architecture définitive.

**Constat du 10/09 (pour mémoire) :**

**Preuve.** `get-global-settings` : les 4 couleurs système du kit sont désormais `primary "Étain" #B6ADA2`, `secondary "Noir" #0C0A09`, `text "Ivoire" #F2EDE4`, `accent "Or" #E3AA3E` — valeurs identiques au caractère près aux variables `--srlm-etain:#B6ADA2`, `--srlm-noir:#0C0A09`, `--srlm-ivoire:#F2EDE4`, `--srlm-or:#E3AA3E` du CSS personnalisé. `custom_colors: []` (pas de doublon). En revanche `system_typography` reste aux 4 valeurs par défaut d'Elementor (Roboto 600 / Roboto Slab 400 / Roboto 400 / Roboto 500), sans lien avec `--srlm-serif/--srlm-sans/--srlm-voix`. `list-variables` : 0 Variable globale Elementor créée. `get-page-snapshot` sur 130 : `tokens.global_colors: []` et `tokens.global_typography: []` — aucun élément de la page ne référence ces jetons par leur ID Elementor, tout continue de passer par les variables CSS.

**Écart.** Les couleurs sont désormais synchronisées (jetons + noms cohérents avec le CSS personnalisé) ; la typographie ne l'est toujours pas, et aucun élément de la page 130 n'utilise réellement les jetons couleur du kit — le risque d'origine (modifier au mauvais endroit) subsiste dès qu'on touche à la typographie ou qu'on repeint un élément depuis son panneau Style plutôt que depuis les jetons globaux.

**Recommandation.** Répéter l'opération pour la typographie (renommer les 4 créneaux, leur donner les familles `--srlm-serif/--srlm-sans/--srlm-voix`) ; documenter en tête du CSS personnalisé que les couleurs vivent maintenant aussi dans les Global Colors d'Elementor.

## D3 — Aucune règle responsive dans Elementor

**Verdict : Non corrigé.**

**Preuve.** `get-page-snapshot` sur 130 : `responsive.overrides: []`, toujours 178 éléments en `desktop_only`. `get-global-settings` : `viewport_md: 768`, `viewport_lg: 1025`. Le CSS personnalisé du kit contient ses propres points de rupture, relevés dans son texte : `max-width:600px`, `max-width:960px`, `min-width:600px and max-width:699px`, `min-width:700px`, `min-width:700px and max-width:959px`, `min-width:960px`, `min-width:1050px`, `min-width:1224px`, `min-width:1444px`. Aucun ne coïncide avec 768 ou 1025. Contrôle visuel effectué à 375, 768, 1000 et 1440 px : aucune rupture de mise en page visible à ces quatre largeurs précises.

**Écart.** Deux bandes de contradiction identifiées par calcul : 700–768 px (le CSS personnalisé bascule de comportement à 700, alors qu'Elementor considère encore la largeur comme "mobile" jusqu'à 768) et 960–1025 px (le CSS bascule à 960, Elementor reste en "tablette" jusqu'à 1025). Ces deux bandes n'ont pas été inspectées pixel par pixel — seule l'analyse structurelle les identifie.

**Recommandation.** Aligner les points de rupture du CSS personnalisé sur 768/1025 (ou l'inverse : ajuster `viewport_md`/`viewport_lg` sur 700/960) pour supprimer les bandes de contradiction.

## D4 — Imbrication profonde et conteneurs vides

**Verdict : Non corrigé** (sur le volet profondeur ; le volet étiquetage, lui, est déjà acquis).

**Preuve.** `get-page-snapshot` sur 130 : toujours `max_depth: 8`, avertissements `deep_nesting` et `empty_container` présents, 178 éléments au total — structure inchangée depuis le 9 septembre. `get-element-settings` vérifié individuellement sur 7 conteneurs vides (`fca2ba3`, `7c05105`, `8ea629f`, `03d0060`, `4641c56`, `8afceb6`, et la série des "Cran 1/2/3") : chacun porte un `_title` explicite ("Photo — déposer l'image ici", "Motif de clé", "Cran 1 — allumé", etc.). L'absence de champ `label` pour certains d'entre eux dans le digest `get-page-snapshot` est un artefact de cet outil de synthèse, pas une absence réelle d'étiquette côté Elementor — vérifié tool par tool.

**Écart.** Le critère demandait profondeur ≤ 6 **ou** justification écrite : ni l'un ni l'autre n'est rempli, la profondeur reste à 8 sans note documentée. Le second volet du critère (chaque conteneur vide étiqueté) est en revanche rempli pour tous les conteneurs testés.

**Recommandation.** Soit aplatir d'un niveau la structure `Cadre photo > Arche > (Photo | Mention)`, soit consigner par écrit (dans le CSS personnalisé ou une note de projet) que cette profondeur sert un besoin de mise en page réel et est assumée.

## D5 — Les emplacements photo sont toujours vides

**Verdict : Partiellement corrigé.**

**Preuve.** `get-page-snapshot` sur 130 : `image_count: 1`, `images_missing_alt: 1`, et le texte "Photo à venir" apparaît encore 5 fois dans les intitulés (héros, le lieu, et les 3 pièces). `get-element-settings` sur le widget `e-image` du héros (`4eefca3`) : il pointe vers l'attachment `148`, qui existe dans la médiathèque (`20260907_223011.jpg`, 1441×2560, alt vide, déposé le 08/09/2026). Vérifié en direct sur le site : cette photo se charge et s'affiche réellement (photo d'une pièce à l'éclairage rouge, chaise tantrique) — mais le bloc "Mention de photo" (`38d69a2`, qui porte le texte "Photo à venir" et la note de cadrage) occupe exactement le même rectangle que la photo, avec un z-index supérieur (2 contre 0) : il n'a pas été retiré ni masqué après l'ajout de l'image, et reste visible en permanence par-dessus elle sur toutes les largeurs testées.

Les 4 autres cadres (façade, 3 pièces) ne contiennent toujours aucune image — le total `image_count: 1` le confirme pour toute la page. Les 5 pages secondaires (`Bons cadeaux`, `Contact`, `Conditions`, `Mentions légales`, `Confidentialité`) ont été revérifiées une à une : chacune est une coquille d'un seul widget HTML "Page en préparation", 0 mot, 0 image, aucun H1 — un état antérieur à la préparation du contenu, distinct du défaut D5 mais qui en aggrave la portée.

**Écart.** Sur les emplacements photo attendus sur la page d'accueil, un seul (héros) a reçu une image, sans que la mention "Photo à venir" soit retirée à cette occasion ; les autres restent vides.

**Recommandation.** Retirer ou masquer le bloc "Mention de photo" du héros maintenant qu'une image y est posée (ou le transformer en légende discrète hors du cadre visuel de la photo) ; déposer les photos manquantes pour la façade et les 3 pièces. Les 5 pages secondaires relèvent d'un chantier de contenu à part entière, hors du périmètre strict de D5.

## D6 — L'îlot du moteur de réservation

**Verdict : signalé, hors grille de correction** (aucun critère de correction n'était défini à ce stade — seul un changement d'état inquiétant est à signaler).

**Preuve.** `get-element-settings` sur `46f8434` (page 130) : le widget HTML contient un `<form action="#srlm-reserver" method="get">` complet — champs de date d'arrivée/départ, sélection de la pièce, sélection du nombre de personnes, bouton "Vérifier les disponibilités" — suivi d'un texte indiquant que le moteur est en cours de branchement et renvoyant vers la page Contact.

**Écart.** Ce n'est ni un iframe intégré, ni un lien mort au sens strict, mais un formulaire qui a toutes les apparences du fonctionnel (champs actifs, bouton d'action) alors que sa soumission ne mène nulle part (l'action pointe vers une ancre locale, sans traitement). Un visiteur qui le remplit et clique sur "Vérifier les disponibilités" n'obtient aucune réponse ; seul le texte sous le formulaire l'avertit qu'il faut passer par Contact. C'est plus proche du risque que le brief voulait précisément éviter (un cadre qui a l'air de fonctionner) que de l'état "inerte" d'origine.

**Recommandation.** Soit désactiver visuellement les champs (les griser, retirer le bouton d'action, pour signaler clairement un aperçu) soit basculer dès maintenant sur la solution recommandée par le brief lui-même : un simple bouton menant vers `/contact/` ou `reservation.sexcaperoom.ch`.

## Hygiène du thème

**Thème enfant.** `astra-child` est actif, parent `astra` (`list-themes`). Le CSS personnalisé vit dans le kit Elementor (`custom_css`), pas dans un fichier du thème : `wp-content/themes/astra-child` ne contient que `functions.php` (1035 o) et un `style.css` de 62 octets, quasi vide. Une mise à jour d'Astra ne menace donc rien ici.

**Gabarits d'en-tête/pied de page.** `list-theme-templates` (gabarits "Themer" propres à EMCP Tools) renvoie une liste vide. La bibliothèque de templates Elementor Pro (`list-templates`), elle, contient un en-tête ("Sexcape Room Header - Global", id 21) et **deux** pieds de page : "Sexcape Room Footer - Global" (id 23, enregistré à 13:46) et "Sexcape Room Foloter - Global" (id 25, enregistré à 13:28, faute de frappe dans le titre — probable brouillon abandonné). `resolve-template` sur la page 130 renvoie header/body/footer tous `null` : cet outil ne résout que les gabarits "Themer" d'EMCP (vides ici), pas les conditions d'affichage propres à la Theme Builder d'Elementor Pro. Je n'ai pas pu déterminer, avec les outils disponibles, lequel des deux pieds de page est réellement assigné ni sous quelles conditions — à vérifier manuellement dans Elementor Pro → Générateur de site.

**Slug de la page d'accueil.** Toujours `sexcape-room-variante-controles-standards`, inchangé.

**Extensions actives.** `list-plugins` (statut actif) : 12 extensions, dont NitroPack — confirmé actif et opérant (visible dans le HTML rendu via `<style id="nitro-fonts">` et servi depuis le CDN `cdn-ilephlh.nitrocdn.com`), ce qui signifie que toute mesure sur le HTML rendu passe par une couche de cache/optimisation. Egalement actifs : Wordfence Security, Security Optimizer (SiteGround), TranslatePress (Business + Multilingual), MailPoet, WPForms, Duplicate Page, SiteGround Central, AI Agent by SiteGround. Une mise à jour est disponible pour EMCP Tools (Premium) : 3.15.0 → 3.15.1.

## Annexe — aperçus renvoyés à un autre chantier

Les éléments suivants ont été croisés en chemin et ne sont ni notés ni jugés ici (référencement, contenu rédactionnel, accessibilité) :

- `seo_lite` vide sur les 6 pages : aucun `meta_title`, `meta_description`, `canonical` ni `og_image` déclaré.
- L'unique image du site (attachment 148) n'a pas de texte alternatif.
- `resolve-template` signale `is_front_page: false` pour l'URL `/fr/` — à vérifier côté configuration multilingue/TranslatePress, hors périmètre de cette revue.
- Les 5 pages secondaires (Bons cadeaux, Contact, Conditions, Mentions légales, Confidentialité) sont de simples coquilles "Page en préparation" sans contenu rédactionnel — chantier de contenu à part entière.
