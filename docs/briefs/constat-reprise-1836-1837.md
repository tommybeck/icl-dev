# Constat — reprise des réservations d'essai #1836 et #1837, et correction de B9i

25 septembre 2026. Suite de `constat-reprise-1830-1831.md`, dont il corrige
le §3 et tranche les points 3.4 et 3.5. À l'intention de Cowork et de Thomas.

**Tout ce qui suit vient de lectures dans le code de Vik Booking 1.8.15 sur
`staging13` (par SSH, en lecture seule), de requêtes `SELECT` à colonnes
explicites et de deux exécutions réelles du script corrigé.** Rien n'a été
déployé. Rien n'a été écrit en base, ni par SQL ni par une tâche de Vik.
Aucune clé n'a été lue. Comme dans les constats précédents, les `sid` sont
omis, ainsi que les adresses des vrais clients.

---

## 1. Prémisses, vérifiées à la source avant d'agir

| Prémisse | Vérifiée comment | Résultat |
|---|---|---|
| Vik Channel Manager retiré | `ls wp-content/plugins/`, `wp plugin list` | dossier absent, greffon non listé |
| Préproduction | `wp_get_environment_type()` | `staging` |
| 1836 et 1837 confirmées et payées | `sir_vikbooking_orders` | `confirmed`, `totpaid` = `total` (398 et 329) |
| Pas encore reprises | registre local | `en_attente_reprise` |
| Garde-fou de messagerie déployé | md5 des trois fichiers | 1.1.0, identique au dépôt |
| B5 déployé | md5 de `mail-brand.php` | identique au dépôt (commit `c22f5b7`) |

La copie `.local/vikbooking` est en 1.8.14 : toutes les lectures ci-dessous
viennent du serveur.

---

## 2. Un défaut que le constat précédent n'avait pas vu : la 6c ne lisait pas la réservation

Le script lisait `https://<hôte>/index.php?option=com_vikbooking&view=booking&sid=…&idorder=…`.
**Sur WordPress, cette adresse rend la page d'accueil**, en 200, titre
« Accueil - L'Instant Clé ». Elle ne porte aucun shortcode de Vik. Et la
vue `booking` exige `sid` **et** `ts`, pas `idorder`
(`site/views/booking/view.html.php:25-30` : sinon `VBINSUFDATA`).

La vérification 6c concluait sur l'absence des mots `standby`, `en attente`
et `pending` dans une page qui ne parlait pas de la réservation. **Elle
était verte par construction, pour 1830 et 1831 comme pour toutes les
autres.** Que 1830 et 1831 soient `confirmed` reste vrai, mais c'était
établi par la base, pas par la 6c.

La vraie page est celle qui porte le shortcode `booking`
(`sir_vikbooking_wpshortcodes`, page 845, `/your-booking-detail/`,
redirigée vers `/fr/les-details-de-votre-reservation/`). Vik y affiche
l'état par la classe `vbo-booking-details-head-confirmed`, `-pending` ou
`-cancelled` (`tmpl/default.php:271-278`).

---

## 3. Pourquoi `docancelbooking` répond 403

**Cause établie : le nonce WordPress `vikwp_nonce` n'est pas transmis, et il
ne peut pas l'être, parce que Vik ne rend pas le formulaire d'annulation sur
cette installation.**

1. `docancelbooking()` (`site/controller.php:2740`) commence par
   `JSession::checkToken()`. Faute de jeton : `close(403, JINVALID_TOKEN)`,
   **avant toute lecture de la réservation**. Ce 403 ne dit donc rien du
   statut : il a frappé 1826 et 1827 (`standby`) comme 1830 et 1831
   (`confirmed`). L'hypothèse « encore `standby` » était de trop dès le
   départ.
2. `checkToken()` (`libraries/adapter/session/session.php:214-258`) attend
   un nonce WordPress nommé `vikwp_nonce` pour l'action
   `JSession::getFormTokenAction.1`, en POST ou dans l'en-tête
   `X-CSRF-Token`. Le script cherchait `viktoken` et `vikwp_nonce` sur une
   page qui était la page d'accueil (§2). Il n'a jamais rien trouvé.
3. Sur la vraie page, le formulaire `docancelbooking` et son nonce ne sont
   rendus que si `canc_allowed` (`tmpl/default.php:235` et `:1203`), qui
   exige `resmodcanc > 1`. **`resmodcanc` vaut `1` sur `staging13`**, lu par
   sa clé dans `sir_vikbooking_config`, soit « Disabled, with Request » dans
   l'administration. `resmodcancmin` vaut `1`. Le plan tarifaire des quatre
   réservations (« Standard Rate », `free_cancellation = 1`,
   `canc_deadline = 7`) n'y est pour rien : c'est le mode global qui ferme.
4. Dans ce mode, la page rend seulement le formulaire `cancelrequest`
   (`tmpl/default.php:1157-1198`). Il envoie une demande à l'administrateur
   et consigne l'évènement `CR` dans l'historique. **Il n'annule rien.**
   Vérifié sur les pages de 1836 et 1837 : `cancelrequest` présent,
   `docancelbooking` absent, un nonce présent (celui du formulaire de
   demande).
5. Même avec un nonce, `docancelbooking()` refait le calcul de `canc_allowed`
   (l. 2835-2841) et renverrait vers la page avec `VBOERRCANNOTCANCBOOK`.

**Conclusion : Vik ne permet pas, sur cette installation, d'annuler une
réservation d'essai autrement qu'à la main, dans l'administration (Bookings).**
Les seules autres voies seraient de changer `resmodcanc`, une écriture dans
une configuration copiée de la production et qui change ce que voient les
clients, ou de fabriquer un nonce pour un formulaire que Vik ne propose pas.
Ce serait contourner. Aucune des deux n'est faite.

À savoir pour le jour où l'annulation en libre-service serait ouverte :
`docancelbooking()` **rembourse** le montant payé par la passerelle
(l. 2876-2946, `refund()` de VikStripe), écrit à l'administrateur et au
client, et appelle Vik Channel Manager si son fichier existe (l. 2949).

Aussi relevé, sans effet sur le 403 : le script passait
`recette@recette-automatisee…` comme `email`, et non l'adresse de la
réservation. Vik ne compare pas ce champ, il le recopie dans les notes
d'administration.

---

## 4. Comment Vik planifie le rappel, et ce que son déclenchement enverrait

### 4.1 Le crochet, établi dans le code

- `VikBookingCron::setup()` est accroché à `plugins_loaded`, priorité
  `PHP_INT_MAX - 1` (`vikbooking.php:601`).
- Il lit les tâches **`published = 1`** de `sir_vikbooking_cronjobs`
  (`libraries/system/cron.php:271-296`). Pour chacune, il accroche
  `runJob($id)` au crochet `vikbooking_cron_<class_file sans .php>_<id>`
  (`getScheduleHook`, l. 508-519). Si le crochet n'est pas planifié, il le
  planifie par `wp_schedule_event()` avec la récurrence `schedule_key`
  (l. 169-182).
- Une tâche dépubliée n'a ni action ni évènement. D'où le
  `Invalid cron event` du 24 septembre.

### 4.2 Pourquoi le script se trompait

Le nom construit par le script était juste. **L'identifiant, non.** Il
prenait la dernière ligne `email_reminder` de la table, publiée ou non :

| id | nom | publiée | récurrence | crochet dans WP-Cron |
|---|---|---|---|---|
| 5 | Email pre check-in (new) | non | daily | aucun |
| 6 | Check-in info (old) | non | hourly | aucun |
| **7** | **Check-in info** | **oui** | **hourly** | `vikbooking_cron_email_reminder_7`, prévu le 20.09 08:50:50 UTC |
| 8 | Check-in info (test) | non | daily | aucun |

WP-Cron contient aussi `vikbooking_cron_email_reminder_3`, `_4` et
`vikbooking_cron_precheckin_reminder_2`, des tâches qui n'existent plus dans
la table. `setup()` ne leur accroche aucune action : leur exécution ne fait
rien. `DISABLE_WP_CRON` vaut `true` sur `staging13`, et tous les évènements
sont en retard depuis le 20 septembre.

### 4.3 Ce que la tâche 7 enverrait si on la déclenchait aujourd'hui

Réglages lus un par un par leur clé dans `params` (ni le texte, ni l'adresse
de test en clair) : `checktype = checkin`, `remindbefored = 2`,
`less_days_advance = 1`, `ota_res = 1`, `test = OFF`, `listings` vide.

D'après `admin/cronjobs/email_reminder.php`, `execute()` :

- **Fenêtre** : de minuit aujourd'hui à 23:59:59 à J+2 (l. 282-296), dans
  le fuseau du site. Pas de rattrapage : un passage ne voit que la fenêtre
  du jour où il tourne. Les arrivées manquées depuis le 20 septembre ne
  partiront jamais d'ici.
- **Cible** : **toutes** les réservations `confirmed`, `closure = 0`, dont
  l'arrivée tombe dans la fenêtre, toutes chambres, OTA comprises
  (l. 346-358), moins celles déjà notifiées. La liste des notifiées est
  `flag_char`, 268 identifiants, la plus récente 1800 (l. 478-484, trait
  `VBOCronTrackerArray`).
- **Destinataire** : `custmail` de chaque réservation, puisque le mode test
  est `OFF` (l. 549-560).
- **Envoi** : `sendMail(senderemail, senderemail, custmail, senderemail,
  subject, …)` (l. 660). Donc `From` et `Reply-To` valent `senderemail` de
  Vik, `info@maisonnette-enchantee.ch` (relu ce jour). B5, déployé,
  remplace ensuite l'expéditeur et son nom par ceux de la marque de la
  réservation, et rien d'autre (`mail-brand.php:336`).

**Relevé en base le 25 septembre, fenêtre du 25 au 27 septembre :**

| Réservation | Arrivée (UTC) | Chambre | Marque (registre lme-brands) | Origine | Domaine du destinataire | Déjà notifiée |
|---|---|---|---|---|---|---|
| 1817 | 25.09 15:00 | 4, Le Boudoir du Désir | sexcaperoom | site | gmail.com | non |
| 1490 | 25.09 15:02 | 1, L'Entracte | linstantcle | site | gmail.com | non |
| 1434 | 26.09 15:00 | 4, Le Boudoir du Désir | sexcaperoom | site | gmail.com | non |

**Un déclenchement aujourd'hui composerait donc trois rappels
« Infos de dernière minute pour votre séjour », adressés avant détournement
à trois vrais clients** dont les réservations ont été copiées de la
production. Le `From` serait `reservations@sexcaperoom.ch` pour 1817 et
1434 et `reservations@linstantcle.ch` pour 1490, si B5 résout la
réservation. Le `Reply-To` serait `info@maisonnette-enchantee.ch`. Le corps
serait le gabarit de la tâche, au logo L'Instant Clé (chantier F).

Après le déclenchement, `lme-mail-guard` 1.1.0 remplace le destinataire par
l'adresse fourre-tout de `wp-config.php` (non relue) et ajoute
`X-Original-To`. La transcription garderait les trois vraies adresses dans
`wp-content/lme-mail-guard-envelopes.log` sur la préproduction. Que le
message quitte réellement le serveur dépend encore de WP Mail SMTP. Sa
sous-clé `general.do_not_send` n'a rien rendu à la lecture : **l'état
« Do not send » n'est pas vérifié aujourd'hui.** Enfin, la tâche inscrirait
ces trois réservations dans `flag_char` et réécrirait `last_exec` et `logs` :
une écriture en base, faite par Vik à notre demande.

**Les réservations d'essai ne sont jamais dans la fenêtre.** 1836 et 1837
arrivent le 24 décembre (J+90, `JOURS_RESERVATION_REELLE`). Déclencher le
rappel ne pourrait jamais les viser : il ne toucherait que de vrais clients.

**Le 24 septembre, l'ancien script a échoué par chance.** S'il avait trouvé
la tâche 7, il l'aurait exécutée par `wp cron event run` sur la fenêtre du
24 au 26 septembre.

### 4.4 La vérification 7 corrigée

Elle **constate sans déclencher**. Elle liste les tâches `email_reminder`,
calcule le crochet de chaque tâche publiée comme `getScheduleHook()`, relit
sa prochaine exécution dans WP-Cron, donne la fenêtre qu'aurait un passage
aujourd'hui et l'arrivée de la réservation d'essai. Elle rend **`??`,
« non établie, déclenchement refusé »**, jamais OK. Le mode distant
`cron-run` est supprimé du script : il ne peut plus déclencher aucune tâche.

**Établir la vérification 7 demande une décision, pas du code.** Il faudrait
une fenêtre sans aucune vraie réservation `confirmed`, et une réservation
d'essai arrivant à J+2 au plus. Ou le mode test de la tâche, qui dirige
**tous** les rappels de la fenêtre vers `test_email`, vrais clients compris,
et qui est une écriture dans la configuration de Vik. Aucune des deux ne
revient à Claude Code.

---

## 5. Corrections du script (B9i)

`recetter-moteur.sh` et `recetter-moteur-vik.sh`.

**Code de sortie (§3.1 de l'ancien constat).**
- `0` si toutes les vérifications menées sont OK. `2` si au moins une est
  KO ou `??`, y compris le KO annoncé de la vérification 8. `1` sur un
  préalable au rouge ou une erreur.
- L'état se lit au second champ de chaque ligne du rapport, et non plus par
  un motif ` OK` cherché dans toute la ligne. Un rapport vide rend `2`.
- `--reprise` rend ce code. `--nettoyer` rend `2` s'il reste une
  réservation ouverte.
- **Le tube.** Sous zsh, `commande | tee f` rend le code de `tee`, quoi que
  fasse le script. Aucun script ne peut changer ce qu'un tube rend chez son
  appelant. D'où deux choses :
  - l'option **`--journal FICHIER`** : le script fait lui-même le `tee` et
    sort avec `PIPESTATUS[0]` ;
  - la dernière ligne toujours imprimée, **`SORTIE=<code>`**, y compris sur
    une erreur précoce.

  Testé sous zsh : `| tee` seul rend `0` pour une erreur, alors que
  `$pipestatus[1]`, `SORTIE=1` et `--journal` rendent `1`. Si le tube est
  voulu malgré tout, il faut `setopt pipefail` ou lire `$pipestatus[1]`.
  (`constat-recette-vcm-inactif.md` §2 disait que zsh n'a pas de
  `PIPESTATUS`. C'est vrai en majuscules, mais zsh a `$pipestatus`.)

**Vérification 5 (§3.2).**
- L'adresse de recette est relue en base par l'identifiant
  (`order-facts`). Le mode distant ne la rend que si elle est sur le domaine
  de recette, sinon `HORS_RECETTE`. Le registre ne la contient pas, et on
  ne peut pas la déduire de `ts` : pour 1837, `ts` vaut 1790325560 et
  l'adresse `recette+1790325559@…`.
- Le script retient les lignes où cette adresse, comparée en entier, est
  destinataire (message client) ou en `Reply-To` (copie de
  l'administrateur). Il les affiche toutes.
- La vérification est KO sans aucune ligne, KO sans message client, et KO
  si le `From` composé du message client n'est pas le `sender_email` de la
  marque dans lme-brands, lu sur la cible.
- **Le filtre `#<idorder>` dans l'objet, proposé au §3.2 de l'ancien
  constat, aurait manqué le message client.** Son objet ne porte pas
  l'identifiant (« Votre réservation — Chambre Sexcape »). Seule la copie
  de l'administrateur le porte.

**Préalables (§3.3).**
- `--reprise` et `--nettoyer` passent désormais `run_preconditions` en
  entier : hôte hors production, `environment: staging`, Vik Channel Manager,
  VikStripe en `sk_test_`.
- **Vik Channel Manager doit être absent, et plus seulement inactif.** Le
  mode distant rend `absent` seulement si le greffon n'est pas listé **et**
  si le dossier `wp-content/plugins/vikchannelmanager` n'existe pas. Un
  dossier présent mais non listé rend `fichiers_presents`, et le script
  refuse. C'est la conséquence de `constat-recette-vcm-inactif.md` §7 :
  désactivé, le greffon reste appelé.

**Vérification 6c (§2 ci-dessus).** La vérification lit la vraie page
(shortcode `booking`, `sid` et `ts` pris en base). Elle exige la classe
`vbo-booking-details-head-confirmed` **et**, en base, `status = confirmed`
avec `totpaid > 0`.

**Annulation (§3 ci-dessus).**
- Le script lit la page de réservation. Si Vik n'y rend pas
  `docancelbooking`, il ne poste rien et dit pourquoi.
- Si Vik le rend un jour, le script soumet ce formulaire natif avec son
  nonce et juge le résultat au statut relu en base, jamais à un mot de la
  page. Ce chemin n'a jamais été exercé.
- Une réservation sans adresse de recette n'est jamais touchée.
- `--nettoyer` inscrit « nettoyée » une réservation qu'il trouve déjà
  `cancelled` : après une annulation à la main, il remet le registre à jour.

**Vérifié.** `bash -n` passe sur les deux fichiers, sous le bash 3.2 de
macOS. Le filtre de la vérification 5 et la décision du rapport sont testés
hors ligne : une ligne client, une copie, une adresse piège qui ne diffère
que d'un chiffre, une ligne illisible, aucune ligne ; puis tout OK, un KO
suivi du mot « OK », un `??`, un rapport vide. Enfin les deux reprises
réelles ci-dessous. **Non exercés** : la recette complète `--appliquer` avec
le script corrigé, et `--nettoyer`.

---

## 6. Les deux reprises

```bash
./recetter-moteur.sh --hote staging13.linstantcle.ch --reprise 1836 --journal …/reprise-1836.log
./recetter-moteur.sh --hote staging13.linstantcle.ch --reprise 1837 --journal …/reprise-1837.log
```

Préalables verts pour les deux : hôte hors production, `staging`, Vik
Channel Manager `absent`, VikStripe `sk_test_` (une passerelle).
**Code de sortie réel : 2 pour les deux**, lu sur `$?` et non à travers un
tube.

### 6.1 — #1836 (sexcaperoom, chambre 4)

```
== Vérification 6c — confirmation de #1836 ==
    page de réservation : HTTP 200, état affiché 'confirmed' ; base : statut 'confirmed', payée 1
  OK   #1836 confirmée et payée, affichée confirmée par Vik

== Vérification 5 — transcription d'enveloppe de recette+1790325505@recette-automatisee.icl-dev.invalid ==
    [client] {"ts":"2026-09-25T08:39:46+00:00","to":"recette+1790325505@recette-automatisee.icl-dev.invalid","from":"Sexcape Room <reservations@sexcaperoom.ch>","sender":"","reply_to":"reservations@sexcaperoom.ch","subject":"Votre réservation — Chambre Sexcape","host":"staging13.linstantcle.ch"}
    [copie] {"ts":"2026-09-25T08:39:48+00:00","to":"info@maisonnette-enchantee.ch","from":"L'Instant Clé <info@maisonnette-enchantee.ch>","sender":"","reply_to":"recette+1790325505@recette-automatisee.icl-dev.invalid","subject":"Votre réservation à L'Instant Clé #1836","host":"staging13.linstantcle.ch"}
  OK   message client composé avec From reservations@sexcaperoom.ch, sender_email de 'sexcaperoom'

== Vérification 7 — rappel avant séjour (constat, sans déclenchement) ==
    tâche #5 (email_reminder, daily) dépubliée : jamais inscrite dans WP-Cron
    tâche #6 (email_reminder, hourly) dépubliée : jamais inscrite dans WP-Cron
    tâche #7 publiée (hourly), crochet vikbooking_cron_email_reminder_7, prochaine exécution WP-Cron : 2026-09-20 08:50:50 UTC (WP-Cron ne tourne pas sur la préproduction)
    fenêtre si déclenchée aujourd'hui : arrivées confirmed du 2026-09-25 au 2026-09-27 (UTC), mode test OFF ; arrivée de #1836 : 2026-12-24
    tâche #8 (email_reminder, daily) dépubliée : jamais inscrite dans WP-Cron
  ??   déclenchement refusé : la tâche écrit à toutes les réservations de sa fenêtre, vraies réservations copiées comprises

== Annulation de #1836 ==
  KO   #1836 non annulée : Vik ne propose pas l'annulation sur cette page (resmodcanc « Disabled, with Request » : seul le formulaire de demande, qui n'annule rien), annulation manuelle dans Bookings

== Rapport (reprise #1836) ==
  6c OK  (sexcaperoom) #1836 confirmed et payée en base, page de réservation « confirmed »
  5  OK  (sexcaperoom) From composé reservations@sexcaperoom.ch — ce que WordPress compose, pas ce que le relais Gmail envoie
  7  ??  (sexcaperoom) non établie, déclenchement refusé (#7 vise les arrivées du 2026-09-25 au 2026-09-27, #1836 arrive le 2026-12-24)
  an KO  (sexcaperoom) #1836 non annulée, […même motif…]

SORTIE=2
```

Entre les deux exécutions, le libellé du motif d'annulation a été
reformulé : il affirmait une valeur de `resmodcanc` que le script ne lit
pas. Rien d'autre n'a changé.

### 6.2 — #1837 (linstantcle, chambre 2)

```
== Vérification 6c — confirmation de #1837 ==
    page de réservation : HTTP 200, état affiché 'confirmed' ; base : statut 'confirmed', payée 1
  OK   #1837 confirmée et payée, affichée confirmée par Vik

== Vérification 5 — transcription d'enveloppe de recette+1790325559@recette-automatisee.icl-dev.invalid ==
    [client] {"ts":"2026-09-25T08:40:19+00:00","to":"recette+1790325559@recette-automatisee.icl-dev.invalid","from":"L'Instant Clé <reservations@linstantcle.ch>","sender":"","reply_to":"reservations@linstantcle.ch","subject":"Votre réservation à L'Instant Clé","host":"staging13.linstantcle.ch"}
    [copie] {"ts":"2026-09-25T08:40:21+00:00","to":"info@maisonnette-enchantee.ch","from":"L'Instant Clé <info@maisonnette-enchantee.ch>","sender":"","reply_to":"recette+1790325559@recette-automatisee.icl-dev.invalid","subject":"Votre réservation à L'Instant Clé #1837","host":"staging13.linstantcle.ch"}
  OK   message client composé avec From reservations@linstantcle.ch, sender_email de 'linstantcle'

== Vérification 7 — rappel avant séjour (constat, sans déclenchement) ==
    […tâches 5, 6, 8 dépubliées, comme pour #1836…]
    tâche #7 publiée (hourly), crochet vikbooking_cron_email_reminder_7, prochaine exécution WP-Cron : 2026-09-20 08:50:50 UTC (WP-Cron ne tourne pas sur la préproduction)
    fenêtre si déclenchée aujourd'hui : arrivées confirmed du 2026-09-25 au 2026-09-27 (UTC), mode test OFF ; arrivée de #1837 : 2026-12-24
  ??   déclenchement refusé : la tâche écrit à toutes les réservations de sa fenêtre, vraies réservations copiées comprises

== Annulation de #1837 ==
  KO   #1837 non annulée : Vik ne rend pas le formulaire docancelbooking, seulement la demande cancelrequest qui n'annule rien (mode « Disabled, with Request » relevé le 25 septembre 2026), annulation manuelle dans Bookings

== Rapport (reprise #1837) ==
  6c OK  (linstantcle) #1837 confirmed et payée en base, page de réservation « confirmed »
  5  OK  (linstantcle) From composé reservations@linstantcle.ch — ce que WordPress compose, pas ce que le relais Gmail envoie
  7  ??  (linstantcle) non établie, déclenchement refusé (#7 vise les arrivées du 2026-09-25 au 2026-09-27, #1837 arrive le 2026-12-24)
  an KO  (linstantcle) #1837 non annulée, […motif ci-dessus…]

SORTIE=2
```

### 6.3 — L'expéditeur de chaque message client, lu dans la transcription

| Réservation | Marque | `From` composé | `Reply-To` composé | Objet |
|---|---|---|---|---|
| 1836 | sexcaperoom | `Sexcape Room <reservations@sexcaperoom.ch>` | `reservations@sexcaperoom.ch` | Votre réservation — Chambre Sexcape |
| 1837 | linstantcle | `L'Instant Clé <reservations@linstantcle.ch>` | `reservations@linstantcle.ch` | Votre réservation à L'Instant Clé |

**La transcription enregistre ce que WordPress compose, pas ce que le relais
Gmail envoie.** Elle est écrite au filtre `wp_mail`, avant PHPMailer et avant
le relais. Celui-ci remplace toute adresse d'expéditeur qui n'est pas un
alias d'envoi vérifié du compte. Or `reservations@<marque>` est un groupe
Google. La vérification 5 verte établit la composition, et elle seule. La
preuve de l'envoi reste la lecture des en-têtes bruts dans la boîte
fourre-tout, une fois l'envoi rouvert (plan de marche, « La première
reprise »).

**La question du §4 de l'ancien constat est tranchée.** Vik émet bien deux
messages par réservation : le message client, et la copie de
l'administrateur à `info@maisonnette-enchantee.ch`, avec le client en
`Reply-To`. L'ancien script n'affichait que la dernière ligne. La copie
garde l'expéditeur de Vik, et la phase 3 la laisse ainsi délibérément.

### 6.4 — Rien n'a bougé sur le serveur

Relu après les deux reprises : 1836 et 1837 toujours `confirmed`, la tâche 7
toujours à `last_exec` du 20.09 07:51:01 UTC avec 268 notifiées, et le
journal d'enveloppe toujours à 10 lignes. Au registre local, 1836 et 1837
sont passées à `a_nettoyer`.

---

## 7. Ce qui reste, et à qui

1. **Thomas : annuler à la main les réservations d'essai**, dans Bookings de
   l'administration de Vik, puis le consigner dans `journal-vik.md`. La
   liste : 1826 à 1832, 1834 à 1837, et 1833 une fois son origine confirmée
   (`constat-recette-vcm-inactif.md` §4). Vik Channel Manager étant absent,
   rien ne part vers les plateformes. Pour 1830, 1831, 1836 et 1837, qui
   sont payées, Vik peut proposer un remboursement. Il passerait par
   VikStripe en clés de test. Ensuite, `--nettoyer` relira les statuts
   `cancelled` et remettra le registre à jour.
2. **Décision : faut-il un jour exercer la vérification 7 ?** Et si oui, par
   quelle voie (§4.4). Jusque-là, elle reste `??`.
3. **Plan de marche (Cowork)** : B9i se ferme pour ses quatre défauts. La
   6c des reprises précédentes était un faux positif (§2). La phrase « les
   rappels de Vik sur les vraies réservations copiées partiront d'un coup
   au premier passage » est à préciser : un passage n'envoie que la fenêtre
   de son jour (§4.3), mais à de vrais clients. Le crochet
   `vikbooking_cron_door_access_control`, lui aussi en retard, n'a pas été
   examiné.
4. **Non vérifié** : l'état « Do not send » de WP Mail SMTP sur `staging13`.

---

## Journal des versions

- 25 septembre 2026 : première version, après les reprises de #1836 et #1837.
