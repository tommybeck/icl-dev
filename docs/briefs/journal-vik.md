# Journal des modifications Vik Booking

Toute modification faite dans l'administration de Vik Booking s'inscrit ici, avec sa valeur avant et après. C'est ce qui rend un retour arrière possible : ces réglages vivent en base de données, pas dans le dépôt, donc Git ne les protège pas.

Une ligne par modification. La plus récente en haut.

| Date | Où (écran, chambre, tarif) | Avant | Après | Pourquoi | Par qui |
|---|---|---|---|---|---|
| 2026-09-12 | Saisons, `Long-stay Discount` (id 122) | Remise active, ne portant que sur les chambres de test 5 et 6 | Supprimée | Ne s'appliquait à aucune chambre vendue ; tranchée comme oubli plutôt que choix délibéré (`revue-tarifs.md` §2) | Thomas |
| 2026-09-12 | Saisons, `Summer vacation 2026` (id 27) | Saison inerte (`idrooms` vide), aucune majoration appliquée de tout l'été 2026 | Supprimée | Confirmée sans effet et sans remplacement prévu ; `revue-tarifs.md` §1 recommandait de vérifier puis corriger, Thomas a choisi la suppression plutôt que la réparation | Thomas |
| 2026-09-12 | Restrictions, « L'Entracte families & friends - April only » | Restriction fantôme, `allrooms = 0` et `idrooms` nul, sans effet sur aucune chambre | Supprimée | Confirmée inerte par construction (`revue-tarifs.md` §4) | Thomas |
| 2026-09-13 | Vik Shortcodes | Pas de shortcode pour A Huis Clos. Aurait pointé sur L'Entracte | Ajouté | Confirmé par Claude CoWork via EMCP Tools | Thomas |
| 2026-09-19 | NitroPack (réglage hors Vik, consigné ici au même titre) | Page 845 et toute URL portant un paramètre `sid` optimisées par NitroPack | Exclues de l'optimisation | Parade H de `constat-incident-1818.md` : l'agent `Nitro-Optimizer-Agent` rendait la page de paiement d'un client, ce qui faisait créer une session Stripe de plus et détruisait la trace de la session payée | Thomas |
| 2026-09-15 | Greffon VikStripe, remplacement manuel depuis `plugin-install.php` | VikStripe 2.2.4, installée le 23 octobre 2025 | **Inchangée** : le code de l'archive n'est pas en place | Téléversement à 09:44:05 UTC, confirmation `overwrite=update-plugin` à 09:44:15. L'archive porte la même version 2.2.4 (`constat-vikstripe-nouvelle-version.md`). Soit le remplacement a échoué, soit il a été défait le jour même par une restauration : non tranché | Thomas |
| 2026-09-13 | Chambres 8, 9 et 10, disponibilité | Non réservables | Réservables, et fermées jusqu'au 24 septembre exclu | Ouverture à la vente des trois expériences Sexcape Room, décision consignée dans `marques-et-experiences.md` 1.2 | Thomas |
| 2026-09-13 | Vik Shortcodes, lignes 20, 21 et 22 | Aucun shortcode pour les chambres 7, 8 et 9 | Ajoutés | Même motif que la ligne du 13 septembre sur À Huis Clos : sans enregistrement, le shortcode pointait sur la mauvaise chambre | Thomas |
| 2026-09-14 | Vik Shortcodes, lignes 7 et 23 | Deux lignes orphelines | Supprimées | Sans chambre de rattachement, donc sans effet | Thomas |
| 2026-09-14 | Vik Shortcodes, ligne 4 | Nom précédent | Renommée `Room details - EN` | Mise au clair du nommage | Thomas |
| 2026-09-14 | Saisons, `Fall vacation 2026 (rooms)` (ligne 148) | Doublet « rooms » inexistant, les chambres 8 et 9 sans majoration d'automne | Créée : chambres 8 et 9, 12 au 23 octobre 2026, 30 CHF par nuit | Doublet manquant relevé par `revue-tarifs.md` ; vérifiée en base le 14 septembre | Thomas |

---

## Rappels

- Mise à jour automatique de Vik Booking **désactivée**. Toute mise à jour est manuelle, suivie d'une passe de recette complète.
- Instantané de base de données avant toute série de modifications de configuration.
- Les chambres 5 et 6 sont des chambres de test : elles ne se vendent pas et n'entrent dans aucune analyse.
- **Ce fichier appartient à Thomas, et lui seul y écrit.** Toute modification tarifaire passe par les écrans natifs de Vik, jamais par une écriture SQL ni par un plugin maison : le compte MySQL est en lecture seule et le reste. Voir `convention-tarifs-annuelle.md`.
