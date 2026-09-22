# mu-plugins

Nos mu-plugins, déployés dans `wp-content/mu-plugins/` du serveur. Ils se chargent avant les thèmes et les plugins, et ne peuvent pas être désactivés par erreur depuis l'administration.

Phases 1, 2 et 3 du brief de réservation, faites : `lme-brands/`, qui porte le registre des marques, la résolution de marque depuis l'identifiant de chambre, la réécriture d'URL par hôte, le filtrage des chambres par marque (présentation puis garde de réservation), les e-mails par marque, l'écran de santé et la journalisation. Détails, procédures de vérification et tests dans `lme-brands/README.md`.

`lme-mail-guard/` : détourne tout e-mail sortant vers une adresse fourre-tout unique partout sauf en production stricte, pour recetter sans écrire à de vrais clients. Indépendant de `lme-brands` (couplage faible, optionnel, pour la journalisation seulement). Pas encore déployé. Détails et preuves dans `docs/briefs/constat-mail-guard.md`.

Convention : un dossier par mu-plugin, plus un fichier de chargement à la racine de `mu-plugins/` si l'hébergeur ne charge pas les sous-dossiers automatiquement, ce qui est le cas de WordPress par défaut.
