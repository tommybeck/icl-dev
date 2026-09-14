# Journal des modifications Vik Booking

Toute modification faite dans l'administration de Vik Booking s'inscrit ici, avec sa valeur avant et après. C'est ce qui rend un retour arrière possible : ces réglages vivent en base de données, pas dans le dépôt, donc Git ne les protège pas.

Une ligne par modification. La plus récente en haut.

| Date | Où (écran, chambre, tarif) | Avant | Après | Pourquoi | Par qui |
|---|---|---|---|---|---|
| 2026-09-12 | Saisons, `Long-stay Discount` (id 122) | Remise active, ne portant que sur les chambres de test 5 et 6 | Supprimée | Ne s'appliquait à aucune chambre vendue ; tranchée comme oubli plutôt que choix délibéré (`revue-tarifs.md` §2) | Thomas |
| 2026-09-12 | Saisons, `Summer vacation 2026` (id 27) | Saison inerte (`idrooms` vide), aucune majoration appliquée de tout l'été 2026 | Supprimée | Confirmée sans effet et sans remplacement prévu ; `revue-tarifs.md` §1 recommandait de vérifier puis corriger, Thomas a choisi la suppression plutôt que la réparation | Thomas |
| 2026-09-12 | Restrictions, « L'Entracte families & friends - April only » | Restriction fantôme, `allrooms = 0` et `idrooms` nul, sans effet sur aucune chambre | Supprimée | Confirmée inerte par construction (`revue-tarifs.md` §4) | Thomas |
| 2026-09-13 | Vik Shortcodes | Pas de shortcode pour A Huis Clos. Aurait pointé sur L'Entracte | Ajouté | Confirmé par Claude CoWork via EMCP Tools | Thomas |

---

## Rappels

- Mise à jour automatique de Vik Booking **désactivée**. Toute mise à jour est manuelle, suivie d'une passe de recette complète.
- Instantané de base de données avant toute série de modifications de configuration.
- Les chambres 5 et 6 sont des chambres de test : elles ne se vendent pas et n'entrent dans aucune analyse.
- **Ce fichier appartient à Thomas, et lui seul y écrit.** Toute modification tarifaire passe par les écrans natifs de Vik, jamais par une écriture SQL ni par un plugin maison : le compte MySQL est en lecture seule et le reste. Voir `convention-tarifs-annuelle.md`.
