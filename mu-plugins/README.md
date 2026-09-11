# mu-plugins

Nos mu-plugins, déployés dans `wp-content/mu-plugins/` du serveur. Ils se chargent avant les thèmes et les plugins, et ne peuvent pas être désactivés par erreur depuis l'administration.

Phase 1 du brief de réservation, faite : `lme-brands/`, qui porte le registre des marques, la résolution de marque depuis l'identifiant de chambre, la réécriture d'URL par hôte, l'écran de santé et la journalisation. Détails, procédures de vérification et tests dans `lme-brands/README.md`.

Convention : un dossier par mu-plugin, plus un fichier de chargement à la racine de `mu-plugins/` si l'hébergeur ne charge pas les sous-dossiers automatiquement, ce qui est le cas de WordPress par défaut.
