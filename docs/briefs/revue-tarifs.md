# Revue tarifs — corrections à faire dans Vik Booking

Version 2, 12 septembre 2026. Lu depuis `constat-phase-0.md` (Q6, close le 11 septembre 2026). Chaque correction devient une ligne dans `journal-vik.md`, au moment où elle est exécutée, pas avant.

---

## Déjà fait, avant cette revue

**11 septembre 2026**, par Thomas :

- Supplément weekend, chambres 8 et 9 : doublet « rooms » créé (+80 CHF/nuit), 195.00 CHF → 275.00 CHF le vendredi et le samedi.
- Séjour maximum uniformisé à 15 nuits sur les sept chambres vendues.

Ces deux gestes précèdent la création de ce fichier et n'ont pas de ligne `journal-vik.md` dédiée à leur date réelle ; à ajouter rétroactivement si Thomas le souhaite, sinon les laisser tels quels.

---

## Tranchées le 12 septembre 2026, consignées dans `journal-vik.md`

- **`Summer vacation 2026`** — supprimée. Thomas a choisi de ne pas reconstruire cette majoration plutôt que de la réparer.
- **`Long-stay Discount`** — supprimée. Confirmé oubli de paramétrage, pas un choix délibéré.
- **Restriction fantôme « April only »** — supprimée.

---

## En cours, 12 septembre 2026 — saisie par Thomas dans Vik, prompts A6 et A8 de `plan-de-marche.md`

Le chemin a changé en cours de route : ces corrections devaient d'abord être écrites en SQL par Code, puis par un plugin maison. Les deux voies sont abandonnées, le compte MySQL reste en lecture seule, et la saisie se fait dans les écrans de Vik. Code prépare la fiche de saisie (A6), Thomas saisit (A7), Code vérifie (A8). Voir `convention-tarifs-annuelle.md`.

### Doublet « rooms » pour `Fall vacation`

`Fall vacation 2026` et `Fall vacation 2027` (villas) n'avaient pas de jumeau « rooms » pour les chambres 8 (La Parenthèse) et 9 (L'Indécent). Demande de Thomas : créer les deux jumeaux, supplément de 30 CHF/nuit, mêmes bornes de dates que les saisons villas correspondantes.

`Carnival vacation 2027` reste sans jumeau « rooms » et sans demande explicite : à trancher séparément, voir plus bas.

### Anomalie G, trouvée en préparant cette demande — restriction id 2 sur une portée de 21 mois

La restriction id 2, « L'Entracte families & friends - no check-in Fri/Sat », couvre 2026-04-09 à 2027-12-31 en une seule ligne. C'est exactement le défaut que Thomas signale de façon générale : Vik calcule mal une portée supérieure à un an environ. Cette ligne n'était pas dans les anomalies de `constat-phase-0.md`, parce qu'elle n'avait pas été relue sous cet angle-là — la relecture pour ce prompt l'a fait apparaître.

Demande de Thomas : rescoper cette ligne à 2026 seul (renommée `L'Entracte All-inclusive - no check-in Fri/Sat 2026`), créer une ligne jumelle `L'Entracte All-inclusive - no check-in Fri/Sat 2027` scopée à 2027 seul. Même modèle que les saisons déjà dupliquées par année.

---

## Encore ouvert, sans demande d'exécution pour l'instant

### Doublet « rooms » manquant sur `Carnival vacation 2027`

Toujours pas vérifié si cette saison couvre les chambres 8 et 9. Même geste que pour `Fall vacation` si le manque est confirmé.

### Asymétrie de la tarification par occupation

Seules les chambres 1 (L'Entracte) et 10 (À Huis Clos) portent un surcoût par adulte supplémentaire (+30/+60/+90 CHF). Les chambres 2, 4, 7, 8 et 9 n'en ont aucun. À vérifier : ces cinq chambres ont-elles une capacité qui ne dépasse pas deux adultes — auquel cas rien à faire — ou un surcoût existe-t-il ailleurs et a simplement été oublié dans Vik.

---

## Ce qui ne nécessite aucune action

- **Nuits minimum = 1 partout, sans exception.** Confirmé sur les trois sources qui portent cette règle.
- **Nuits maximum = 15 partout.** Corrigé le 11 septembre, cohérent sur les sept chambres.
- **Restriction « No checkout on Christmas 2025 ».** Échue, `allrooms = 1`, sans effet aujourd'hui. Peut être supprimée par hygiène, sans urgence.

---

## Une fois ces points tranchés

Le tableau de `constat-phase-0.md` §Q6 devient la référence figée de l'oracle tarifaire, phase 5. Chaque correction ci-dessus doit y être répercutée avant de le geler — c'est Code qui le met à jour, puisque `constat-phase-0.md` est un fichier de constat, pas de revue.

Voir aussi `convention-tarifs-annuelle.md` pour la règle générale sur la portée d'un an et le rythme de création annuelle qui en découle.
