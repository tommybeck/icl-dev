# icl-dev

Code maison des sites **linstantcle.ch** (L'Instant Clé) et **sexcaperoom.ch** (Sexcape Room).
Ce dépôt ne contient que du code écrit par nous. Ni WordPress, ni les plugins tiers, ni les médias.

---

## Stack

WordPress, Elementor Pro, thème enfant Astra, Vik Booking, TranslatePress, Loco Translate, MailPoet, WPForms, hébergement SiteGround, cache NitroPack.
Base de données : schéma `dbvkhvlostfyua`, préfixe de tables `sir_`, MySQL en UTC.
Préproduction : `staging10.linstantcle.ch`.
Back-office WordPress/Elementor de Thomas en **anglais** : donner les libellés de menu en anglais, jamais une traduction française du nom d'un écran.

---

## Règles absolues

1. **Ne jamais modifier les fichiers d'un plugin tiers**, Vik Booking en premier lieu. Tout passe par un mu-plugin, le thème enfant, ou un hook. Une mise à jour efface toute édition directe.
2. **Aucun secret dans un commit.** Ni `wp-config.php`, ni clé Stripe, ni identifiant de base, ni jeton d'API. Thomas les saisit lui-même sur le serveur. Le `.gitignore` en couvre les formes connues, ce qui ne dispense pas de vérifier avant de valider.
3. **Préproduction avant production, sauvegarde avant écriture.** Vérifier `environment: staging` avant toute écriture de contenu.
4. **Le cache est le premier suspect.** Devant un comportement inexpliqué en frontal, purger NitroPack puis le cache dynamique SiteGround, et retester sur Safari et iPhone avant de conclure à un bug.
5. **Rien de configurable n'est écrit en dur.** La correspondance chambre, marque, expérience et espace physique vit dans un fichier de configuration, jamais dans du code.
6. **Aucun échec silencieux.** Chaque chemin se termine par un succès explicite ou par une erreur journalisée qui alerte.
7. **Toujours penser au multilingue.** TranslatePress sur linstantcle.ch (anglais natif, français dans le dictionnaire), Elementor sur sexcaperoom.ch (français natif).

---

## Arborescence

```
icl-dev/
├── CLAUDE.md                 ce fichier
├── docs/briefs/              briefs de construction et journaux de chantier
├── mu-plugins/               nos mu-plugins, déployés dans wp-content/mu-plugins/
└── themes/astra-child/       le thème enfant Astra, récupéré du serveur par SFTP
```

Le contenu de `mu-plugins/` et de `themes/` se déploie aux emplacements de même nom sous `wp-content/` du serveur.

---

## Chantier en cours

**Réservation Sexcape Room sur le Vik Booking existant.**

- `docs/briefs/plan-de-marche.md` — qui fait quoi, dans quel ordre. **À lire en premier.**
- `docs/briefs/sexcape-room-reservation.md` — le brief de construction, à lire en entier avant d'écrire une ligne.
- `docs/briefs/constat-phase-0.md` — ce qui a été établi dans le code de Vik, preuves à l'appui.
- `docs/briefs/handoff-acces-mysql.md` — accès SSH et MySQL.
- `docs/briefs/brief-habillage-tunnel.md` — apparence Sexcape Room des pages du tunnel.
- `docs/briefs/revue-tarifs.md` et `docs/briefs/convention-tarifs-annuelle.md` — corrections tarifaires en cours et règle de portée annuelle.

**Aucune écriture en base, jamais.** Le compte MySQL est en lecture seule et le reste : c'est une décision, pas une limitation subie. Toute modification de tarif, de saison ou de restriction se fait par Thomas dans les écrans natifs de Vik. Code lit, vérifie et constate ; il n'écrit ni par SQL, ni par un plugin qui écrirait à sa place. Voir `convention-tarifs-annuelle.md`.

**Partage du travail.** Claude Code tient le dépôt : `mu-plugins/`, `themes/`, et les fichiers `docs/constat-*.md`. Cowork tient Airtable, Make, Elementor et les fichiers `docs/brief-*.md` et `docs/revue-*.md`. Un fichier, un auteur. Claude Code n'écrit jamais dans Airtable ni dans Make, ne déploie jamais en production, et ne modifie jamais `.local/`, qui est en lecture seule.
Journal des modifications faites dans l'administration de Vik : `docs/briefs/journal-vik.md`, à tenir à jour à chaque changement.

Décision cadre : un seul Vik Booking, sur linstantcle.ch, fait foi pour les deux marques. Pas de seconde instance, pas de synchronisation iCal entre marques.

---

## Les trois commandes Git à connaître

Dans le Terminal, depuis ce dossier.

```bash
git status                        # ce qui a changé
git add -A && git commit -m "…"   # enregistrer un état, avec un message clair
git push                          # envoyer sur GitHub
```

Pour revenir en arrière sur un fichier pas encore validé : `git restore <fichier>`.
Pour voir l'historique : `git log --oneline`.

Un commit par phase du brief. Un message qui dit ce que le changement fait, pas quel fichier il touche.
