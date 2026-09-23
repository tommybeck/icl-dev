# Prérequis dormants — relevé du 23 septembre 2026

Rédaction Cowork. **À déplacer dans `icl-dev/docs/briefs/`, pas à recopier.**

Balayage des 32 documents de `docs/briefs/` à la recherche des prérequis, gestes de Thomas et conditions préalables qui vivent dans un brief ou un constat **sans être remontés au plan de marche**. Déclenché par la découverte fortuite d'un d'entre eux, l'exclusion de cache du chantier G.

**Vingt-trois points trouvés.** Huit sont bloquants pour une tâche nommée, dix sont à savoir, cinq sont mineurs.

**Ce que j'ai vérifié moi-même** est marqué ✓. Le reste est cité avec sa source et reste à confirmer par Code au moment de le traiter : le relevé a été fait par un agent de recherche, et je ne signe que ce que j'ai lu.

---

## Bloquants

| # | Quoi | Source | Rattacher à | Qui |
|---|---|---|---|---|
| 1 ✓ | **La commande de test 1822 est toujours en `standby`** et verrouille la chambre 4 sur la préproduction | `constat-fatal-page-paiement.md:179` | recette, vérifications 3 et 6 | Thomas, dans l'écran de Vik |
| 2 ✓ | **L'exemplaire de `tests/test-core.php` déjà déployé n'a pas été retiré.** L'exclusion vaut pour les déploiements futurs, `rsync` étant additif | `constat-script-deploiement.md:262` | B10 | Code en préproduction, Thomas en production |
| 3 ✓ | **La fiche de L'Indécent, page 6586, manque de la liste blanche tranchée.** Les pages 6586 et 6287 sont en statut WordPress `private` | `constat-recette-moteur.md:62`, décision de liste dans `brief-verrou-hote-reservation.md` | G2 | Thomas tranche, Code implémente |
| 4 ✓ | **Les pages des chambres 8, 9 et 10 sont `private`**, donc sans page publique | `constat-recette-moteur.md:61` | à ranger avec F3, avant la première vente | Thomas |
| 5 | **Le fragment `.htaccess`**, `X-Robots-Tag` conditionné à l'hôte et `robots.txt` propre, n'est ni écrit ni déposé. Le plan ne contient aucune occurrence de `.htaccess` | `brief-verrou-hote-reservation.md:123` | G2 produit, G3 dépose | Code puis Thomas |
| 6 | **La recette en six points du verrou d'hôte** n'est portée nulle part ; la vérification n°8 du plan n'en reprend qu'un | `brief-verrou-hote-reservation.md:70-79` | G3 | Code puis Thomas, Safari et iPhone |
| 7 ✓ | **`revue-constat-vikstripe-b4b.md` n'est pas dans le dépôt**, alors que le plan le cite trois fois et que H1 s'appuie sur son §6 | absent de `docs/briefs/` | H1 | Cowork, déplacer le fichier |
| 8 | **Cinq vérifications manuelles de la phase 4** ne figurent pas dans les huit de la recette, dont le retour d'annulation Stripe et la réservation à marques mêlées | `constat-phase-4-paiement.md:249-274` | vérification n°6 | Thomas puis Code |

**Le point 8 mérite un mot.** `cancel_url` et les réservations à marques mêlées sont **les deux seuls chemins où la métadonnée de marque peut être fausse**. Une vérification n°6 qui les ignore ne prouve pas grand-chose.

---

## À savoir

| # | Quoi | Source | Qui |
|---|---|---|---|
| 9 | Le script sort en erreur à son étape 6 pendant la passe L'Instant Clé : sa vérification d'apparence est câblée pour la passe Sexcape Room. Limite du script, pas du moteur | `constat-recette-moteur.md:96` | Code |
| 10 | L'écran de santé `lme-brands` demande une session d'administration : contrôle à l'œil, et aucune des huit vérifications ne le nomme | `constat-script-deploiement.md:314` | Thomas |
| 11 | Le contrôle de fuite de marque n'est pas branché sur le chemin des rappels. Une ligne, volontairement pas écrite, à poser le jour où F sera fait | `constat-b5-rappels.md:166` | Code, après F |
| 12 | Avant l'en-tête et le pied de page du tunnel : vérifier qu'EMCP Themer ne porte aucun gabarit, et arbitrer entre trois systèmes candidats, Elementor Pro compris | `brief-habillage-tunnel.md:195` | Cowork |
| 13 | L'îlot « cadre du moteur » de la page d'accueil de sexcaperoom.ch : bouton vers le tunnel, ou cadre intégré qui rouvre la question des cookies tiers | `brief-habillage-tunnel.md:225` | Thomas tranche |
| 14 | `brief-verification-theme-sexcaperoom.md` n'a jamais été exécuté, et son livrable manque. Les six défauts du 9 septembre ne sont pas rebouclés, dont les polices qui conditionnent D3 | brief en entier | Cowork |
| 15 | Redirections 301 des anciennes URL vers sexcaperoom.ch, et `Redirection depuis` dans Airtable | `sexcape-room-reservation.md:159` | Cowork puis Thomas |
| 16 | TranslatePress sur un second domaine : licence et structure d'URL à vérifier **avant** de promettre l'anglais et l'allemand, que G2 ouvre déjà | `sexcape-room-reservation.md:160` | Thomas |
| 17 | Le rendez-vous annuel de duplication des saisons, prévu « autour de septembre », donc maintenant | `convention-tarifs-annuelle.md:24` | Code puis Cowork |
| 18 | Le patron d'erreur de champ et `.ast-container` restent supposés. La recette d'habillage compte six points quand D3 n'en porte qu'un, dont la page d'erreur de paiement | `constat-habillage-tunnel.md:80-130` | Cowork puis Code |

---

## Mineurs

| # | Quoi | Source |
|---|---|---|
| 19 | L'adresse fourre-tout en production : le plan la décrit comme souhaitable, le geste n'est pas au chapitre 4 |`constat-mail-guard.md:224` |
| 20 | `FUNCTIONS_BASELINE_SIZE` se met à jour à la main. Thomas verra un refus du script sans en connaître la cause | `constat-script-deploiement.md:328` |
| 21 | Donnée personnelle dans la préproduction : `X-Original-To` porte l'adresse réelle du client, base copiée de la production | `brief-redirection-emails-hors-production.md:68` |
| 22 | Le journal d'accès qui devait clore le point NitroPack, lisible depuis le 18 septembre. **La fenêtre de rétention se referme** | `constat-incident-1818.md:438` |
| 23 | `journal-vik.md` porte encore « Thomas seul y écrit », contredit par la décision du 19 ; et une ligne orpheline de `calendars_xref` n'y est pas | `journal-vik.md`, `brief-correctif-phase-2-filtrage.md:108` |

---

## Ce que ce relevé apprend sur la méthode

**Un prérequis écrit dans un brief n'existe pas.** Vingt-trois points attendaient dans des documents que personne ne relit entre deux tâches, dont huit bloquent une tâche nommée. Le plan est la seule chose qu'on ouvre à chaque session : ce qui n'y est pas ne sera pas fait.

**Corollaire à porter au plan** : un brief ou un constat qui pose un prérequis le remonte au plan **dans la même passe**, faute de quoi il n'a rien posé du tout. Cela vaut pour Code comme pour Cowork.

**Et une observation sur celui-ci** : `constat-recette-moteur.md` du 22 septembre n'a pas été revu par Cowork. Une recette a été menée et sa lecture n'a jamais eu lieu.

---

## Journal des modifications

| Version | Date | Modification |
|---|---|---|
| 1.0 | 2026-09-23 | Création. Balayage des 32 documents de `docs/briefs/`, vingt-trois prérequis dormants, dont huit bloquants. |
