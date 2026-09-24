# Constat — première reprise réelle, réservations d'essai #1830 et #1831

24 septembre 2026. Journal des deux exécutions de `--reprise` contre
`staging13.linstantcle.ch`, à l'intention de Cowork pour analyse. Suite du
chapitre 5 de `constat-recette-automatisee.md`, qui notait `--reprise`
« non exercé de bout en bout » : il l'est désormais, et il échoue en partie.

**Tout ce qui suit vient d'une exécution réelle**, sorties recopiées telles
quelles. Aucune correction n'a été faite au script entre les deux
exécutions ni depuis. Rien n'a été déployé, rien n'a été écrit en base par
SQL. Les `sid` des réservations sont volontairement omis de ce fichier : ils
ouvrent la page de confirmation, ce sont des jetons d'accès.

---

## 1. Commandes lancées

```bash
./recetter-moteur.sh --hote staging13.linstantcle.ch --reprise 1830
./recetter-moteur.sh --hote staging13.linstantcle.ch --reprise 1831
```

Les deux réservations figuraient au registre local
(`~/.icl-dev-recette/staging13.linstantcle.ch/reservations.tsv`) au statut
`en_attente_reprise`, créées le 24 septembre à 10:34 et 10:35 UTC :

| idorder | marque | chambre |
|---|---|---|
| 1830 | sexcaperoom | 4 (Le Boudoir du Désir) |
| 1831 | linstantcle | 2 (L'Aparté) |

---

## 2. Résultats

### 2.1 — #1830 (sexcaperoom)

```
== Reprise — réservation #1830 (sexcaperoom, chambre #4) ==
  OK   page de confirmation lisible (statut 200)
  OK   au moins une ligne de transcription d'enveloppe pour cet hôte
    dernière ligne : {"ts":"2026-09-24T10:43:58+00:00","to":"info@maisonnette-enchantee.ch","from":"L'Instant Clé <info@maisonnette-enchantee.ch>","sender":"","reply_to":"recette+1790246129@recette-automatisee.icl-dev.invalid","subject":"Votre réservation à L'Instant Clé #1831","host":"staging13.linstantcle.ch"}
    déclenchement de vikbooking_cron_email_reminder_8 (wp cron event run)
  KO   échec de vikbooking_cron_email_reminder_8 : Error: Invalid cron event 'vikbooking_cron_email_reminder_8'

  annulation de la réservation d'essai #1830
  annulation non confirmée pour idorder=1830 (code 403) — probablement encore 'standby' (carte de test non saisie), politique d'annulation refusée (délai minimal), ou jeton anti-CSRF requis et non transmis
  KO   #1830 non annulée automatiquement — relancer avec --nettoyer

== Rapport (reprise #1830) ==
  6c OK  (sexcaperoom) page de confirmation affichée après retour de paiement, sur staging13.linstantcle.ch
  5  OK  (sexcaperoom) transcription d'enveloppe présente — vérifier à l'œil From/Sender/Reply-To ci-dessus
  7  KO  (sexcaperoom) échec de la tâche planifiée
EXIT=0
```

### 2.2 — #1831 (linstantcle)

```
== Reprise — réservation #1831 (linstantcle, chambre #2) ==
  OK   page de confirmation lisible (statut 200)
  OK   au moins une ligne de transcription d'enveloppe pour cet hôte
    dernière ligne : (identique à celle de #1830, ci-dessus)
    déclenchement de vikbooking_cron_email_reminder_8 (wp cron event run)
  KO   échec de vikbooking_cron_email_reminder_8 : Error: Invalid cron event 'vikbooking_cron_email_reminder_8'

  annulation de la réservation d'essai #1831
  annulation non confirmée pour idorder=1831 (code 403) — […même message…]
  KO   #1831 non annulée automatiquement — relancer avec --nettoyer

== Rapport (reprise #1831) ==
  6c OK  (linstantcle) page de confirmation affichée après retour de paiement, sur staging13.linstantcle.ch
  5  OK  (linstantcle) transcription d'enveloppe présente — vérifier à l'œil From/Sender/Reply-To ci-dessus
  7  KO  (linstantcle) échec de la tâche planifiée
EXIT=0
```

### 2.3 — Lecture vérification par vérification

| Vérif. | #1830 | #1831 | Ce que le résultat établit réellement |
|---|---|---|---|
| 6c page de confirmation | OK | OK | HTTP 200 et aucun mot `standby`/`en attente`/`pending` dans la page. **N'établit pas que le paiement Stripe a abouti.** |
| 5 transcription d'enveloppe | OK **faux positif** | OK | La ligne affichée porte l'objet `#1831`. Pour #1830, aucune ligne propre n'a été montrée : rien n'établit qu'un message #1830 a été transcrit. |
| 7 rappel avant séjour | KO | KO | Le crochet `vikbooking_cron_email_reminder_8` n'existe pas dans WP-Cron sur staging13. |
| Annulation | KO (403) | KO (403) | Les deux réservations restent actives dans Vik. |

---

## 3. Défauts constatés

### 3.1 — Le script sort en 0 malgré des vérifications au rouge

`reprendre_reservation()` (`recetter-moteur-vik.sh`) ne rend jamais autre
chose que 0 une fois la page de confirmation lue ; `recetter-moteur.sh` fait
`exit $?`. Deux KO et une annulation manquée donnent `EXIT=0`. Contraire à la
convention du script (« sortie en erreur au premier contrôle rouge ») et à la
règle absolue n°6 (aucun échec silencieux). Un appelant automatisé
conclurait au succès.

### 3.2 — La vérification 5 n'est pas liée à la réservation reprise

Elle relit le journal d'enveloppe depuis l'offset 0 et accepte **n'importe
quelle** ligne portant `"host":"staging13.linstantcle.ch"`. Toute réservation
antérieure sur cet hôte la fait passer. Prouvé par #1830, déclarée OK sur la
ligne de #1831. Correctif envisagé : filtrer sur l'identifiant de la
réservation (objet `#<idorder>`) ou sur le `reply_to` propre à la
réservation d'essai (`recette+<ts>@…`), et échouer s'il n'y a aucune ligne.

### 3.3 — `--reprise` saute les préalables, dont le contrôle `environment: staging`

Le chemin `--reprise` appelle `reprendre_reservation` sans
`run_preconditions`. Il déclenche une tâche planifiée de Vik et une
annulation sans avoir vérifié `wp_get_environment_type() == 'staging'` sur
l'hôte visé. Ici l'hôte était le bon (même hôte que les exécutions
précédentes, consignées au registre), mais rien ne l'a vérifié. Même
remarque pour `--nettoyer`. Contraire à la règle absolue n°3.

### 3.4 — Vérification 7 : le nom du crochet WP-Cron est déduit, et faux

Le script lit l'identifiant de la tâche `email_reminder` dans
`sir_vikbooking_cronjobs` (ici `8`) et en déduit
`vikbooking_cron_email_reminder_8`. WP-CLI répond
`Invalid cron event`. Deux hypothèses, non tranchées : Vik enregistre ses
tâches sous un autre nom de crochet, ou il ne les inscrit pas du tout dans
WP-Cron sur cette préproduction (tâche désactivée, planification faite
ailleurs, ou déclenchement propre à Vik). **À établir dans le code de Vik
avant toute correction du script** — aucune hypothèse n'a été vérifiée.

### 3.5 — Annulation refusée en 403, pour la troisième fois

**Correction du 24 septembre, après relecture de `sir_vikbooking_orders` :
#1830 et #1831 sont `confirmed`.** Le paiement de test a donc abouti, et
l'hypothèse « encore `standby` » est écartée pour ces deux réservations.
Le `403` a une autre cause, qui n'est pas encore établie. Le paragraphe
ci-dessous est conservé tel qu'écrit. Voir `constat-recette-automatisee.md`,
3.2 bis.

Même code que lors du test de `--nettoyer` sur #1826 et #1827 (chapitre 5 de
`constat-recette-automatisee.md`). Le message du script liste trois causes
possibles (réservation encore `standby`, politique d'annulation de Vik,
jeton anti-CSRF manquant) sans en établir aucune. Le fait qu'une page de
confirmation « sans standby » donne quand même un 403 affaiblit la première
hypothèse, sans l'exclure (voir 2.3 : la vérification 6c ne prouve pas le
paiement). Tant que ce point n'est pas résolu, **aucune réservation d'essai
ne se nettoie automatiquement**.

---

## 4. Point à vérifier à l'œil : l'enveloppe du message #1831

| Champ | Valeur |
|---|---|
| `to` | `info@maisonnette-enchantee.ch` |
| `from` | `L'Instant Clé <info@maisonnette-enchantee.ch>` |
| `sender` | vide |
| `reply_to` | adresse de la réservation d'essai (`recette+…@recette-automatisee.icl-dev.invalid`) |
| `subject` | `Votre réservation à L'Instant Clé #1831` |

L'objet est celui du message client, mais le destinataire est la boîte
d'administration (`adminemail` de Vik, `constat-phase-0.md`) et l'adresse du
client est en `reply_to`. Deux lectures possibles : détournement hors
production voulu par `lme-mail-guard`, ou copie administrateur de Vik. Non
tranché ; à confronter à `constat-mail-guard.md`. Un seul message transcrit
pour #1831 alors que Vik en émet d'ordinaire deux (client et
administrateur) mérite aussi d'être expliqué.

---

## 5. État laissé sur staging13

Registre local après les deux exécutions :

| idorder | marque | chambre | statut registre | créée (UTC) |
|---|---|---|---|---|
| 1826 | sexcaperoom | 4 | `standby_sans_paiement` | 2026-09-23 20:04 |
| 1827 | linstantcle | 2 | `standby_sans_paiement` | 2026-09-23 20:05 |
| 1828 | sexcaperoom | 4 | `en_attente_reprise` | 2026-09-24 09:36 |
| 1829 | linstantcle | 2 | `en_attente_reprise` | 2026-09-24 09:37 |
| 1830 | sexcaperoom | 4 | `a_nettoyer` | 2026-09-24 10:34 |
| 1831 | linstantcle | 2 | `a_nettoyer` | 2026-09-24 10:35 |

**Six réservations d'essai restent ouvertes dans Vik sur staging13.** Toutes
portent le marquage `RECETTE / AUTOMATISEE - NE PAS TRAITER`. Reprendre #1828
et #1829 tel quel reproduirait les échecs 3.4 et 3.5.

---

## 6. Suites proposées, dans l'ordre

1. Établir dans le code de Vik pourquoi `task=docancelbooking` répond 403 (3.5).
2. Établir comment Vik planifie `email_reminder` et sous quel crochet (3.4).
3. Corriger le script : code de sortie (3.1), vérification 5 liée à
   l'idorder (3.2), préalables en `--reprise` et `--nettoyer` (3.3).
4. Relancer `--nettoyer` ; à défaut, annulation manuelle par Thomas dans
   l'administration de Vik (Bookings), consignée dans `journal-vik.md`.

Rien de cette liste n'a été entrepris.

---

## Journal des versions

- 24 septembre 2026 — première version, après les reprises de #1830 et #1831.
- 24 septembre 2026 — 3.5 : #1830 et #1831 sont `confirmed`, l'hypothèse `standby` est écartée pour elles.
