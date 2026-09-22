# Brief de correctif — la vérification d'apparence de `deployer-moteur.sh` ne suit pas les redirections

Version 1, 21 septembre 2026. Rédaction Cowork, exécution Code.

**Destination : `icl-dev/docs/briefs/brief-correctif-verification-apparence.md`. À déplacer, pas à recopier.**

---

## 1. Ce qui s'est passé

Le déploiement du 21 septembre en préproduction s'est terminé sur :

```
KO   aucun jeton --srlm- trouvé sur https://staging13.linstantcle.ch/ alors que le levier est actif
ERREUR : apparence non confirmée — vérifier à l'œil avant de considérer ce déploiement terminé
```

**Le moteur n'est pas en cause. La vérification l'est.**

## 2. La preuve, en trois lectures

Sur le serveur, en lecture seule :

```
wp eval 'var_dump( wp_get_environment_type(), LME_BRANDS_HOST_OVERRIDE, lme_brands_current_http_host(), lme_brands_current_request_brand_key() );'
  → 'staging' / 'reservation.sexcaperoom.ch' / 'reservation.sexcaperoom.ch' / 'sexcaperoom'

wp eval 'var_dump( lme_sexcaperoom_current_appearance() );'
  → array(6), colors, radii, fonts et shell complets. Aucun 'appearance_invalid' dans debug.log.

curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' https://staging13.linstantcle.ch/
  → 301 https://staging13.linstantcle.ch/fr/

curl -sL https://staging13.linstantcle.ch/ | grep -c -- '--srlm-'
  → 57
```

**Le levier agit, la marque résout, l'apparence s'applique, et les 57 jetons sont bien dans la page.** Le script a lu le corps d'une réponse 301, qui est vide, et en a conclu à leur absence.

## 3. La cause

`/` est redirigé vers `/fr/` par TranslatePress, sur les deux marques et dans les deux environnements. La vérification d'apparence appelle l'URL racine sans suivre la redirection.

## 4. Le correctif

Suivre les redirections dans cette vérification, et dans toute autre vérification du script qui lit un corps de réponse HTTP. Contrôler en même temps le code de statut final : un 200 attendu, et un échec franc sur 4xx ou 5xx plutôt qu'une absence de jeton interprétée comme un défaut d'apparence.

**Ne pas viser `/fr/` en dur** : la langue par défaut est une donnée de configuration, pas une constante du chantier, et elle diffèrera le jour où l'hôte de réservation ne servira que du français.

## 5. Ce que ce défaut apprend, et qui vaut plus que le correctif

Le script a rendu **un échec sur un succès**. C'est le symétrique de la règle n°6 du `CLAUDE.md`, « aucun échec silencieux » : ici, un succès bruyamment nié. Les deux coûtent, et le second use la confiance dans l'outil, qui est ce qui fait qu'on le relance sans le relire.

**À vérifier dans la même passe : toute autre vérification du script qui conclut d'une absence.** Une absence de jeton, de ligne ou de correspondance ne prouve rien tant que la lecture elle-même n'est pas établie comme valide.

## 6. Prompt de lancement

**Sonnet 5, effort faible.**

> Lis `CLAUDE.md`, `docs/briefs/brief-correctif-verification-apparence.md` et `deployer-moteur.sh`. Le déploiement du 21 septembre en préproduction a rendu un KO sur la vérification d'apparence alors que les 57 jetons `--srlm-` sont bien dans la page : le script a lu le corps d'une réponse 301 vers `/fr/`, produite par TranslatePress.
>
> Corrige la vérification d'apparence pour qu'elle suive les redirections et contrôle le code de statut final, avec un échec franc et distinct sur 4xx ou 5xx. **Ne vise pas `/fr/` en dur.** Puis **relis toute autre vérification du script qui conclut d'une absence** et applique le même traitement : une absence ne prouve rien tant que la lecture n'est pas établie comme valide. Mets à jour `constat-script-deploiement.md`.
>
> Profite de la même passe pour **exclure le motif `tests/` des racines déployées** : `mu-plugins/lme-brands/tests/test-core.php` est suivi par Git, donc déployé, et reste exécutable par une requête HTTP sans garde `ABSPATH`. C'est un motif et non une énumération, donc sans contradiction avec la liste dynamique de B9. Prérequis de B10.
>
> Ne déploie rien, n'écris rien en base, ne lis aucune clé.
