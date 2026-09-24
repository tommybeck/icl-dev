# Incident — la préproduction pousse sa disponibilité vers Airbnb, Booking.com et Expedia

24 septembre 2026. Rédaction Cowork. **À déplacer dans `icl-dev/docs/briefs/`, pas à recopier**, et à reporter au plan de marche.

## Ce qui est établi

Lecture seule sur `staging13.linstantcle.ch`, par le lecteur SQL d'EMCP, tables `sir_vikchannelmanager_notifications` et `sir_vikchannelmanager_notification_child`. Aucune clé lue.

**Vik Channel Manager est actif sur la préproduction**, avec la configuration copiée de la production. Chaque réservation d'essai créée depuis le 21 septembre a déclenché une `Availability Update RQ` vers e4jConnect, et **les trois plateformes ont répondu OK** : `e4j.OK.Airbnb.AR_RS`, `e4j.OK.Booking.AR_RS` avec ses identifiants de requête, `e4j.OK.Expedia.AR_RS` sur l'hôtel 102900157.

**Nuits possiblement fermées sur les plateformes par des réservations qui n'existent pas en production :**

| Chambre | Nuits | Réservations d'essai, statut sur la préproduction |
|---|---|---|
| À Huis Clos, 10 | 28 → 29 septembre 2026 | 1823, `confirmed` |
| Le Boudoir du Désir, 4 | 29 → 30 septembre, 30 septembre → 1er octobre | 1824, 1825, `confirmed` |
| Le Boudoir du Désir, 4 | 22 → 24 novembre | 1826 et 1828 `standby`, 1830 `confirmed` |
| Le Boudoir du Désir, 4 | 14 → 15 décembre | 1832, `standby` |
| L'Aparté, 2 | 22 → 24 novembre | 1827 et 1829 `standby`, 1831 `confirmed` |

La 1822, Boudoir du 21 au 22 septembre, est annulée et dans le passé.

## Ce qui n'est pas établi

Si ces nuits sont **encore** fermées aujourd'hui sur les plateformes. La production ignore ces réservations et n'a aucune raison de rouvrir ces nuits d'elle-même ; une mise à jour ultérieure depuis la production a pu en rouvrir certaines, mais rien ne le garantit. **Une réservation `standby` qui expire ne pousse pas nécessairement de réouverture.**

## Gestes, dans l'ordre

1. **Désactiver Vik Channel Manager sur `staging13`**, Plugins, avant toute autre recette. Désactiver MailPoet dans le même geste : il envoie par son propre service et ne passe ni par `wp_mail()`, ni par le garde-fou, ni par « Do not send ».
2. **Depuis la production**, pousser la disponibilité réelle des chambres 2, 4 et 10, **du 28 septembre au 15 décembre**, par les actions groupées de Vik Channel Manager. C'est ce qui rouvre ce qui a été fermé à tort.
3. **Vérifier sur les calendriers d'Airbnb et de Booking.com** : À Huis Clos le 28 septembre, le Boudoir les 29 et 30. Ce sont les plus proches, et À Huis Clos rouvre à la vente aujourd'hui.
4. Consigner dans `journal-vik.md`.

## La leçon

**La préproduction est une copie complète de la production, intégrations sortantes comprises, et rien ne les neutralisait.** Le garde-fou de messagerie ne couvre que `wp_mail()` ; ce qui écrit vers l'extérieur par un autre chemin passe.

Deux conséquences à porter au plan :

- **La recréation de la préproduction gagne une étape obligatoire** : désactiver tout ce qui écrit vers l'extérieur. Vik Channel Manager et MailPoet d'abord, puis ce qu'un inventaire trouvera, dont les deux greffons maison `lme-vik-contacts-api` et `lme-vik-ics`, **actifs et absents du dépôt**.
- **`recetter-moteur.sh` doit refuser de créer une réservation si Vik Channel Manager est actif sur la cible**, comme il refuse des clés Stripe réelles.

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-24 | Création. |
