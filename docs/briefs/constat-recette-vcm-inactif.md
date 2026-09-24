# Constat — recette du 24 septembre après la désactivation de Vik Channel Manager

24 septembre 2026. Exécution de `recetter-moteur.sh --appliquer` contre
`staging13.linstantcle.ch`, à l'intention de Cowork pour analyse et pour le
plan de marche. C'est la première recette depuis que Vik Channel Manager a été
désactivé sur la préproduction, à la suite de
`incident-preproduction-vers-plateformes-2026-09-24.md`.

**Tout ce qui suit vient d'une exécution réelle et de lectures en lecture seule**
(`debug.log` de la préproduction, `wp db query` en `SELECT` à colonnes
explicites). Rien n'a été corrigé, rien n'a été déployé, rien n'a été écrit en
base. Les `sid` sont omis, pour la même raison que dans
`constat-reprise-1830-1831.md`. L'adresse du client de la réservation 1833 est
omise aussi : c'est une vraie adresse, qui n'a rien à faire dans le dépôt.

---

## 1. Commande et contrôles de départ

```bash
./recetter-moteur.sh --hote staging13.linstantcle.ch --appliquer
```

Lancée à 15 h 54 UTC. Tous les contrôles de départ sont verts :

- l'hôte n'est pas un hôte de production connu de `lme-mail-guard` ;
- `wp_get_environment_type()` vaut `staging` ;
- Vik Channel Manager est `inactive` ;
- VikStripe est publié en clés de test (`sk_test_`), une passerelle.

Le levier valait `reservation.sexcaperoom.ch` au départ et a été restauré à
cette valeur à la fin.

## 2. Résultat

| # | Sexcape Room | L'Instant Clé |
|---|---|---|
| 1a, 1b | OK | OK |
| 2 | OK, aucune chambre étrangère proposée | OK |
| 3a | OK, chambre étrangère refusée en `403` (arrivée le 24 novembre) | OK |
| 3b | non concluant, la chambre désactivée n'apparaît pas dans les résultats natifs | non concluant |
| 4 | OK, jetons `--srlm-` présents | OK, aucune fuite |
| 5-6 | **KO** : `code HTTP inattendu à la soumission finale : 500` | **KO**, même erreur |
| 8 | KO, attendu tant que G2 n'existe pas | |

Le script rapporte pour les deux passes « la réservation d'essai n'a pas pu être
créée ». **C'est faux**, voir le chapitre 4.

Le code de sortie n'a pas été relevé : la commande passait par `tee` sous zsh,
où `PIPESTATUS` n'existe pas. Le défaut « sortie en `0` malgré des
vérifications au rouge » de B9i reste donc ni confirmé ni infirmé par cette
exécution.

## 3. La cause des 500

`wp-content/debug.log` de la préproduction contient la même erreur fatale, à
chaque soumission finale :

```
PHP Fatal error:  Uncaught Error: Undefined constant "VIKCHANNELMANAGER_LIBRARIES"
  in wp-content/plugins/vikchannelmanager/admin/helpers/src/request/availability.php:445
#0 availability.php(360): VCMRequestAvailability->checkLanguageStatus()
#1 availability.php(102): VCMRequestAvailability->storeTemporaryBusyRecords(Array)
#2 vikchannelmanager/site/helpers/synch.vikbooking.php(1070): VCMRequestAvailability->fetchTemporaryBusyRecords(...)
#3 synch.vikbooking.php(265): SynchVikBooking->getOrderDetails()
#4 vikbooking/site/helpers/vcm.php(165): SynchVikBooking->sendRequest()
#5 vikbooking/site/controller.php(1634): VboVcmInvoker->doSync()
#6 libraries/adapter/mvc/controller.php(337): VikBookingController->saveorder()
...
#12 wp-settings.php(779): do_action('init')
```

Trois occurrences, à 15:50:38, 15:55:04 et 15:55:57 UTC.

**Ce que la trace établit.** Dans `saveorder()`, Vik Booking appelle
`VboVcmInvoker->doSync()`, qui charge directement les fichiers de Vik Channel
Manager alors que WordPress ne charge plus le greffon. La constante
`VIKCHANNELMANAGER_LIBRARIES` est définie par l'amorce du greffon. Désactivé,
le greffon ne la définit plus, et le code chargé à la main plante.

**Ce qu'elle suggère, sans que ce soit établi.** Vik Booking détecterait la
présence de Vik Channel Manager par ses fichiers, pas par son statut
d'activation. Cette conclusion vient de la trace seule : `vcm.php` n'a pas été
lu.

**Conséquence.** La parade posée ce matin contre l'incident, désactiver le
greffon, empêche toute réservation de s'achever sur la préproduction. Les
réservations 1830 et 1831 de ce matin passaient parce que le greffon était
encore actif. Le chapitre 4 du plan de marche prescrit cette désactivation
à chaque recréation : en l'état, il rend aussi les vérifications 5, 6 et 7
impossibles.

**En production** le greffon est actif, et ce chemin n'est pas en cause.

## 4. Les réservations ont bien été créées

L'erreur survient **après** l'insertion. Lecture de `sir_vikbooking_orders` et
`sir_vikbooking_ordersrooms` à partir de 1826 :

| id | statut | créée (UTC) | arrivée | chambre | client |
|---|---|---|---|---|---|
| 1832 | standby | 24.09 11:11:14 | 14.12 | 4 | adresse de recette |
| **1833** | standby | 24.09 15:50:38 | **24.09** | 4 | **adresse Gmail réelle** |
| **1834** | standby | 24.09 15:55:04 | 23.12 | 4 | adresse de recette |
| **1835** | standby | 24.09 15:55:57 | 23.12 | 2 | adresse de recette |

- **1834 et 1835 viennent de cette recette.** Le script les croit non créées
  et ne les inscrit pas au registre local, donc `--nettoyer` ne les verra
  jamais. C'est un échec silencieux au sens de la règle absolue n°6 : le
  rapport affirme une absence qui n'est pas vraie.
- **1833 ne vient pas du script.** Elle a été créée cinq minutes avant, avec
  une vraie adresse de client et une arrivée le jour même, et elle a planté au
  même endroit. **Son origine n'est pas établie.** Un essai manuel dans le
  navigateur est l'hypothèse la plus probable, à confirmer par Thomas. Avec
  WP Mail SMTP en « Do not send » et le garde-fou de messagerie en place,
  aucun e-mail ne devrait lui être parvenu. Ce n'est pas vérifié dans le
  journal d'envoi.

Toutes restent en attente de nettoyage dans l'administration de Vik,
avec 1826 à 1829 et 1832.

## 5. Rien n'est parti vers les plateformes

La dernière ligne de `sir_vikchannelmanager_notifications` sur la préproduction
est la **4028, à 11:11:20 UTC**, qui correspond à la réservation 1832, créée
avant la désactivation. Aucune ligne n'a été ajoutée depuis. L'erreur fatale
arrive dans `getOrderDetails()`, pendant la préparation de la requête et avant
son envoi.

## 6. Ce qui reste à décider

1. **Rétablir la création de réservations sur la préproduction sans rouvrir
   la voie vers les plateformes.** Trois pistes, par ordre de préférence :
   - **Retirer le dossier `wp-content/plugins/vikchannelmanager` de
     `staging13`** (Site Tools, geste de Thomas). Si Vik Booking détecte le
     greffon par ses fichiers, c'est la seule parade qui écarte en même temps
     l'appel sortant et l'erreur fatale. Les dix verrous en attente
     disparaissent avec lui. Il faudrait alors ajouter ce retrait à l'étape
     obligatoire du chapitre 4, à la place de la simple désactivation, ou
     en plus d'elle.
   - Un mu-plugin qui neutralise `VboVcmInvoker` hors production, **à
     condition** qu'un crochet ou un réglage de Vik Booking le permette sans
     toucher ses fichiers. Ce n'est pas établi : il faudrait d'abord lire
     `vikbooking/site/helpers/vcm.php` et les conditions de `controller.php`
     autour de la ligne 1634.
   - Une question à VikWP sur la détection du Channel Manager.
2. **Corriger le script, dans le cadre de B9i.** Après une réponse 500 à la
   soumission finale, il doit chercher la réservation par son `sid` ou par
   l'adresse de recette qu'il a générée, l'inscrire au registre si elle
   existe, et le dire dans le rapport. « Non créée » ne doit s'afficher qu'une
   fois l'absence vérifiée en base.
3. **Nettoyer 1833, 1834 et 1835** dans l'administration de Vik. Thomas
   confirme d'abord l'origine de 1833.
