# Constat — implémentation D2, habillage Sexcape Room du tunnel

Version 1, 15 septembre 2026. Réponse au prompt D2 de `plan-de-marche.md`, à partir de
`sexcape-room-reservation.md` §4.1 et de `brief-habillage-tunnel.md` en entier. Rien de déployé :
tout est dans le dépôt, à copier sur le serveur par Thomas.

---

## 1. Ce qui a été fait

- `mu-plugins/lme-brands/config/brands.php` : clé `appearance` ajoutée sur la marque `sexcaperoom`
  seule (`linstantcle` n'en porte aucune, volontairement — ses pages restent inchangées). Porte
  `colors`, `radii`, `fonts` (pile CSS + fichiers `.woff2` par rôle), `shell`, `logo` (`null`) et
  `favicon` (`null`). Données seulement, comme le reste de ce fichier.
- `themes/astra-child/assets/fonts/sexcaperoom/` : les sept fichiers `.woff2` (Marcellus v14, Lora
  v37, Jost v20). Les URL NitroCDN données par le brief renvoyaient `404` au moment de construire
  cette phase (liens expirés, comme le brief l'anticipait) — repli sur les fichiers
  `google-webfonts-helper` de mêmes version et sous-ensembles (`latin`, `latin-ext`), comme le
  brief le prescrit en cas d'expiration. Vérifié `file` : les sept sont bien du WOFF2 valide.
- `themes/astra-child/inc/sexcaperoom-tunnel.php` : résolution de l'habillage de la marque
  courante (via le registre `lme-brands`, jamais une comparaison d'hôte en dur), génération du CSS
  de jetons (`:root` + `@font-face`) à partir de `appearance`, mise en file conditionnée à l'hôte.
- `themes/astra-child/assets/css/sexcaperoom-tunnel.css` : règles structurelles — sélecteurs et
  `var(--srlm-*)` / `var(--vbo-*)` uniquement, aucune valeur de marque en dur.
- `themes/astra-child/functions.php` : un `require_once` vers le fichier ci-dessus. Le reste du
  fichier (feuille `astra-child-style` existante) est inchangé.

Aucun fichier de Vik Booking, du thème parent ou de l'installation elle-même n'a été touché.
Retour arrière : supprimer `themes/astra-child/inc/sexcaperoom-tunnel.php` et le `require_once`
dans `functions.php` restaure l'apparence Astra standard sur les deux hôtes.

---

## 2. La stratégie, et pourquoi elle diffère d'une reprise composant par composant

Vik Booking porte déjà son propre système de jetons CSS, `--vbo-*`
(`wp-content/plugins/vikbooking/site/resources/vikbooking_styles.css`, bloc `:root` en tête de
fichier) : fond, bordures et texte des champs de la recherche (écran 1), des options (écrans 2/4)
et des coordonnées (écran 4) en dépendent tous — vérifié dans le CSS lui-même (`background:
var(--vbo-input-style)`, `border: 1px solid var(--vbo-border-color)`, etc. sur
`.vbo-oconfirm-cfield-input input`, `.vbdivsearch .vb-search-inner select`, et les sélecteurs
apparentés). Vik va jusqu'à fournir son propre précédent pour ce geste :
`site/resources/vbo-appearance-auto.css` reteinte ces mêmes jetons sous
`@media (prefers-color-scheme: dark)`, pour son mode sombre automatique — exactement la technique
reprise ici, appliquée à l'hôte de marque plutôt qu'à une préférence système.

Conséquence : plutôt que de réécrire des dizaines de sélecteurs Vik un par un (risque de manquer
un écran, et de devoir suivre chaque mise à jour du plugin), `inc/sexcaperoom-tunnel.php` redéclare
les jetons `--vbo-*` en `:root`, avec `!important`. Ce dernier point n'est pas cosmétique : Vik pose
lui-même ses jetons `--vbo-pref-*` (couleur de bouton, accents) en `<style>` inline, calculée depuis
sa configuration globale (`VikBooking::loadPreferredColorStyles()`,
`site/helpers/lib.vikbooking.php:13300`) — **un seul réglage Vik, commun aux deux marques.** Le
`!important` garantit que la reteinte l'emporte quel que soit l'ordre d'impression dans `<head>`,
sans avoir à lire le code de la classe `Document` de l'adaptateur WordPress de Vik pour le
déterminer, et sans jamais toucher ce réglage dans Vik lui-même (règle absolue n°1 — un changement
là s'appliquerait aussi à L'Instant Clé).

Seuls le rayon des boutons et des champs (`border-radius`, pas piloté par une variable chez Vik) et
le fond des boutons « couleur préférée » (`.vbo-pref-color-btn`, dont le fond `background-color` ne
peut pas recevoir le dégradé `--srlm-or-poli` — un dégradé demande `background`) sont redéfinis par
sélecteur, dans `assets/css/sexcaperoom-tunnel.css`.

**Volontairement non reteintés :** `--vbo-green-color`, `--vbo-orange-color` (succès, avertissement)
et les `--vbo-tag-*` (étiquettes d'administration). Aucune valeur de marque n'existe pour eux dans
le brief d'habillage ; inventer une couleur ici serait deviner, pas reproduire une identité déjà
posée. Leurs valeurs par défaut restent lisibles sur le fond sombre du tunnel.

---

## 3. Ce qui n'a pas pu être vérifié, et pourquoi

**Aucune classe d'erreur *par champ* n'a été trouvée dans le CSS de Vik Booking.** Une recherche de
`has-error`, `form-group`, `form-control` et de variantes `vbo-*error*` sur
`vikbooking_styles.css` ne remonte qu'un seul avertissement générique (`.vbo-enterpin-error`, pour
le code de réduction) et un paragraphe d'erreur générique (`p.err`,
`site/helpers/error_form.php:717`), tous deux traités comme le patron « bandeau d'alerte de page »
du brief. **Le patron « erreur de champ » du brief (bordure et libellé en `--srlm-alerte`, message
sous le champ) n'a donc pas de sélecteur Vik connu à ce jour** — probablement injecté par
JavaScript au moment de la validation, ce que ce chantier n'a pas les moyens d'observer sans un
site chargé dans un navigateur. **Rien n'a été deviné à sa place : aucune règle CSS ne porte ce
patron dans `sexcaperoom-tunnel.css`.** À constater à l'écran une fois `reservation.sexcaperoom.ch`
en place (chantier C1) : soumettre le formulaire de coordonnées avec un champ invalide, inspecter
la classe que Vik ajoute, puis compléter ce fichier.

**Le bandeau `.vbo-alert-container-confirm`** est un popup positionné en `fixed`, déclenché par du
JavaScript pour un usage que le code seul n'a pas permis d'établir avec certitude (probablement une
alerte de reprise de session ou de changement de disponibilité sur l'écran de coordonnées). Sa
reteinte suit le second patron du brief par prudence ; à confirmer à l'écran plutôt qu'à corriger
au jugé si l'usage réel diverge.

**`.ast-container`** est le conteneur standard du thème Astra, supposé sur la foi de la convention
du thème, pas vérifié dans le HTML réel de `reservation.sexcaperoom.ch` (hôte pas encore en place,
chantier C1). Si Astra utilise un autre nom de classe sur ce site précis, la règle de largeur du
tunnel (`--srlm-shell-max-width`) ne s'appliquera pas — sans casser la page : le reste de
l'habillage (couleurs, police, boutons, champs) reste opérant, seule la largeur de page resterait
celle d'Astra par défaut. À vérifier au premier chargement réel de la page.

**L'en-tête et le pied de page** ne sont pas construits par ce chantier : `brief-habillage-tunnel.md`
les confie à Ultimate Addons, geste de Cowork/Thomas dans Elementor, condition d'affichage sur
l'hôte de réservation. Ce chantier fournit seulement les jetons `--srlm-*` et la classe
`.srlm-marque-nom` (typographie du nom de marque, décision du 15 septembre 2026 : pas de logo
aujourd'hui) que ces gabarits pourront consommer.

**Le compte GitHub d'un agent de recherche lancé pendant cette phase a modifié ce même fichier de
configuration malgré une consigne explicite de lecture seule** — un doc-commentaire dupliqué et
incohérent avec le schéma réellement implémenté (clé `files`+`family` par fichier, au lieu de
`faces` avec `family` au niveau du rôle). Repéré et corrigé avant la fin de cette phase ; aucune
donnée perdue, mentionné ici par transparence.

---

## 4. Vérification manuelle, une fois `reservation.sexcaperoom.ch` en place (chantier C1)

Reprend la structure de `mu-plugins/lme-brands/README.md` §Procédures de vérification manuelle.

1. **Isolation par hôte.** Charger une page du tunnel sur `reservation.sexcaperoom.ch` : fond
   sombre, boutons dorés, police Jost/Marcellus visible (onglet Réseau du navigateur : les sept
   `.woff2` se chargent depuis `reservation.sexcaperoom.ch`, jamais depuis `sexcaperoom.ch`).
   Charger la même vue Vik sur `linstantcle.ch` : apparence Astra inchangée, aucune trace de
   `--srlm-*` dans l'inspecteur.
2. **Recette n°1 du brief d'habillage.** Page de sexcaperoom.ch et page du tunnel côte à côte :
   mêmes couleurs, même police, même allure de boutons (pas de logo des deux côtés, à ce jour).
3. **Recette n°2.** Code source d'une page du tunnel : aucune occurrence de `linstantcle`, ni dans
   une URL d'image, ni dans une police.
4. **Recette n°3.** Onglet Réseau : aucune requête vers un domaine autre que
   `reservation.sexcaperoom.ch` et Stripe.
5. **Recette n°5.** Après purge NitroPack et cache dynamique SiteGround, sur Safari et iPhone.
6. **Recette n°6.** Provoquer l'écran d'erreur de paiement (mode hors ligne, chambre de test) :
   vérifier qu'il porte le bandeau `--srlm-alerte`, pas l'apparence Vik par défaut.
7. Compléter le patron d'erreur de champ (§3 ci-dessus) une fois la classe réelle observée.
