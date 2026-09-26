# Constat : une réservation d'essai par le parcours client, `recetter-moteur.sh --reserver`

26 septembre 2026. Cette commande met en œuvre la décision 2 du 26 septembre
(`plan-de-marche.md` 2.21, « Décisions du 26 septembre ») : les réservations
d'essai se créent par `recetter-moteur.sh`, jusqu'au lien Stripe Checkout, et
Thomas paie lui-même. Ce constat s'adresse à Thomas et à Cowork.

**Méthode.** Lectures du code de Vik dans `.local/vikbooking` (1.8.14), puis
revérifiées sur `staging13` (1.8.15) par SSH et WP-CLI. Les réglages ont été
lus par leur clé, jamais par joker. Le registre de lme-brands a été lu en
hexadécimal. Je n'ai rien écrit en base à la main et rien déployé. Seul le
parcours client écrit : il crée une réservation `standby` et son verrou
temporaire, comme pour n'importe quel visiteur.

---

## 1. L'usage

```bash
./recetter-moteur.sh --hote staging13.linstantcle.ch --reserver 2 --arrivee 2026-09-28 --nuits 1
```

La commande rend le lien Stripe Checkout sur sa dernière ligne utile, et rien
d'autre : elle ne mène aucune vérification. Codes de sortie : 0 quand le lien
est rendu, 2 quand il ne l'est pas (date refusée, refus de Vik, lien absent,
état inattendu), 1 quand un préalable ou un argument est au rouge.

Une fois le paiement fait, `--reprise IDORDER` et `--nettoyer` fonctionnent
comme avant, parce que la réservation est inscrite au registre local avec le
statut `en_attente_reprise`.

## 2. Ce que fait la commande, dans l'ordre

1. **Arguments.** Chambre numérique, date `AAAA-MM-JJ` qui existe au
   calendrier et qui n'est pas passée, nuits de 1 à 30. La commande refuse de
   se combiner avec `--appliquer`, `--verification`, `--reprise` ou
   `--nettoyer`.
2. **Préalables de `run_preconditions`**, les mêmes que pour tous les modes :
   hôte hors production, `environment: staging`, Vik Channel Manager absent
   du disque, VikStripe en clés de test. `reserver_une` exige en plus
   `VIKSTRIPE_TEST_KEYS_OK`.
3. **Marque, hôte et groupe de calendrier de la chambre**, lus dans
   lme-brands sur la cible (mode distant `room-facts`), jamais tenus dans le
   script (règle absolue n°5). Une chambre sans marque est refusée.
4. **Levier** posé sur l'hôte de la marque (`linstantcle.ch` pour la chambre
   2). La commande vérifie que la marque est bien résolue, puis remet le
   levier à sa valeur d'entrée en sortie, quoi qu'il arrive.
5. **Recherche** (`task=search`), qui n'écrit rien. Elle rend les horodatages
   d'arrivée et de départ que Vik a calculés lui-même.
6. **Contrôle de la date** avec ces horodatages (mode distant
   `stay-conflicts`, §3). À la moindre occupation, la commande refuse et rien
   n'est soumis au-delà de la recherche.
7. **Devis, coordonnées, `saveorder`**, par le parcours existant
   (`vik_creer_reservation`). L'adresse de recette est
   `recette+<epoch>@recette-automatisee.icl-dev.invalid`, et Stripe est la
   passerelle 3.
8. **Inscription au registre** (`~/.icl-dev-recette/<hôte>/reservations.tsv`),
   avant toute autre lecture.
9. **Relecture en base** : le statut doit être `standby` et l'adresse celle
   de recette générée. Sinon, la réservation passe `a_nettoyer` et la
   commande sort en 2.
10. **Lien Stripe Checkout**, relevé dans la page. Il doit commencer par
    `https://checkout.stripe.com/`.

## 3. Le refus d'une date occupée

Le refus ne laisse pas Vik trancher. Sont contrôlées :

- la chambre elle-même ;
- les chambres que `sir_vikbooking_calendars_xref` lui relie, dans les deux
  sens (c'est la table que lit `updateSharedCalendars()`,
  `site/helpers/lib.vikbooking.php:6701`) ;
- les chambres de son `availability_group` dans lme-brands.

Sur chacune, la commande cherche deux choses. D'abord les occupations
(`sir_vikbooking_busy`) qui recouvrent le séjour jusqu'à `realback`, battement
de ménage compris, comme le fait `roomBookable()`. Ensuite les verrous
temporaires encore valides (`sir_vikbooking_tmplock`). **Une seule occupation
suffit au refus, quel que soit le nombre d'unités** : c'est plus strict que
Vik, et c'est voulu. Toutes les chambres ont aujourd'hui une seule unité.

Une réservation `standby` sans verrou ne compte pas, parce qu'elle n'occupe
rien : Vik ne crée ses occupations qu'au paiement.

**Relevé en passant.** `sir_vikbooking_calendars_xref` relie la chambre 2 à
une chambre 3 (ligne 8, `mainroom = 3`), qui n'existe pas dans
`sir_vikbooking_rooms`. Des occupations de la chambre 3 existent pourtant,
reportées par calendrier partagé depuis la 2 et la 4. C'est sans effet sur le
contrôle, qui compte la 3 comme les autres. C'est un résidu de configuration
de Vik, et le retirer est un geste de Thomas.

## 4. La vue booking : une exception décidée par Thomas

La consigne disait deux choses : rendre le lien Stripe Checkout, et ne jamais
ouvrir la vue booking avec la clé d'une réservation, parce qu'elle écrit à
l'affichage (`constat-vues-vik-par-view.md` §4). Or le lien n'existe que dans
cette vue. `saveorder()` redirige lui-même vers
`view=booking&sid=…&ts=…` (`site/controller.php:1642` en 1.8.14), et
VikStripe crée la session Checkout pendant cet affichage. Aucun autre chemin
du parcours client ne rend ce lien.

**Décision de Thomas, 26 septembre : suivre cette redirection une fois**, parce
qu'elle fait partie du parcours client réel, et ne jamais rouvrir la vue
ensuite.

Pour une réservation `standby`, la vue écrit dans trois cas seulement
(`site/views/booking/view.html.php`, branche `standby`, relue en 1.8.15 sur
`staging13`) : la chambre n'est plus réservable, l'arrivée est passée, ou
`minautoremove` est écoulé. Elle passe alors la réservation en `cancelled` et
supprime ses occupations. Chacun de ces cas est écarté avant l'affichage :

| Cas qui fait écrire la vue | Écarté par |
|---|---|
| chambre plus réservable | contrôle de la date (§3), quelques secondes avant la création |
| arrivée passée | contrôle des arguments : aucune date antérieure à aujourd'hui |
| `minautoremove` écoulé | `minautoremove = 0` sur `staging13`, lu par sa clé : jamais d'annulation automatique |

La relecture en base qui suit l'affichage (§2, étape 9) confirme que rien n'a
été annulé.

**Mécanique notée.** `vik_curl_post_brut` enchaîne `-X POST` et `-L`, si bien
que curl suit la redirection en gardant la méthode POST. Le test du §5
montre qu'une seule commande porte l'adresse de recette : la soumission n'a
pas été rejouée.

**Hors du périmètre de cette commande.** `--reprise` (vérification 6c) et
`--nettoyer` ouvrent toujours la vue booking avec la clé. Pour une réservation
payée, donc `confirmed`, la vue n'écrit rien dans la branche `standby`. Elle
appelle `invokeChannelManager`, qui reste sans effet puisque VCM est absent.
Pour une réservation encore `standby`, par exemple si Thomas n'a pas payé,
`--nettoyer` rouvrirait la vue : l'éviter tant que ce point n'est pas tranché.

## 5. Le test : L'Aparté, arrivée dans deux jours

**Refus éprouvé d'abord**, sur une date occupée : chambre 2, arrivée le
7 octobre 2026, deux nuits. La commande a trouvé trois occupations de la
réservation 1695 : chambre 2, puis chambres 3 et 4 reportées par calendrier
partagé. Elle a refusé avant le devis, remis le levier à sa valeur d'entrée
et rendu `SORTIE=2`. Les refus d'arguments ont aussi été essayés un par un :
date impossible, date passée, zéro nuit, combinaison avec `--appliquer`,
chambre non numérique, format de date.

**Création** : `--reserver 2 --arrivee 2026-09-28 --nuits 1`, le 26 septembre
vers 14 h 05 UTC.

| Fait | Valeur |
|---|---|
| Préalables | quatre au vert |
| Marque et hôte lus dans lme-brands | `linstantcle`, `linstantcle.ch`, groupe `4` |
| Chambres contrôlées | 2, 3, 4 ; aucune occupation, aucun verrou |
| Séjour selon Vik | 28.09 15:00 → 29.09 11:00 UTC |
| Réservation | **#1842**, `standby`, adresse de recette, non payée |
| Commandes portant cette adresse | 1 |
| Verrou temporaire | 1 (`minuteslock = 20`), aucune occupation avant paiement |
| Registre local | `1842 … linstantcle 2 en_attente_reprise` |
| Lien rendu | `https://checkout.stripe.com/c/pay/cs_test_…`, session en mode test |
| Levier | remis à `reservation.sexcaperoom.ch` |
| Sortie | `SORTIE=0` |

Le lien complet n'est pas recopié ici : il est dans la sortie de la commande.

**À faire par Thomas** : payer 1842 avec ce lien, carte de test
`4242 4242 4242 4242`. Le verrou temporaire expire vers 14 h 25 UTC, ce qui
ne gêne pas le paiement : le retour de Stripe passe par `notifypayment`. Sur
la préproduction, personne d'autre ne réserve la chambre entre-temps. Ensuite,
`./recetter-moteur.sh --hote staging13.linstantcle.ch --reprise 1842`.

## 6. Fichiers modifiés

- `recetter-moteur.sh` : options `--reserver`, `--arrivee` et `--nuits`, leurs
  refus, les modes distants `room-facts` et `stay-conflicts`, et
  l'aiguillage principal.
- `recetter-moteur-vik.sh` : un point d'appel facultatif,
  `VIK_CONTROLE_DATES`, entre la recherche et le devis de
  `vik_creer_reservation`, avec le statut `date_refusee`. S'y ajoutent
  `controle_dates_libres` et `reserver_une`. Les vérifications 3 et 5-6
  restent inchangées : le point d'appel est vide pour elles.
