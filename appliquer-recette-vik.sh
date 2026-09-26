#!/bin/bash
#
# appliquer-recette-vik.sh
#
# Applique une recette versionnée d'écriture dans Vik Booking, décision de
# Thomas du 26 septembre 2026 (docs/briefs/brief-f3-rappel-avant-sejour.md,
# chapitres 2 et 3). Périmètre : #__vikbooking_condtexts,
# #__vikbooking_cronjobs (une clé de params) et #__vikbooking_adultsdiff,
# rien d'autre. Tarifs, saisons et restrictions restent dans les écrans de Vik.
#
# Même modèle que deployer-moteur.sh : simulation par défaut, refus si
# l'environnement ne correspond pas à la cible annoncée, sauvegarde des
# lignes touchées avant écriture, --rollback qui les remet, idempotent (la
# seconde exécution n'a rien à écrire), sortie non nulle au premier écart.
#
# Usage :
#   ./appliquer-recette-vik.sh --env staging|production --hote DOMAINE [options]
#
# Options :
#   --env ENV                staging ou production (obligatoire)
#   --hote DOMAINE           dossier sous ~/www/ sur le serveur, ex.
#                            staging13.linstantcle.ch ou linstantcle.ch
#                            (obligatoire)
#   --recette FICHIER        recette JSON (défaut :
#                            recettes-vik/f3-rappel-avant-sejour.json)
#   --ssh ALIAS              alias SSH (défaut : sg-linstantcle)
#   --appliquer              écrit pour de vrai (sinon simulation)
#   --confirmer-production   obligatoire en plus de --appliquer ou de
#                            --rollback quand --env vaut production ; un geste
#                            de Thomas, jamais de Code
#   --rollback               remet les lignes de la dernière sauvegarde de
#                            cette recette sur cet hôte
#   -h, --help               cette aide
#
# Code de sortie : 0 si tout est conforme (ou déjà appliqué) ; 2 au premier
# écart entre la base et l'état « avant » ou « après » de la recette, sans
# rien avoir écrit ; 1 sur un refus ou une erreur. La dernière ligne imprimée
# est toujours « SORTIE=<code> ».
#
# Toute comparaison se fait côté serveur, sur les valeurs brutes. La sortie
# de WP-CLI est traduite à la volée sur ce site : le rapport revient en
# hexadécimal avec son SHA-256, contrôlé ici avant lecture. Chaque texte y
# figure par son SHA-256 ou en hexadécimal, jamais en clair.
#
# Ce que ce script ne fait jamais : écrire hors des trois tables, lire le msg
# d'un texte existant (certains portent un code de porte), réécrire le msg
# d'un texte marqué « msg_libre » une fois créé, tourner en production sans
# --confirmer-production.
#
set -u

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOTEUR="$REPO_ROOT/recettes-vik/moteur-recette.php"

ENV=""
HOTE=""
SSH_ALIAS="sg-linstantcle"
RECETTE="$REPO_ROOT/recettes-vik/f3-rappel-avant-sejour.json"
APPLIQUER=0
CONFIRMER_PRODUCTION=0
ROLLBACK=0

rouge() { printf '\033[31m%s\033[0m\n' "$*"; }
vert()  { printf '\033[32m%s\033[0m\n' "$*"; }
jaune() { printf '\033[33m%s\033[0m\n' "$*"; }
info()  { printf '  %s\n' "$*"; }
titre() { printf '\n== %s ==\n' "$*"; }
sortir(){ echo "SORTIE=$1"; exit "$1"; }
mourir(){ rouge "ERREUR : $*"; sortir 1; }

usage() { sed -n '2,45p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

while [ $# -gt 0 ]; do
  case "$1" in
    --env)                  ENV="${2:-}"; shift 2 ;;
    --hote)                 HOTE="${2:-}"; shift 2 ;;
    --recette)              RECETTE="${2:-}"; shift 2 ;;
    --ssh)                  SSH_ALIAS="${2:-}"; shift 2 ;;
    --appliquer)            APPLIQUER=1; shift ;;
    --confirmer-production) CONFIRMER_PRODUCTION=1; shift ;;
    --rollback)             ROLLBACK=1; shift ;;
    -h|--help)              usage; exit 0 ;;
    *) mourir "option inconnue : $1 (--help pour l'usage)" ;;
  esac
done

[ -n "$ENV" ] || mourir "--env est obligatoire (staging ou production)"
[ "$ENV" = "staging" ] || [ "$ENV" = "production" ] || mourir "--env doit valoir staging ou production, pas '$ENV'"
[ -n "$HOTE" ] || mourir "--hote est obligatoire : le domaine cible ne se devine pas"
printf '%s' "$HOTE" | grep -Eq '^[a-z0-9][a-z0-9.-]*[a-z0-9]$' || mourir "--hote invalide : $HOTE"

if [ "$ENV" = "production" ] && { [ "$APPLIQUER" -eq 1 ] || [ "$ROLLBACK" -eq 1 ]; } && [ "$CONFIRMER_PRODUCTION" -ne 1 ]; then
  mourir "--env production avec --appliquer ou --rollback exige aussi --confirmer-production : un geste de Thomas, jamais de Code"
fi

command -v ssh >/dev/null || mourir "ssh introuvable"
command -v jq  >/dev/null || mourir "jq introuvable"
command -v xxd >/dev/null || mourir "xxd introuvable"
[ -f "$MOTEUR" ]  || mourir "moteur introuvable : $MOTEUR"
[ -f "$RECETTE" ] || mourir "recette introuvable : $RECETTE"
jq -e '.nom and .regles and .textes_crees and .gabarit and .supplements_adultes' "$RECETTE" >/dev/null \
  || mourir "recette JSON invalide ou incomplète : $RECETTE"

sha_local() { shasum -a 256 | awk '{print $1}'; }
hex_local() { printf '%s' "$1" | od -An -tx1 | tr -d ' \n'; }
# Décode localement de l'hexadécimal rendu par le serveur : des octets bruts,
# jamais passés par la traduction du site.
dehex() { printf '%s' "$1" | xxd -r -p; }

RECETTE_SHA=$(sha_local < "$RECETTE")

if [ "$ROLLBACK" -eq 1 ]; then MODE="rollback"
elif [ "$APPLIQUER" -eq 1 ]; then MODE="appliquer"
else MODE="plan"
fi

# ------------------------------------------------------------------ appel
# Le moteur part par l'entrée standard de `wp eval-file -`, précédé de la
# recette en base64 : rien ne transite par la ligne de commande distante
# sinon le mode, l'environnement et l'hôte. --skip-plugins et --skip-themes :
# ni Vik ni la traduction n'ont à se charger pour lire et écrire trois tables.
appel_distant() {
  local b64 quoted
  b64=$(base64 < "$RECETTE" | tr -d '\n')
  quoted=$(printf '%q ' "$MODE" "$ENV" "$HOTE")
  {
    printf '<?php $ICL_RECETTE_B64 = %s; ?>' "'$b64'"
    cat "$MOTEUR"
  } | ssh "$SSH_ALIAS" "cd ~/www/$(printf '%q' "$HOTE")/public_html && wp eval-file - $quoted --skip-plugins --skip-themes"
}

titre "Recette $(jq -r .nom "$RECETTE") — $HOTE ($ENV), mode $MODE"
info "recette : ${RECETTE#"$REPO_ROOT"/} (SHA-256 $RECETTE_SHA)"

SORTIE_BRUTE=$(appel_distant 2>&1)
RC=$?
LIGNE=$(printf '%s\n' "$SORTIE_BRUTE" | grep '^RAPPORT ' | tail -1)
if [ -z "$LIGNE" ]; then
  rouge "  aucun rapport rendu par le serveur (code $RC). Sortie brute, en hexadécimal :"
  printf '%s' "$SORTIE_BRUTE" | head -c 2000 | od -An -tx1 | sed 's/^/    /'
  mourir "le résultat n'est pas établi : ne rien conclure"
fi
RHEX=$(printf '%s' "$LIGNE" | awk '{print $2}')
RSHA=$(printf '%s' "$LIGNE" | awk '{print $3}')
JSON=$(dehex "$RHEX")
[ "$(printf '%s' "$JSON" | sha_local)" = "$RSHA" ] \
  || mourir "empreinte du rapport non conforme : le rapport a été altéré en route, ne rien conclure"
printf '%s' "$JSON" | jq -e . >/dev/null || mourir "rapport illisible"

j() { printf '%s' "$JSON" | jq -r "$1"; }

# --------------------------------------------------------------- préalables
titre "Préalables"
ENV_HEX=$(j '.env_hex // ""')
EXP_HEX=$(hex_local "$ENV")
if [ "$ENV_HEX" = "$EXP_HEX" ]; then vert "  OK   wp_get_environment_type() == '$ENV' — $ENV_HEX"
elif [ -n "$ENV_HEX" ]; then rouge "  KO   wp_get_environment_type() — relevé $ENV_HEX, attendu $EXP_HEX"
fi
HH=$(j '.home_host_hex // ""')
[ -n "$HH" ] && info "hôte du site (option home) : $HH ($(dehex "$HH"))"
[ "$(j '.recette_sha256 // ""')" = "$RECETTE_SHA" ] \
  || mourir "le serveur n'a pas reçu la recette telle qu'elle est dans le dépôt"
vert "  OK   recette reçue intacte par le serveur"

# -------------------------------------------------------------- opérations
titre "Opérations"
N=$(j '.operations | length')
i=0
while [ "$i" -lt "$N" ]; do
  op=$(j ".operations[$i]")
  type=$(printf '%s' "$op" | jq -r .type)
  etat=$(printf '%s' "$op" | jq -r .etat)
  cible_hex=$(printf '%s' "$op" | jq -r '.cible_hex // empty')
  if [ -n "$cible_hex" ]; then cible=$(dehex "$cible_hex"); else cible=$(printf '%s' "$op" | jq -r '.cible // ""'); fi
  case "$etat" in
    deja)     marque="déjà    " ;;
    a_ecrire) marque="à écrire" ;;
    ecart)    marque="ÉCART   " ;;
    *)        marque="$etat" ;;
  esac
  ligne="$marque  $type  $cible"
  case "$etat" in
    ecart)    rouge "  $ligne" ;;
    a_ecrire) jaune "  $ligne" ;;
    *)        info "$ligne" ;;
  esac
  # Détail des états : hexadécimal ou SHA-256 seulement.
  printf '%s' "$op" | jq -r '
    to_entries[]
    | select(.key | test("^(id|trouve|avant|apres|lastupd|occurrences|detail)"))
    | "        \(.key) = \(.value | if type == "array" then map(tostring) | join(",") else tostring end)"'
  i=$((i + 1))
done

# ------------------------------------------------------------------- bilan
titre "Bilan"
STATUT=$(j .statut)
j '.messages[]?' | sed 's/^/  /'
[ "$(j '.sauvegarde // ""')" != "" ] && info "sauvegarde sur le serveur : $(j .sauvegarde) (SHA-256 $(j '.sauvegarde_sha256 // "-"'))"
[ "$(j '.lastupd_ecrit // ""')" != "" ] && info "lastupd écrit (UTC) : $(j .lastupd_ecrit)"
info "comptes : déjà $(j '.compte.deja // 0'), à écrire $(j '.compte.a_ecrire // 0'), écarts $(j '.compte.ecart // 0'), écrit $(j '.ecrit // 0')"

case "$STATUT" in
  ok)
    if [ "$MODE" = "plan" ] && [ "$(j '.compte.a_ecrire // 0')" != "0" ]; then
      jaune "Simulation : rien n'a été écrit. Relancer avec --appliquer pour écrire."
    elif [ "$MODE" = "plan" ]; then
      vert "Rien à écrire : la base vaut déjà l'état « après » de la recette."
    elif [ "$(j '.ecrit // 0')" = "0" ] && [ "$MODE" = "appliquer" ]; then
      vert "Rien à écrire : la base vaut déjà l'état « après » de la recette."
    elif [ "$(j '.ecrit // 0')" = "0" ]; then
      vert "Rien à remettre : la base vaut déjà l'état d'avant la recette."
    elif [ "$MODE" = "appliquer" ]; then
      vert "Recette appliquée et relue : tout vaut l'état « après »."
    else
      vert "Retour arrière terminé et relu."
    fi
    [ "$RC" -eq 0 ] || mourir "statut ok mais code de sortie distant $RC : ne rien conclure"
    sortir 0
    ;;
  ecart)
    rouge "Arrêt au premier écart : rien n'a été écrit."
    sortir 2
    ;;
  *)
    mourir "statut distant « $STATUT » (code $RC)"
    ;;
esac
