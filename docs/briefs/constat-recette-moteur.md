# Constat — recette du moteur, ce qui est mesurable sans réservation réelle ni clé

22 septembre 2026. Réponse au prompt de recette de `plan-de-marche.md` § « La recette du moteur — les huit
vérifications » et chantier B, à partir de `constat-deploiement-moteur.md` chapitre 6,
`constat-script-deploiement.md` et `constat-mail-guard.md`. Mené sur `staging13.linstantcle.ch`, seule
préproduction existante à ce jour.

**Ce que ce constat établit et ce qu'il n'établit pas.** Les vérifications 1 à 4 sont mesurées en entier, par
HTTP et par appel direct des crochets WordPress avec des données synthétiques. Les vérifications 5 et 6 sont
mesurées pour moitié, exactement comme le prompt les qualifie : ce que notre code compose, pas ce qui est
délivré ni la session Stripe elle-même. Les vérifications 7 et 8 ne sont pas menées, sur instruction expresse.
**Aucune écriture en base de données Vik, aucune réservation créée, aucune clé lue** (Stripe restée en
préfixe uniquement, comme déjà établi par `deployer-moteur.sh`). Une écriture s'est produite, et c'est
attendue : `lme_brands_log()` en niveau erreur pose `update_option('lme_brands_alert_state', …)` dans
`sir_options`, comportement normal et déjà en place du greffon dès qu'on l'exerce — pas une écriture faite à
sa place, mais l'effet du greffon qu'on recette.

**Le levier a été basculé trois fois pendant cette session** (`reservation.sexcaperoom.ch` → `linstantcle.ch`
→ un hôte inconnu), toujours par `deployer-moteur.sh --forcer-override`, jamais à la main. **Remis à
`reservation.sexcaperoom.ch` en fin de session**, confirmé par une quatrième exécution du script en mode
simulation (« levier déjà posé : reservation.sexcaperoom.ch »).

---

## État du serveur avant toute vérification

`./deployer-moteur.sh --env staging --hote staging13.linstantcle.ch`, sans `--appliquer` : tous les
préalables au vert, les trente et un fichiers suivis par git sous `mu-plugins/` et `themes/astra-child/`
déjà à jour sur le serveur, `functions.php` déjà fusionné (7463 o), le levier déjà posé à
`reservation.sexcaperoom.ch`. **Le moteur est donc déjà déployé sur cette préproduction avant cette
session** : `lme-brands` et `lme-mail-guard` y vivent déjà, à l'identique du dépôt. Aucun redéploiement n'a
été nécessaire.

---

## Vérifications 1 à 4 — en trois passes

### Passe A — `reservation.sexcaperoom.ch` (levier déjà en place)

**Vérification 1, hôte et jetons.** `curl -sL https://staging13.linstantcle.ch/fr/book-now/` : HTTP 200,
57 occurrences de `--srlm-` dans le corps de la page finale (redirection `/fr/` suivie, comme
`constat-script-deploiement.md` §3.5 l'exige). La marque résout bien `sexcaperoom` : confirmé par le journal
(`host_override_used`, `override_host: reservation.sexcaperoom.ch`) et par l'apparence.

**Vérification 2, les quatre vues publiques.** Chaque vue a un mécanisme distinct (chapitre 4.3 du brief,
`room-filter.php`) : trois filtrent une requête déjà déposée, `search` a un vrai hook natif qui retire une
annonce du résultat. Testé par injection de paramètre GET (`roomdetails`, `availability`, `roomslist`) et par
appel direct du filtre natif (`search`), preuve par le journal `[lme-brands]` pour les trois premières,
preuve par la valeur de retour pour la dernière.

| Vue | Requête / appel | Résultat |
|---|---|---|
| `roomdetails` | `GET /fr/private-villas/le-boudoir-du-desir/?roomid=2` (page native chambre 4, marque sexcaperoom, injection de la chambre 2, linstantcle) | journal : `foreign_room_param_stripped`, `roomid=2`, `resolved_brand=linstantcle`, `expected_brand=sexcaperoom` — **retiré** |
| `roomdetails` (témoin) | `GET .../le-boudoir-du-desir/?roomid=4` (même marque) | **aucune ligne de journal** : rien retiré, comme attendu |
| `availability` | `GET /fr/book-now/?room_ids=2` | journal : `foreign_room_param_stripped`, `param=room_ids`, chambre #2 — **retiré** |
| `roomslist` | `GET /fr/book-now/?category_id=1` (catégorie Vik 1 = chambres 1, 2, 5, 7, toutes linstantcle) | journal : `foreign_room_param_stripped`, `category_id=1`, chambre #1 — **retiré** |
| `roomslist` (témoin) | `GET /fr/book-now/?category_id=3` (catégorie 3 = chambres 4, 9, sexcaperoom) | **aucune ligne de journal** |
| `search` | `apply_filters('vikbooking_apply_search_results_filtering', null, ['idroom' => N], [])` | chambre 1 → `false` (retirée) ; chambre 2 → `false` ; chambre 4 → `null` (gardée) ; chambre 9 → `null` ; chambre 10 → `null` |

**Les trois expériences Sexcape Room (chambres 4, 9, 10) passent sur les quatre vues ; les chambres
linstantcle testées (1, 2) sont retirées sur les quatre.** Chambres 9 et 10 n'ont toutefois **aucune page
publique aujourd'hui** — `sir_vikbooking_wpshortcodes` les rattache aux pages 6586 et 6287, toutes deux en
statut WordPress **`private`** (chambre 8, linstantcle, également privée sous la page 6585), cohérent avec
« les chambres 8, 9 et 10 rouvrent le 24 septembre » de `plan-de-marche.md` §F. Testées ici par le mécanisme
lui-même (injection de paramètre sur une page tierce, et appel direct du filtre `search`), qui ne dépend pas
du statut de publication de la page propre à la chambre.

**Limite à noter, déjà documentée dans `room-filter.php` et non un défaut de cette passe : le filtrage de
présentation ne protège que le paramètre de requête, pas le contenu natif d'une page.** Visiter
`https://staging13.linstantcle.ch/fr/private-villas/l-aparte/` (page native de la chambre 2, linstantcle)
sous ce même levier sexcaperoom rendrait L'Aparté sans qu'aucune règle ne s'y oppose : aucun paramètre de
requête à retirer, rien à filtrer. C'est le périmètre non couvert que `room-filter.php` documente lui-même
(« elle n'est pas opposable ») et que G2 (liste blanche d'hôte, non déployée) doit fermer. Non testé ici en
tant que défaut, puisque ce n'en est pas un pour ce chantier : c'est un rappel, pas une régression.

**Vérification 3, garde de réservation.** Appel direct de `do_action('vikbooking_before_create_booking_record', …)`
avec des données synthétiques (`array(array('id' => N)), array(), array(), array()`), jamais le contrôleur
réel de Vik : aucune commande créée.

| Chambre | Marque | `avail` | Résultat | Journal |
|---|---|---|---|---|
| 2 | linstantcle | 1 | **refusée**, 403, « Cette réservation ne peut pas être créée depuis cette adresse » (français, langue de sexcaperoom) | `foreign_room_booking_attempt` |
| 5 | linstantcle | 0 | **refusée**, même message | `unavailable_room_booking_attempt` |
| 6 | sexcaperoom | 0 | **refusée**, même message, y compris même marque | `unavailable_room_booking_attempt` |
| 4 | sexcaperoom | 1 | **acceptée** (script atteint sa fin sans `wp_die`) | — |

Le refus d'une chambre désactivée précède la résolution de marque et s'applique même à une chambre de la
marque courante (chambre 6) : conforme au code, `booking-guard.php` §1.

**Vérification 4, apparence.** Jetons `--srlm-` présents (57, vérification 1) sous le levier sexcaperoom.
Confirmé absents en passe B ci-dessous.

### Passe B — `linstantcle.ch` (`--forcer-override`)

Bascule : `./deployer-moteur.sh --env staging --hote staging13.linstantcle.ch --override-host linstantcle.ch
--forcer-override --appliquer`. Préalables au vert, fichiers déjà à jour, levier remplacé
(`override_state=replaced`). **Le script sort en erreur à sa propre étape 6** : sa vérification d'apparence
est câblée pour la passe sexcaperoom (elle exige la présence des jetons) et échoue donc quand on la force
sur l'autre marque. **Ce n'est pas un défaut du moteur, c'est une limite du script, attendue et sans
conséquence** : tout ce qui précède cette étape (fusion, copie, absence d'erreur fatale, réponse HTTP 200)
est resté vert.

**Vérification 1 et 4.** `curl -sL .../fr/book-now/` : HTTP 200, **0** occurrence de `--srlm-`. La marque
résout `linstantcle`.

**Vérification 2**, même méthode, chambres inversées :

| Vue | Requête / appel | Résultat |
|---|---|---|
| `roomdetails` | `GET /fr/private-villas/l-entracte/?roomid=4` (page native chambre 1, injection chambre 4, sexcaperoom) | `foreign_room_param_stripped`, `roomid=4` — **retiré** |
| `roomdetails` (témoin) | `.../l-entracte/?roomid=1` | rien retiré |
| `availability` | `GET /fr/book-now/?room_ids=4` | `foreign_room_param_stripped`, chambre #4 — **retiré** |
| `roomslist` | `GET /fr/book-now/?category_id=3` (chambres 4, 9, sexcaperoom) | `foreign_room_param_stripped`, `category_id=3`, chambre #4 — **retiré** |
| `search` | filtre natif | chambres 1, 2, 7, 8 (linstantcle) → `null`, gardées ; chambres 4, 9, 10 (sexcaperoom) → `false`, retirées |

**Piège de la passe B, tel qu'annoncé par la commande : la liste des chambres servies départage, pas
l'absence de jetons seule.** Ici la liste est sans ambiguïté : les quatre chambres vendues de la marque
linstantcle (1, 2, 7, 8) passent le filtre `search`, les trois de sexcaperoom (4, 9, 10) sont retirées.

**Correction à porter au texte de la commande de recette : « les sept chambres vendues de L'Instant Clé »
est une affirmation fausse à corriger.** Le registre (`config/brands.php`) ne rattache que **quatre**
chambres vendues à la marque `linstantcle` (1, 2, 7, 8), et **trois** à `sexcaperoom` (4, 9, 10) — sept au
total, **toutes marques confondues**. Le chiffre sept vient de `constat-deploiement-moteur.md` chapitre 4,
étape 3 (« les sept chambres vendues doivent apparaître synchronisées » à l'écran de santé), une vérification
qui porte sur l'ensemble du registre et ne distingue pas les marques — elle n'a rien à voir avec ce que
`room-filter.php` doit laisser passer sous un hôte donné. Vérifié ici par appel direct des quatre vues sur les
quatre chambres linstantcle (1, 2, 7, 8, toutes gardées) et par appel sur les trois chambres sexcaperoom
(toutes retirées) : c'est cette liste-là, quatre et non sept, qui fait foi pour la passe B.

**Vérification 3.** Chambre 4 (sexcaperoom, étrangère) : refusée, 403, message **en anglais** (« This booking
cannot be created from this address », premher langue déclarée de `linstantcle` dans le registre). Chambre 1
(linstantcle, native) : acceptée.

### Passe C — hôte qui n'est celui d'aucune marque

Bascule : `--override-host hote-inconnu.example.invalid --forcer-override --appliquer`. Même limite attendue
sur l'étape 6 du script (câblée pour sexcaperoom).

**Aucun jeton `--srlm-`** sur `.../fr/book-now/` (0 occurrence). **Aucune règle de marque appliquée** :
`GET /fr/private-villas/l-entracte/?roomid=4` (chambre sexcaperoom injectée sur une page linstantcle) et
`GET /fr/book-now/?category_id=3` **ne produisent aucune ligne de journal** `foreign_room_param_stripped` —
le filtrage ne retire rien, exactement ce que `room-filter.php` documente (« hôte non enregistré : aucun
contexte de marque à faire respecter »).

**Garde de réservation, comportement asymétrique et voulu.** Chambre 4 (active, sexcaperoom) : **acceptée**
— aucune marque à faire respecter, la chambre est active, rien ne s'y oppose. Chambre 5 (désactivée,
`avail=0`) : **refusée quand même** — ce refus ne demande aucune marque, il précède toute résolution
(`booking-guard.php` §1). Les deux résultats sont conformes au code lu, pas une anomalie.

### Fin des trois passes

Levier remis à `reservation.sexcaperoom.ch` (`--forcer-override --appliquer`), vérifié vert par le script
lui-même (jetons présents, apparence confirmée), puis reconfirmé par une exécution en simulation
(« levier déjà posé : reservation.sexcaperoom.ch »).

---

## Vérification 5 — moitié mesurable : ce qui est composé, pas ce qui est délivré

**Un chemin propre existe, et il ne crée pas de réservation.** `VikBooking::sendBookingEmail($id, ['guest'],
true, true)` (`site/helpers/lib.vikbooking.php:6069`) est la fonction que l'action d'administration native
« Renvoyer l'e-mail » (`resendordemail`, `admin/controller.php:3614`) appelle pour réémettre le message
client d'une réservation existante. Lue en entier (446 lignes) : **aucune écriture en base**, seulement des
`SELECT` pour recomposer le message (tarifs, options, chambre). L'appeler directement, sans passer par
l'écran d'administration, évite en plus l'écriture d'historique que fait ce dernier — chemin plus propre
encore que le bouton natif pour cette vérification.

**Exercé sur une réservation confirmée de chaque marque**, choisie sans lire ni journaliser d'adresse client :
commande #1816 (chambre 1, linstantcle), commande #1823 (chambre 10, sexcaperoom), toutes deux `status =
confirmed`, `channel` nul (réservation directe). Un filtre `wp_mail` temporaire, ajouté par ce seul script de
diagnostic et retiré avec lui, a lu les lignes `From:` et `Reply-to:` telles que `wp_mail()` du cœur les
reçoit — établi ligne à ligne avec la classe `JMail` (`admin/helpers/src/mail/mail.php:162-183` : `$headers[]
= "From: {$this->from}"` et `$headers[] = "Reply-to: {$replyto}"`) — **avant** que `lme-mail-guard` ne
détourne le destinataire.

| Commande | Chambre | Marque résolue | En-tête `From` composé | En-tête `Reply-to` composé |
|---|---|---|---|---|
| 1816 | 1 | linstantcle | `L'Instant Clé <reservations@linstantcle.ch>` | `reservations@linstantcle.ch` |
| 1823 | 10 | sexcaperoom | `Sexcape Room <reservations@sexcaperoom.ch>` | `reservations@sexcaperoom.ch` |

Conformes au registre (`config/brands.php`, `sender_name`/`sender_email`/`reply_to` de chaque marque).

**Ce que cela prouve, et ce que cela ne prouve pas.** Ces en-têtes sont ceux que `wp_mail()` du cœur a reçus
en argument, avant tout envoi réel — **ce que notre code compose**, exactement la limite posée par la
commande. Ni l'un ni l'autre message n'a atteint une boîte de réception client : `lme_mail_guard_is_production_context()`
retourne toujours `false` sur cette préproduction (`wp_get_environment_type() !== 'production'`, premier
`if` de la fonction), donc `lme_mail_guard_should_redirect()` retourne toujours `true`, quel que soit l'hôte —
confirmé par le journal (`production_brand_host_redirected` pour chacun des deux envois). L'adresse
fourre-tout `LME_MAIL_GUARD_CATCHALL_EMAIL` est bien définie sur cette préproduction (présence vérifiée par
nom de constante, jamais sa valeur, règle absolue n°2). Les deux envois réels ont donc chacun été détournés
vers cette adresse, jamais vers le client. Le piège `from_email_force` de WP Mail SMTP a déjà écrasé en
silence un expéditeur correct par le passé (`plan-de-marche.md`) : rien ici ne le rejoue, mais rien ici ne le
dément non plus, puisque **la tentative d'envoi elle-même a échoué** (`sendBookingEmail()` a retourné
`false` pour les deux commandes, cause non investiguée — probablement un problème de transport SMTP propre à
ce contexte CLI, sans rapport apparent avec `lme-brands` ou `lme-mail-guard`, hors périmètre de cette
vérification). **La lecture d'un vrai message reste un geste de Thomas**, dans une vraie boîte de réception,
seule façon de clore ce que ce constat ne peut pas trancher.

**Effet de bord observé, déjà connu et sans lien avec cette vérification** : la commande #1823 a déclenché
`mail_brand_leak` (le corps du message Sexcape Room nomme encore L'Instant Clé) — c'est exactement l'état
attendu tant que D4 n'est pas livré, pas une découverte.

**Note de méthode, même piège que `constat-script-deploiement.md` §3.1.** La sortie de `wp-cli` traverse
TranslatePress avant d'atteindre ce terminal : les mots « From », « confirmed », « CALLING », « interrupted »
apparaissent traduits ou paraphrasés dans les échos de diagnostic bruts, jamais dans les valeurs réellement
lues par le code (qui ne passent, elles, par aucune traduction). Le tableau ci-dessus restitue les valeurs
telles que le code les compose, pas telles que le terminal les a affichées.

---

## Vérification 6 — moitié mesurable : métadonnée et URL, jamais la session Stripe

**Rendu de la page de paiement.** `GET https://staging13.linstantcle.ch/fr/your-booking-detail/` (page 845,
partagée par les deux marques) sans session de réservation : HTTP 200, aucune occurrence de « erreur grave »,
« fatal error » ni « there has been a critical error » dans le corps. Cela ne constitue pas un parcours de
paiement complet (aucune réservation n'a été créée pour l'exercer de bout en bout), mais confirme que la page
elle-même ne lève plus l'erreur fatale inconditionnelle que `constat-fatal-page-paiement.md` avait constatée
avant B9c.

**`payment-brand.php`, exercé au niveau du crochet.** Un objet synthétique, minimal (`isDriver()`, `get()`,
`set()`), reproduit l'interface d'un `JPayment` Stripe et a été passé à
`do_action('payment_before_begin_transaction_vikbooking', array(&$objet))` — exactement la forme que Vik
produit réellement et que WordPress déballe en argument direct (établi par B9c/B9d,
`constat-fatal-page-paiement.md`, `constat-signatures-crochets.md`). Aucune commande, aucune session Stripe,
aucune clé : l'objet n'existe qu'en mémoire PHP le temps du script.

| Commande | Chambre | Marque | Résultat |
|---|---|---|---|
| 1816 | 1 | linstantcle | aucune erreur, `tn_metadata` = `{"lme_brand":"linstantcle","lme_room_ids":"1","lme_booking_id":"1816"}` |
| 1823 | 10 | sexcaperoom | aucune erreur, `tn_metadata` = `{"lme_brand":"sexcaperoom","lme_room_ids":"10","lme_booking_id":"1823"}` |

**Aucune erreur fatale dans les deux cas** : confirme, à ce niveau, que la prémisse fausse corrigée par B9c
(§ `constat-fatal-page-paiement.md`) ne s'est pas reproduite. La métadonnée de marque est correcte pour
chaque commande, cohérente avec la chambre réellement réservée.

**L'URL de retour, non vérifiable dans ce contexte, et c'est un fait établi, pas une supposition.**
`lme_brands_correct_payment_urls()` a besoin de l'hôte HTTP réel de la requête
(`lme_brands_current_raw_http_host()`) pour décider s'il faut corriger `return_url`/`error_url`/`notify_url` ;
un appel par `wp-cli` ne porte aucun `HTTP_HOST`. Confirmé par le journal, pour les deux commandes :
`payment_url_host_unavailable`, « Hôte HTTP réel introuvable […] : impossible de vérifier ou de corriger
l'hôte ». Les trois URL synthétiques (`https://exemple-etranger.invalid/...`) sont donc restées inchangées —
comportement attendu de ce filet en l'absence d'hôte, pas un échec de la fonction. Vérifier la correction
réelle demanderait une vraie requête HTTP de paiement, donc une réservation réelle : **hors périmètre de
cette vérification**, comme la commande le prévoit pour la session Stripe elle-même.

**La session chez Stripe demande une clé : hors périmètre**, non tentée.

---

## Vérifications 7 et 8 — non menées, sur instruction

**Vérification 7** (rappel avant séjour, bonne identité) : attend la revue Cowork de B5, non faite. Non
menée.

**Vérification 8** (redirection 302 hors liste blanche) : attend G2, non écrit. Non menée.

---

## Ce que ce constat ne couvre pas

- La délivrance réelle d'un e-mail dans une boîte de réception (vérification 5) : geste de Thomas.
- La session Stripe Checkout et son contenu réel (vérification 6) : demande une clé, hors périmètre.
- Le parcours de paiement de bout en bout, avec une vraie réservation : non tenté, la commande l'interdisant
  explicitement.
- Les vérifications 7 et 8, sur instruction expresse.
- La cause de l'échec de `sendBookingEmail()` (résultat `false` pour les deux envois) : non investiguée,
  probablement un problème de transport SMTP propre à ce contexte, sans rapport apparent avec le code recetté
  ici.
- Le défaut déjà connu du filtrage de présentation face au contenu natif d'une page (paragraphe dédié,
  passe A) : rappelé, pas re-décidé, périmètre de G2.

---

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-22 | Création. Vérifications 1 à 4 menées en trois passes (sexcaperoom, linstantcle, hôte inconnu), toutes vertes. Vérifications 5 et 6 menées à moitié, comme prévu par la commande : en-têtes et métadonnée composés, confirmés par le journal, jamais par une boîte de réception ni une session Stripe réelle. Vérifications 7 et 8 non menées. Correction apportée au texte de la recette : « les sept chambres vendues de L'Instant Clé » est faux, quatre chambres seulement (1, 2, 7, 8) portent cette marque. Levier remis à `reservation.sexcaperoom.ch` en fin de session, vérifié deux fois. |
