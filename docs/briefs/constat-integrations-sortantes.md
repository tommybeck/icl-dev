# Constat : ce que la préproduction écrit vers l'extérieur

24 septembre 2026. Rédigé par Claude Code, en réponse à
`docs/briefs/incident-preproduction-vers-plateformes-2026-09-24.md`.

Trois livrables :

1. l'inventaire de tout ce qui, sur la préproduction, écrit vers l'extérieur
   par un autre chemin que `wp_mail()` (§1 et §2) ;
2. `recetter-moteur.sh` refuse désormais toute création **et toute annulation**
   de réservation quand Vik Channel Manager est actif sur la cible (§3) ;
3. `lme-mail-guard` reprend la main sur `phpmailer_init`, en dernier (§4).

**Ce qui n'a pas été fait.** Rien n'a été déployé, ni en préproduction ni en
production. Aucune ligne n'a été écrite en base. Aucune clé n'a été lue. Toutes
les lectures sont passées par SSH (`sg-linstantcle`), sur `staging13`, avec
`wp-cli`. Quatre sondes ont été lancées par `wp eval-file`, depuis un fichier
temporaire déposé dans `/tmp` du serveur puis supprimé : ce sont les seules
écritures de fichier faites sur le serveur. Les options ont été lues **par clé
nommée**, et toute valeur possiblement secrète a été relevée comme
« présente » ou « absente », jamais affichée. Le code des deux greffons maison
a été lu à travers un masque qui remplace toute chaîne de plus de 24 caractères.
Dans les journaux d'accès, la chaîne de requête, qui porte les clés en clair,
a été coupée avant affichage.

---

## 0. Les prémisses, vérifiées contre leur source

| Prémisse | Source | Constat du 24 septembre 2026 |
|---|---|---|
| La préproduction est `staging10.linstantcle.ch` | `CLAUDE.md` | **Périmé.** La préproduction est `staging13.linstantcle.ch`, comme dans tous les constats depuis le 22 septembre. Base `dbzhntllr3st06`, distincte de la production `dbvkhvlostfyua`, même préfixe `sir_`. `CLAUDE.md` est à corriger par Thomas. |
| Vik Channel Manager est actif sur la préproduction | incident | **Vrai au moment de l'incident, faux aujourd'hui.** `wp plugin list` : `vikchannelmanager` 1.9.26 `inactive`. Dernière notification VCM à 11:11:20 UTC, six secondes après la réservation 1832 (11:11:14). On compte 16 notifications depuis le 21 septembre pour 11 réservations d'essai (1822 à 1832). Le contenu des réponses (`e4j.OK.…`) n'a pas été relu ici. |
| MailPoet est à désactiver | incident | **Fait.** `mailpoet` et `mailpoet-premium` 5.39.0 sont `inactive`. Son chemin d'envoi propre n'a pas été relu dans son code (greffon inactif). |
| `lme-vik-contacts-api` et `lme-vik-ics` sont actifs et absents du dépôt | incident | **Vrai.** Versions 1.2.0 et 1.2.1, `active`, un fichier PHP chacun. Leur md5 est identique en production et en préproduction. Ni l'un ni l'autre n'est dans ce dépôt. |
| Le garde-fou ne couvre que `wp_mail()` | incident, `constat-mail-guard.md` | **Vrai jusqu'à ce constat.** Crochets relevés sur `staging13` : `wp_mail` et `pre_wp_mail` à `PHP_INT_MAX`, rien sur `phpmailer_init`. `wp_mail()` est bien celle du cœur (`wp-includes/pluggable.php:189`), aucun greffon ne la redéfinit. |
| « Do not send » | incident | C'est le réglage de **WP Mail SMTP Pro** : `general.do_not_send = true` sur `staging13`. |
| `lme-mail-guard` n'est « pas encore déployé » | `mu-plugins/README.md` | **Périmé.** Il est présent dans `wp-content/mu-plugins/` de `staging13` (dossier daté du 23 septembre) et actif (`must-use`). |
| `plan-de-marche.md` est « à lire en premier » | `CLAUDE.md` | **Le fichier est vide.** Le commit local `bec2ac9` (« Nouveau plan de marche avant exécution + incident-… ») retire ses 513 lignes et n'ajoute pas le fichier d'incident que son message annonce. Ce commit n'est pas poussé. La version précédente est dans `14f137a`. Rien n'a été restauré : c'est à Thomas de trancher. |

Piège constaté en chemin : **TranslatePress réécrit la sortie de `wp eval`**,
même sur une sonde sans rapport avec le site. « vide » est devenu
« à renseigner », et « serrures » est devenu « verrous ». Toutes les sondes
retenues ici ont donc tourné avec `--skip-plugins`, ou au moins avec
TranslatePress écarté. C'est le même piège que celui qui a imposé `bin2hex()`
dans `recetter-moteur.sh`.

---

## 1. Les deux greffons maison : ils répondent, ils n'envoient rien

Les deux fichiers ont été lus en entier. Aucun ne fait d'appel sortant : pas
de `wp_remote_*`, pas de `curl`, pas de `wp_mail`, pas de tâche planifiée.
Ce sont des **points d'accès passifs**. Le risque vient de qui les interroge,
pas de ce qu'ils émettent.

### `lme-vik-contacts-api` 1.2.0, export des clients Vik vers Google Contacts

| | |
|---|---|
| Ce qu'il expose | `GET /wp-json/lme/v1/customers` : les clients de Vik, avec e-mail, prénom, nom, téléphone (réglage `expose_phone`), liste des réservations, `personKey`, `googleResource`. `POST /wp-json/lme/v1/link` : lie un `personKey` à une fiche Google Contacts. |
| Authentification | Clé d'API, en paramètre `key` ou en en-tête `X-API-Key`, stockée dans une option. L'option a été copiée de la production, donc **la clé de production ouvre aussi la préproduction**. |
| Qui l'appelle | Un consommateur hors dépôt (Make ou script) qui lit ce point d'accès et écrit dans Google Contacts. **Il envoie, lui.** S'il était pointé sur la préproduction, les clients d'essai (`RECETTE AUTOMATISEE`, `@recette-automatisee.icl-dev.invalid`) entreraient dans Google Contacts. |
| Constat | Aucun appel à `/lme/v1/` dans les journaux d'accès de `staging13` du 22 au 24 septembre (9 630 lignes). |
| Particularités | Le paramètre `status=*` passe outre la liste des statuts permis et renvoie aussi les `standby` et `cancelled`. Un `GET` **écrit en base** : il insère une ligne dans `sir_lme_vik_contacts_map` pour chaque e-mail inconnu. |

### `lme-vik-ics` 1.2.1, flux iCal par chambre

| | |
|---|---|
| Ce qu'il expose | `/?lme_ics=1&id_room=N&key=…`, `/wp-json/lme/v1/ics`, une redirection `webcal://` (`?lme_ics_webcal`) et un diagnostic (`?lme_ics_diag`) qui renvoie en JSON une commande et ses coordonnées client. |
| Authentification | Un secret global ou un secret par chambre, copiés de la production. Limitation à 20 échecs en 10 minutes par adresse IP. |
| Qui l'appelle | Les plateformes qui importent ce calendrier. En production, le flux est servi sous `api.linstantcle.ch`, que le mu-plugin `api-host.php` limite à `lme_ics` et à l'API de VikRestaurants. **Une plateforme abonnée à une URL de préproduction fermerait les nuits des réservations d'essai `confirmed`**, par le même mécanisme que l'incident mais par une autre porte. |
| Constat | Aucun appel à `lme_ics` dans les journaux d'accès de `staging13` du 22 au 24 septembre. |
| Particularité | Le diagnostic se désactive par un réglage `disable_diagnostics` que l'écran de réglages ne propose pas. Il est donc toujours actif, avec la même clé que le flux. |

**Conséquence :** ni l'un ni l'autre n'est à désactiver pour empêcher une
écriture sortante. Pour que la préproduction ne serve pas des données d'essai
à un consommateur de production, il faut **régénérer leurs clés sur la
préproduction** (bouton *Generate* de chaque écran de réglages, sous
**Settings → LME Vik Contacts API** et **Settings → LME Vik ICS**). C'est un
geste de Thomas.

---

## 2. Les autres chemins sortants actifs sur `staging13`

L'état est lu le 24 septembre, vers 13 h UTC. Sont écartés les greffons sans
chemin sortant métier (`duplicate-page`, `redirection`,
`temporary-login-without-password`, `header-footer-elementor`,
`ultimate-elementor`, `split-test-for-elementor`, `loco-translate`).

| Chemin | Ce qu'il envoie, où | Déclencheur | État constaté | Gravité |
|---|---|---|---|---|
| **Vik Channel Manager** | Disponibilités vers e4jConnect, puis Airbnb, Booking.com et Expedia. Messages aux voyageurs (répondeur automatique). | Toute création ou annulation de réservation. Tâches WP-Cron. | `inactive`. **Ses tâches restent planifiées** : `vikchannelmanager_cron_schedules_retry`, `_pending_locks`, `_messaging_autoresponder` et `_chat_async_processor`, toutes en retard. **Dix verrous en attente** dans `sir_vikchannelmanager_pendinglocks`, pour les réservations 1822 à 1832, tous expirés et jamais traités. File `rqschedules` vide. | Critique si réactivé : tout part au premier passage du cron. |
| **WooCommerce Payments** | Paiements réels, sur le compte de production. | Une commande WooCommerce payée sur la préproduction. | `enabled = yes`, **`test_mode = no`** : mode réel. Aucun produit n'a été examiné. | **Critique**, au même titre que des clés Stripe réelles. |
| **Jetpack Sync** (embarqué par WooCommerce Payments) | Données du site vers WordPress.com, sous l'identifiant de blog de la production. | `jetpack_sync_cron`, `jetpack_sync_full_cron`. | Identifiant Jetpack présent. La protection de Jetpack contre les copies (détection de changement d'URL) n'a pas été vérifiée. | Moyenne. |
| **WP Mail SMTP Pro** | E-mails par **l'API Gmail** (`mailer = gmail`, OAuth copié de la production). | `wp_mail()`. | `do_not_send = true`, aucune connexion de secours, pas de routage intelligent, pas de file d'attente, aucune alerte (Slack, webhook, SMS) active. Passe par `wp_mail()`, donc par le garde-fou et, depuis ce constat, par `phpmailer_init` (§4). | Couverte. |
| **PayPal** (`pymntpl-paypal-woocommerce`) | Paiements. | Une commande WooCommerce. | Actif. Mode non relu. | À vérifier par Thomas. |
| **VikStripe** | Paiements Stripe. | Paiement d'une réservation Vik. | Vérifié par `recetter-moteur.sh` à chaque passe (préfixe `sk_test_`). Pas relu ici. | Couverte par la recette. |
| **TranslatePress Business** | Le texte des pages vers **DeepL**, avec la clé de production. | L'affichage d'une chaîne non encore traduite. | `machine-translation = yes`, moteur `deepl`. | Faible : coût, pas de donnée métier. |
| **Google Site Kit** | Visites vers la propriété GA4 de production (depuis le navigateur). Rapports par e-mail. | Toute page vue par un navigateur. Tâche `googlesitekit_email_reporting_*`. | Identifiant de mesure GA4 présent. `blog_public = 0`. Les rapports passent par `wp_mail()`. | Faible : pollue les statistiques. |
| **Vik Booking**, SMS | SMS aux clients. | Réservation, rappel. | `smsapi` vide, `smsautosend = 0`. | Aucun. |
| **Vik Booking**, serrures connectées | Codes d'accès vers une serrure. | `vikbooking_cron_door_access_control`. | Table `sir_vikbooking_door_access_integrations` : **0 ligne**. | Aucun aujourd'hui. |
| **Vik Booking**, paiements échelonnés | Débits programmés. | `vikbooking_cron_payments_scheduled`. | `sir_vikbooking_payschedules` : 0 échéance en attente. | Aucun aujourd'hui. |
| **Vik Booking**, rappels | E-mails. | Tâches Vik : seule la n° 7 « Check-in info » (`email_reminder`) est publiée. | Passe par `wp_mail()`. | Couverte. |
| **VikRestaurants** | SMS. | Commande. | `smsapi` vide. Son API `orderslist` est entrante. | Aucun. |
| **Formulaires Elementor** | Actions après envoi (e-mail, webhook, Mailchimp…). | L'envoi d'un formulaire. | 98 enregistrements `_elementor_data` contiennent un formulaire (révisions comprises), aucun ne porte de `submit_actions` personnalisé. Tous sont donc sur l'action par défaut, l'e-mail, qui passe par `wp_mail()`. Aucun webhook. | Couverte. |
| **Webhooks WooCommerce** | Événements vers une URL. | Commande, produit… | `sir_wc_webhooks` : 0 ligne. | Aucun. |
| **Facebook Pixel** (`official-facebook-pixel`) | Événements vers Meta, depuis le navigateur ou le serveur (API Conversions). | Page vue, achat. | Option `facebook_config` absente. **Non concluant** : le nom de l'option n'a pas été vérifié dans le code du greffon. | À vérifier. |
| **NitroPack** | Purges de cache vers l'API NitroPack. | Modification de contenu. | Option `nitropack-siteId` absente. **Non concluant**, même réserve. | À vérifier. |
| Télémétrie et licences | Données d'usage et contrôles de licence vers les éditeurs : Wordfence, Elementor (`elementor/tracker/send_event`), WPForms (`wpforms_send_usage_data`), WP Mail SMTP (`wp_mail_smtp_send_usage_data`), UserFeedback, OptinMonster, Rank Math (`rank_math/analytics/data_fetch`), SiteGround AI Studio, EMCP. | WP-Cron, Action Scheduler. | Actifs. Leur code n'a pas été lu. | Faible : aucune donnée de réservation connue. |

### WP-Cron ne tourne pas sur la préproduction, et c'est une menace différée

`DISABLE_WP_CRON` est absent. Pourtant, **toutes** les tâches listées par
`wp cron event list` sont en retard (« now »), y compris les tâches horaires.
WP-Cron ne s'est donc pas exécuté depuis un moment sur `staging13`. Cela
explique les dix verrous VCM jamais traités, et corrobore la phrase de
l'incident : « une réservation `standby` qui expire ne pousse pas
nécessairement de réouverture ». La cause n'a pas été établie : boucle HTTP
bloquée ou absence de visites.

La conséquence compte pour la suite : **le premier passage du cron déclenche
tout d'un coup.** Ce passage peut venir d'une visite, de
`wp cron event run --due-now`, ou de la vérification 7 de la recette, qui ne
lance qu'une tâche nommée. Il déclencherait aussi les tâches de VCM s'il était
réactivé, par exemple par une recréation de la préproduction à partir de la
production.

### Les données laissées par l'incident, en attente d'un geste

Hors du périmètre de ce constat et rappelées pour mémoire : les dix verrous
VCM et les réservations d'essai `confirmed` 1823, 1824, 1825, 1830 et 1831
restent sur la préproduction. Tant que VCM y est inactif, elles ne poussent
rien. Si VCM y était réactivé, la tâche `pending_locks` repartirait.

---

## 3. `recetter-moteur.sh` : aucune réservation créée ni annulée si VCM est actif

### Ce qui a changé

- **Nouveau mode distant `vcm-status`.** Il lit `wp plugin list
  --fields=name,status --skip-plugins --skip-themes`. L'état vient de l'option
  `active_plugins`, sans charger aucun greffon, ce qui écarte aussi
  TranslatePress. Le mode refuse une sortie dont l'en-tête n'est pas
  `name,status`. Il rend `VCM_STATUS=active|inactive|absent|…`.
- **Nouveau préalable** dans `run_preconditions`, à côté du contrôle des clés
  Stripe : « Vik Channel Manager inactif ». Seuls `inactive` et `absent`
  passent. `active`, `active-network`, un statut vide ou illisible font passer
  le préalable au rouge, et le script s'arrête avant toute passe. Ce refus est
  global, **même sans `--appliquer`**, parce que la vérification 3 crée une
  réservation quand la garde de `lme-brands` la laisse passer.
- **`--nettoyer` et `--reprise`** ne passent pas par les préalables. Ils
  appellent désormais `exiger_vcm_inactif`, qui arrête le script dans les
  mêmes cas. Une annulation pousse elle aussi une disponibilité vers les
  plateformes. Or cette disponibilité est calculée sur la base de
  préproduction, qui ignore les réservations de production faites depuis la
  copie : elle pourrait **rouvrir** une nuit vendue en production.
- **Dernier rempart** dans `vik_creer_reservation()` et
  `vik_annuler_reservation()` (`recetter-moteur-vik.sh`) : les deux refusent
  de partir si `VCM_INACTIF_VERIFIE` ne vaut pas 1 pendant l'exécution. Un
  futur appelant qui oublierait le préalable est donc arrêté quand même.
- L'aide du script (`--help`) le dit.

### Vérification

- Mode distant lancé pour de vrai sur `staging13`, en lecture seule :
  `VCM_STATUS=inactive`, code de sortie 0.
- Décision locale testée avec un `remote_call` simulé : `inactive` et `absent`
  passent. `active`, `active-network`, un statut vide et un échec SSH arrêtent
  le script avec un message explicite.
- `bash -n` propre sur les deux scripts.
- **La recette complète n'a pas été relancée** : elle pose le levier dans
  `wp-config.php` et peut créer des réservations. C'est à faire par Thomas au
  prochain passage.

---

## 4. `lme-mail-guard` 1.1.0 : dernier mot sur `phpmailer_init`

### Pourquoi ce crochet, et pourquoi en dernier

Relu dans `wp-includes/pluggable.php` de `staging13` (WordPress 6.9,
PHPMailer 7.1.1). `wp_mail()` vide les destinataires de l'objet (`:386`), les
reconstruit depuis `$atts`, puis appelle
`do_action_ref_array( 'phpmailer_init', … )` (`:622`) juste avant
`$phpmailer->send()` (`:628`, dans le `try`). Entre notre filtre `wp_mail` et
l'envoi, tout rappel posé sur `phpmailer_init` peut encore appeler
`addAddress()`, `addCC()` ou `addBCC()`. Sur `staging13`, 20 rappels y sont
posés, tous à la priorité 10 : `WPMailSMTP\Processor::phpmailer_init` et 19
gestionnaires d'e-mails WooCommerce.

### Ce qui a changé

- `includes/core.php`, fonctions pures :
  `lme_mail_guard_plan_final_recipients( $present, $catchall )` rend
  `keep`, soit la fourre-tout seule, soit rien si elle n'est pas configurée.
  Elle rend aussi `dropped`, les adresses présentes autres que la fourre-tout,
  comparées sans casse ni espaces. `lme_mail_guard_address_domains()` ne garde
  que les domaines, pour le journal.
- `includes/guard.php` : `lme_mail_guard_enforce_phpmailer_recipients()` agit
  **hors production uniquement**, par la même décision que le filtre `wp_mail`
  (`lme_mail_guard_current_should_redirect()`, `FORCE_EMAIL_REDIRECT`
  compris). Elle lit To, Cc et Bcc, appelle `clearAllRecipients()`, puis ne
  remet que la fourre-tout.
  - Si des adresses ont été ajoutées après le filtre, elle écrit un
    avertissement `recipients_added_after_filter`, avec leur nombre et leurs
    domaines, jamais les adresses.
  - Si la fourre-tout manque, elle retire tout et journalise l'erreur
    `catchall_not_configured`. PHPMailer refuse alors l'envoi, `wp_mail()`
    l'intercepte et rend `false`.
  - Si l'objet n'a pas les méthodes attendues, elle écrit l'erreur
    `phpmailer_unexpected`.
  - Si PHPMailer refuse la fourre-tout, elle écrit l'erreur
    `catchall_rejected` au lieu de laisser partir une exception. `wp_mail()`
    crée PHPMailer avec les exceptions actives et appelle `phpmailer_init`
    hors de son `try` (`pluggable.php:622`, relu) : une exception y serait
    fatale. En pratique, `wp_mail()` a déjà refusé une adresse invalide plus
    haut. Aucun chemin n'est silencieux.
- **Réellement en dernier.** À priorité égale, WordPress suit l'ordre
  d'enregistrement, et un mu-plugin s'enregistre avant tout greffon :
  `PHP_INT_MAX` seul laisserait passer après nous un greffon posé à la même
  priorité. Le rappel est donc enregistré au chargement, pour les envois
  antérieurs à `wp_loaded`, puis retiré et remis sur `wp_loaded`, pour
  repasser en queue.
- **Ce qui ne bouge pas.** `clearAllRecipients()` ne vide que `to`, `cc`,
  `bcc`, `all_recipients` et `RecipientsQueue` (relu dans PHPMailer 7.1.1).
  Ni `From`, ni `Sender`, ni `Reply-To` ne sont touchés, et la vérification 5
  de la recette reste valable. `X-Original-To` et la transcription
  d'enveloppe sont inchangés.

### Vérification

- `php mu-plugins/lme-mail-guard/tests/test-core.php` : **79 tests, 0 échec**.
  Il y en avait 70 au commit précédent : 9 sont ajoutés, aucun n'est modifié.
- Essai d'intégration hors dépôt, avec le vrai `plugin.php`, le vrai
  `WP_Hook` et le vrai PHPMailer copiés de `staging13`. Un faux greffon,
  enregistré **après** `lme-mail-guard` à la même priorité `PHP_INT_MAX`,
  ajoute un To et un Bcc :
  - préproduction, fourre-tout posée : `to = fourre-tout`, Cc et Bcc vides,
    `Reply-To` et `From` intacts, avertissement journalisé avec 2 adresses et
    leurs domaines ;
  - préproduction, fourre-tout refusée par PHPMailer : pas d'erreur fatale,
    aucun destinataire, erreur `catchall_rejected` journalisée ;
  - préproduction, fourre-tout absente : aucun destinataire, `preSend()`
    refuse (« You must provide at least one recipient email address »), erreur
    journalisée ;
  - production stricte : rien n'est touché, aucune ligne de journal.
- `php -l` propre sur tous les fichiers du greffon.

### Ce que ce crochet ne couvre pas

- **Ce qui ne passe pas par `wp_mail()`.** MailPoet par son propre service,
  tout greffon qui instancie son propre PHPMailer ou appelle une API d'envoi
  directement. Pour ceux-là, la seule parade est de désactiver le greffon sur
  la préproduction.
- **Une modification après `phpmailer_init`.** WP Mail SMTP remplace l'objet
  global par `WPMailSMTP\Pro\MailCatcherV6`, dont `send()` envoie par l'API
  Gmail à partir des destinataires de l'objet. Une recherche dans son code
  (`src/`, hors `vendor/`) ne trouve qu'un `addAddress()`, dans l'export EML
  du journal des e-mails, qui n'est pas un chemin d'envoi. Les crochets
  internes de sa méthode `send()` n'ont pas été relus un par un.
- Aucun déploiement : ce qui tourne sur `staging13` est encore la version
  1.0.0.

---

## 5. À ajouter à la recréation de la préproduction

Ces gestes reviennent à Thomas, dans les écrans natifs, et complètent l'étape
obligatoire que l'incident ajoute au plan :

1. **Plugins** : désactiver Vik Channel Manager et MailPoet (déjà fait sur
   `staging13`).
2. **WooCommerce → Settings → Payments → WooPayments** : passer en mode test,
   ou désactiver WooCommerce Payments. Relire le mode de PayPal.
3. **Settings → LME Vik Contacts API** et **Settings → LME Vik ICS** :
   régénérer les clés, pour qu'aucune clé de production n'ouvre la
   préproduction.
4. **TranslatePress → Settings → Automatic Translation** : désactiver.
5. Vérifier que WP Mail SMTP garde « Do not send » et que la constante
   `LME_MAIL_GUARD_CATCHALL_EMAIL` est posée.
6. Avant toute réactivation d'un greffon d'intégration, relancer
   `wp cron event list` : tout ce qui est en retard partira d'un coup.

---

## Fichiers modifiés

```
recetter-moteur.sh                            mode vcm-status, préalable, exiger_vcm_inactif, aide
recetter-moteur-vik.sh                        rempart dans vik_creer_reservation / vik_annuler_reservation
mu-plugins/lme-mail-guard.php                 version 1.1.0
mu-plugins/lme-mail-guard/lme-mail-guard.php  docblock
mu-plugins/lme-mail-guard/includes/core.php   plan_final_recipients, address_domains
mu-plugins/lme-mail-guard/includes/guard.php  phpmailer_init, en dernier
mu-plugins/lme-mail-guard/tests/test-core.php 9 tests
docs/briefs/constat-integrations-sortantes.md ce constat
```

## Journal des versions

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-24 | Création. Inventaire des chemins sortants de `staging13`. Les deux greffons maison, lus en entier, sont passifs. WooCommerce Payments est en mode réel. WP-Cron est à l'arrêt, avec des tâches VCM en attente. Refus de VCM actif dans `recetter-moteur.sh`, création et annulation. `lme-mail-guard` 1.1.0 sur `phpmailer_init`, en dernier. 79 tests, 0 échec. Rien de déployé, rien écrit en base, aucune clé lue. |
