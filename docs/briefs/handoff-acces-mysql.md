# Handoff — accès SSH et MySQL, chantier réservation Sexcape Room

9 septembre 2026. À lire avant de relancer une session sur l'accès base de données.

---

## Ce qui est en place et fonctionne

Accès SSH aux deux sites, et accès MySQL en lecture sur linstantcle.ch, testés et validés.

### Deux sites, deux serveurs distincts

Même compte client SiteGround, mais chaque site a sa propre installation, son propre serveur physique, son propre utilisateur SSH et sa propre gestion de clés.

| Site | Serveur | Port SSH |
|---|---|---|
| linstantcle.ch | `gfram1004.siteground.biz` | 18765 |
| sexcaperoom.ch | `gvam1277.siteground.biz` | 18765 |

La base Vik Booking (`dbvkhvlostfyua`) vit sur l'installation **linstantcle.ch**. C'est le seul accès dont ce chantier a besoin pour l'instant ; l'accès sexcaperoom.ch attend la phase 6 (thème et contenu de ce site).

### Clé SSH

Une seule paire pour les deux sites : `~/.ssh/icl_ed25519` (privée, jamais versionnée) et `~/.ssh/icl_ed25519.pub` (ajoutée dans Site Tools, SSH Keys Manager, sur les deux comptes).

### Raccourcis SSH

Dans `~/.ssh/config` sur le Mac de Thomas :

```
Host sg-linstantcle
    HostName gfram1004.siteground.biz
    Port 18765
    User u2523-3ceopjluw77n
    IdentityFile ~/.ssh/icl_ed25519

Host sg-sexcaperoom
    HostName gvam1277.siteground.biz
    Port 18765
    User u1892-e2lfb6tabn8q
    IdentityFile ~/.ssh/icl_ed25519
```

### Le tunnel SSH classique ne fonctionne pas ici

`ssh -N -L 3306:localhost:3306 sg-linstantcle` échoue avec `channel 2: open failed: administratively prohibited`. SiteGround bloque le transfert de port TCP sur ce compte SSH. **Ne pas retenter cette voie**, ni en dépannage ni en automatisation : elle est fermée côté serveur, pas mal configurée côté client.

### Ce qui fonctionne : exécuter MySQL à distance, via SSH, avec mot de passe interactif

```bash
ssh -t sg-linstantcle "mysql -u 'NOM_UTILISATEUR' -p dbvkhvlostfyua"
```

`-t` force une invite interactive à travers SSH ; `-p` sans valeur collée fait que MySQL demande le mot de passe lui-même, à la frappe, sans jamais transiter par un interpréteur de commande. **C'est volontaire et nécessaire** : le mot de passe contient des caractères spéciaux (dont `@`), et une commande à une ligne du type `mysql -u X -p'MOTDEPASSE' ...` le fait passer par deux interprétations de shell successives (locale puis distante), ce qui l'altère et produit `ERROR 1045, Access denied`. C'est l'erreur rencontrée et résolue aujourd'hui.

Pour une requête ponctuelle non interactive (ce que fera Claude Code), le même principe s'applique : ne jamais coller le mot de passe en clair dans une commande composée. Le mode d'exécution non interactif reste à définir, voir « À faire » plus bas.

### Utilisateur MySQL

`SELECT` seul, restreint à la base `dbvkhvlostfyua` (idéalement aux tables `sir_vikbooking_%`), créé dans Site Tools. Identifiants dans `.local/db.env` (exclu de Git), champs `DB_USER` et `DB_PASSWORD`. Ce fichier n'est lu par aucun script pour l'instant : c'est un aide-mémoire, pas une automatisation.

---

## À faire ensuite

1. **Mode d'exécution non interactif, tranché.** Créer sur le serveur linstantcle.ch un fichier `~/.my.cnf`, en **mode 600**, contenant :

   ```ini
   [client]
   user=NOM_UTILISATEUR
   password=MOT_DE_PASSE
   ```

   Le client `mysql` le lit tout seul, sans option à passer. Les requêtes deviennent alors :

   ```bash
   ssh sg-linstantcle "mysql --batch dbvkhvlostfyua -e 'SELECT …'" > .local/q1.tsv
   ```

   Trois raisons de préférer cette forme à toute autre : le mot de passe ne traverse plus aucun interpréteur de commande, donc les caractères spéciaux cessent de poser problème ; il n'apparaît ni dans `ps` ni dans l'historique du shell, ce qu'un mot de passe en argument ne peut pas garantir ; et il ne quitte jamais le serveur. Thomas crée ce fichier lui-même, personne d'autre ne le lit.

   `--batch` produit du TSV sans encadrement, exploitable directement. Rediriger vers `.local/`, qui est exclu de Git.

   L'**oracle tarifaire de la phase 5 n'utilise pas cet accès** : il interroge le prix public par HTTP depuis Make, parce que c'est le prix vu par le client qui doit être surveillé. Cet accès sert aux extractions ponctuelles, Q6 en tête.
2. ~~Clore Q6~~ **Fait, 11 septembre 2026.** Les six requêtes de grille tarifaire ont été exécutées avec cet accès, sorties dans `.local/q1_grille_base.tsv` à `.local/q6_repli_minlos.tsv`, résultats reportés dans le tableau de `docs/briefs/constat-phase-0.md`. Six anomalies relevées (A à F), à valider par Thomas.
3. **Relire le greffon de paiement Stripe**, récupéré dans `.local/wp-vikstripe/`, pour clore la réserve de Q5.

## Fichiers liés

- `docs/briefs/sexcape-room-reservation.md` — le brief de construction
- `docs/briefs/constat-phase-0.md` — le constat, avec Q6 encore ouverte
- `docs/briefs/journal-vik.md` — journal des modifications dans l'administration Vik
- `.local/db.env` — identifiants, non versionné
- `.local/README.md` — état des copies locales (Vik, channel manager)
