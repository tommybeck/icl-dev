# Constat, phase 0

Réponses aux six questions du chapitre 7 du brief `sexcape-room-reservation.md`.
Règle : chaque réponse cite la preuve qui l'établit. Une question sans preuve se déclare non tranchée, jamais déduite.

---

## Q1. Hébergement, les deux domaines peuvent-ils partager une installation ?

**Tranchée, 9 septembre 2026, par Thomas dans SiteGround Site Tools.**

linstantcle.ch et sexcaperoom.ch sont sur le **même compte SiteGround**, mais déclarés comme **deux sites distincts** : racines séparées dans le système de fichiers, installations WordPress séparées.

Conséquence pour l'architecture : le palier retenu tient. `reservation.sexcaperoom.ch` s'ajoute en **domaine garé** sur le site linstantcle.ch, et est donc servi par l'installation qui porte Vik. Même compte veut dire aucune démarche inter-comptes, ni pour le domaine ni pour le certificat.

**Reste à vérifier :** que le site sexcaperoom.ch ne capte pas déjà ses sous-domaines par une règle générique, ce qui entrerait en conflit avec le domaine garé.

---

## Q2. Vik envoie-t-il ses e-mails par `wp_mail`, et quels points d'accroche expose-t-il ?

**Non tranchée.** Demande la lecture du code de Vik dans `.local/vik-source/`.

Ce qu'il faut établir, avec fichier et ligne :

- le chemin d'envoi réel : `wp_mail`, `PHPMailer` direct, ou `mail()` ;
- les actions ou filtres exposés autour de la création, de la confirmation et de l'annulation d'une réservation ;
- si un identifiant de réservation est disponible dans le contexte au moment de l'envoi, ce qui conditionne la résolution de marque.

---

## Q3. Les liaisons de disponibilité reflètent-elles les groupes attendus ?

**Tranchée, 9 septembre 2026.** Requête exécutée dans phpMyAdmin :

```sql
SELECT * FROM dbvkhvlostfyua.sir_vikbooking_calendars_xref;
```

| id | mainroom | childroom |
|---|---|---|
| 8 | 3 | 2 |
| 31 | 4 | 2 |
| 32 | 2 | 4 |
| 37 | 1 | 10 |
| 38 | 1 | 7 |
| 43 | 7 | 10 |
| 44 | 7 | 1 |
| 47 | 10 | 1 |
| 48 | 10 | 7 |

**Villa Entracte, complète et symétrique.** Les six paires orientées entre 1, 7 et 10 sont présentes : 1→7, 7→1, 1→10, 10→1, 7→10, 10→7.

**Villa Aparté, complète et symétrique.** 2→4 et 4→2.

**Chambres 8 et 9, aucune liaison**, conformément à leur indépendance. Chambres de test 5 et 6, aucune liaison non plus.

**Conclusion : le risque de double vente d'un même espace n'existe pas aujourd'hui.**

**Anomalie relevée, ligne id 8 : `mainroom = 3`.** La chambre 3 n'existe pas parmi les neuf chambres de `sir_vikbooking_rooms` (1, 2, 4, 5, 6, 7, 8, 9, 10). Ligne orpheline, asymétrique de surcroît puisque 2→3 est absente. Vestige d'une chambre supprimée. À traiter hors de ce chantier : d'abord confirmer qu'aucune réservation ne référence la chambre 3, puis supprimer la ligne et consigner le geste dans `journal-vik.md`.

---

## Q4. Vik sait-il filtrer nativement les chambres offertes ?

**Non tranchée.** Chercher dans `.local/vik-source/` et dans l'administration : catégories de chambres, paramètres de shortcode acceptant une liste d'identifiants, filtres exposés sur la construction des résultats de recherche.

Rappel du brief : préférer la configuration au code partout où elle existe.

---

## Q5. L'URL de retour de paiement vient-elle de la requête ou de l'option `siteurl` ?

**Non tranchée.** Établir par lecture du code de la passerelle Stripe de Vik. La réponse décide si filtrer `option_home` et `option_siteurl` suffit, ou s'il faut intervenir au moment de la création de la commande.

---

## Q6. Les tarifs configurés correspondent-ils à la grille attendue ?

**Non tranchée, et la question est mal posée en l'état :** aucune grille tarifaire de référence n'existe par écrit. Comparer à une référence absente ne prouve rien.

Procéder à l'envers : extraire de Vik la grille effectivement configurée, chambre par chambre, saison par saison, avec les règles de weekend et les séjours minimums, et la poser en tableau dans ce constat. **Thomas la valide ou la corrige.** La grille validée devient la valeur attendue de l'oracle tarifaire de la phase 5.

C'est la seule manière d'obtenir une référence qui vaille : celle qui a manqué pendant les deux mois du bug du tarif weekend.

---

## Verdict de phase

À remplir une fois les six questions tranchées. Deux sorties possibles :

- **Palier retenu confirmé** : domaine garé sur l'installation linstantcle.ch, phase 1 ouverte.
- **Palier retenu impossible** : exposer précisément ce qui l'empêche, avant d'envisager le repli du chapitre 4.1.
