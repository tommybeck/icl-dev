#!/bin/bash
#
# deployer-moteur.sh
#
# Rend executable le deploiement du moteur reservation Sexcape Room, decrit
# a la main dans docs/briefs/constat-deploiement-moteur.md (chapitres 3 a 7).
# Chantier B9, docs/briefs/plan-de-marche.md.
#
# Le MEME script tourne sur la preproduction et sur la production : la
# symetrie est une propriete du geste, pas d'une intention. Il simule par
# defaut, refuse de continuer plutot que de deviner, prend sa propre
# sauvegarde avant d'ecrire, et porte son propre retour arriere.
#
# Usage :
#   ./deployer-moteur.sh --env staging|production --hote DOMAINE [options]
#
# Options :
#   --env ENV                staging ou production (obligatoire)
#   --hote DOMAINE            sous-domaine ou domaine cible, ex. staging13.linstantcle.ch
#                             ou linstantcle.ch (obligatoire) ; c'est le nom du
#                             dossier sous ~/www/ sur le serveur, il change a
#                             chaque recreation de la preproduction
#   --ssh ALIAS               alias SSH (defaut : sg-linstantcle)
#   --override-host DOMAINE  valeur du levier de preproduction (defaut :
#                             reservation.sexcaperoom.ch) ; refuse avec --env production
#   --forcer-override         autorise a remplacer un levier deja pose avec une
#                             autre valeur (sinon le script refuse de deviner
#                             lequel des deux essais en cours doit gagner)
#   --appliquer                ecrit pour de vrai (sinon simulation, par defaut)
#   --confirmer-production    obligatoire en plus de --appliquer quand --env
#                             vaut production ; un geste de Thomas, jamais de Code
#   --rollback                 retire ce que ce script a pose et restaure functions.php
#   -h, --help                 cette aide
#
# Sans --appliquer : imprime le plan, n'ecrit rien.
# Avec --rollback : ignore --appliquer, ne fait que le retour arriere.
#
# Ce que ce script ne fait jamais : lire une cle en entier, ecrire en base de
# donnees, deployer autre chose que l'inventaire du chapitre 3 du constat.
#
set -u

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

ENV=""
HOTE=""
SSH_ALIAS="sg-linstantcle"
OVERRIDE_HOST="reservation.sexcaperoom.ch"
OVERRIDE_HOST_SET=0
FORCE_OVERRIDE=0
APPLIQUER=0
CONFIRMER_PRODUCTION=0
ROLLBACK=0

REQUIRE_LINE="require_once get_stylesheet_directory() . '/inc/sexcaperoom-tunnel.php';"

# Empreintes attendues, constat-deploiement-moteur.md chapitre 1, revues le
# 20 septembre 2026 sur la production et sur staging13.linstantcle.ch. A
# remettre a jour si Thomas modifie l'un de ces fichiers pour une raison sans
# rapport avec ce chantier -- le script doit alors refuser jusqu'a la mise a
# jour de ces quatre constantes, plutot que de deviner que le changement est
# anodin.
FUNCTIONS_BASELINE_SIZE=7390
STYLE_BASELINE_SIZE=2771
API_HOST_BASELINE_SIZE=1371
VRE_BASELINE_SIZE=5592

# Racines deployees : TOUT ce que git suit dessous part sur le serveur, sans
# liste de fichiers figee dans ce script. mu-plugins/lme-brands/ ou
# themes/astra-child/inc/ peuvent gagner des fichiers sans jamais toucher a
# deployer-moteur.sh -- une liste figee ici serait elle-meme un risque
# d'echec silencieux, le jour ou quelqu'un oublierait de la mettre a jour.
DEPLOY_ROOTS=("mu-plugins" "themes/astra-child")

# Exclusion nominative, deux fichiers seulement : ce sont les placeholders du
# depot, jamais des copies du theme reel (chapitre 2 du constat de
# deploiement). Ajouter du vrai contenu de chantier ne se fait jamais sous
# ces deux noms precis a la racine du theme -- toujours sous inc/ ou assets/
# -- donc cette exclusion ne grossira pas avec le temps.
EXCLUDE_FILES=("themes/astra-child/functions.php" "themes/astra-child/style.css")

# Exclusion par motif, en plus de l'exclusion nominative : tout chemin qui
# traverse un dossier "tests" n'est jamais deploye, quel que soit le mu-plugin
# ou le sous-dossier du theme qui le porte. C'est un motif et non une
# enumeration -- il ne grossira pas avec le temps, meme si tests/ apparait
# ailleurs plus tard -- exactement l'argument qui justifie deja la liste
# dynamique ci-dessus. Motif trouve sur mu-plugins/lme-brands/tests/, suivi
# par git donc deploye sans garde ABSPATH, prerequis de B10
# (docs/briefs/brief-correctif-verification-apparence.md).
is_test_path() {
  case "$1" in
    tests/*|*/tests/*) return 0 ;;
    *) return 1 ;;
  esac
}

is_excluded() {
  local f
  for f in "${EXCLUDE_FILES[@]}"; do [ "$1" = "$f" ] && return 0; done
  is_test_path "$1" && return 0
  return 1
}

rouge() { printf '\033[31m%s\033[0m\n' "$*"; }
vert()  { printf '\033[32m%s\033[0m\n' "$*"; }
jaune() { printf '\033[33m%s\033[0m\n' "$*"; }
info()  { printf '  %s\n' "$*"; }
titre() { printf '\n== %s ==\n' "$*"; }
mourir(){ rouge "ERREUR : $*"; exit 1; }

usage() { sed -n '2,39p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

while [ $# -gt 0 ]; do
  case "$1" in
    --env)               ENV="${2:-}"; shift 2 ;;
    --hote)               HOTE="${2:-}"; shift 2 ;;
    --ssh)                SSH_ALIAS="${2:-}"; shift 2 ;;
    --override-host)     OVERRIDE_HOST="${2:-}"; OVERRIDE_HOST_SET=1; shift 2 ;;
    --forcer-override)    FORCE_OVERRIDE=1; shift ;;
    --appliquer)          APPLIQUER=1; shift ;;
    --confirmer-production) CONFIRMER_PRODUCTION=1; shift ;;
    --rollback)           ROLLBACK=1; shift ;;
    -h|--help)            usage; exit 0 ;;
    *) mourir "option inconnue : $1 (--help pour l'usage)" ;;
  esac
done

[ -n "$ENV" ] || mourir "--env est obligatoire (staging ou production)"
[ "$ENV" = "staging" ] || [ "$ENV" = "production" ] || mourir "--env doit valoir staging ou production, pas '$ENV'"
[ -n "$HOTE" ] || mourir "--hote est obligatoire : le domaine cible ne se devine pas"

if [ "$ENV" = "production" ]; then
  [ "$OVERRIDE_HOST_SET" -eq 0 ] || mourir "--override-host n'a rien a faire avec --env production : le levier ne doit jamais exister en production"
  if [ "$APPLIQUER" -eq 1 ] && [ "$ROLLBACK" -eq 0 ] && [ "$CONFIRMER_PRODUCTION" -ne 1 ]; then
    mourir "--env production --appliquer exige aussi --confirmer-production : un geste deliberé, celui de Thomas, jamais celui de Code"
  fi
fi

command -v ssh >/dev/null || mourir "ssh introuvable"
command -v sha256sum >/dev/null || command -v shasum >/dev/null || mourir "ni sha256sum ni shasum disponibles"
command -v rsync >/dev/null || mourir "rsync introuvable"
command -v curl >/dev/null || mourir "curl introuvable"

hash_local() {
  if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'
  else shasum -a 256 "$1" | awk '{print $1}'
  fi
}

# --------------------------------------------------------------------------
# Le script distant. Un seul fichier, plusieurs modes, aucune decision de
# politique : il ne fait que constater (tailles, empreintes, comptes de
# lignes) ou executer un geste borne (copie de sauvegarde, fusion, retour
# arriere). Toute decision « on continue ou on refuse » vit ici, cote local,
# jamais cote serveur -- un seul endroit a lire pour savoir ce que ce script
# accepte.
#
# Convention de sortie : chaque mode qui rend des faits les imprime sur la
# sortie standard en lignes CLE=valeur, rien d'autre. La narration humaine va
# sur la sortie d'erreur. C'est ce qui permet au prefixe Stripe de sortir
# "sur la seule sortie standard" (§B9 du plan de marche) sans jamais
# traverser un fichier de journal : ce script n'en ecrit aucun.
# --------------------------------------------------------------------------
read -r -d '' REMOTE_HELPER <<'REMOTE_HELPER_EOF'
set -u
err() { printf '%s\n' "$*" >&2; }
kv()  { printf '%s=%s\n' "$1" "$2"; }
sz()  { [ -f "$1" ] && wc -c < "$1" | tr -d ' ' || echo -1; }
# jamais un "A && grep -c ... || echo 0" : grep -c sort avec le statut 1 des
# qu'il compte zero occurrence, ce qui declenche aussi le repli et double la
# ligne. Un if/else classique n'a pas ce piege.
count_line() {
  if [ -f "$2" ]; then grep -Fxc -- "$1" "$2"
  else echo 0
  fi
}

MODE="${1:-}"; HOTE="${2:-}"
BASE="$HOME/www/$HOTE/public_html"

case "$MODE" in

  preconditions)
    REQUIRE_LINE="$3"
    if [ ! -d "$BASE" ] || [ ! -f "$BASE/wp-config.php" ]; then
      kv WP_PATH_OK 0
      err "chemin distant introuvable ou incomplet : $BASE"
      exit 1
    fi
    kv WP_PATH_OK 1

    if command -v wp >/dev/null 2>&1; then
      kv WPCLI_OK 1
      ENV_HEX=$(wp eval 'echo bin2hex(wp_get_environment_type());' --path="$BASE" 2>/dev/null)
      kv ENV_TYPE_ACTUAL_HEX "${ENV_HEX:-}"
    else
      kv WPCLI_OK 0
      err "wp-cli introuvable sur ce serveur"
    fi

    FUNC="$BASE/wp-content/themes/astra-child/functions.php"
    kv FUNCTIONS_SIZE "$(sz "$FUNC")"
    kv FUNCTIONS_REQUIRE_COUNT "$(count_line "$REQUIRE_LINE" "$FUNC")"
    kv STYLE_SIZE "$(sz "$BASE/wp-content/themes/astra-child/style.css")"
    kv API_HOST_SIZE "$(sz "$BASE/wp-content/mu-plugins/api-host.php")"
    kv VRE_SIZE "$(sz "$BASE/wp-content/mu-plugins/vre-paid-autoconfirm.php")"
    kv MUROOT_EXISTS "$( [ -f "$BASE/wp-content/mu-plugins/lme-brands.php" ] && echo 1 || echo 0 )"
    kv MUDIR_EXISTS "$( [ -d "$BASE/wp-content/mu-plugins/lme-brands" ] && echo 1 || echo 0 )"

    OVERRIDE_LINE=$(grep -F "define( 'LME_BRANDS_HOST_OVERRIDE'," "$BASE/wp-config.php" 2>/dev/null || true)
    if [ -n "$OVERRIDE_LINE" ]; then
      kv OVERRIDE_LINE_PRESENT 1
      kv OVERRIDE_LINE_VALUE "$(printf '%s' "$OVERRIDE_LINE" | sed -n "s/.*define( 'LME_BRANDS_HOST_OVERRIDE', *'\([^']*\)'.*/\1/p")"
    else
      kv OVERRIDE_LINE_PRESENT 0
      kv OVERRIDE_LINE_VALUE -
    fi

    if command -v wp >/dev/null 2>&1; then
      Q="SELECT LEFT(JSON_UNQUOTE(JSON_EXTRACT(params,'\$.secretkey')),8) FROM sir_vikbooking_gpayments WHERE file LIKE '%stripe%' AND published=1;"
      ROWS=$(wp db query "$Q" --path="$BASE" --skip-column-names 2>/dev/null)
      NROWS=$(printf '%s\n' "$ROWS" | grep -c . || true)
      kv STRIPE_PUBLISHED_COUNT "${NROWS:-0}"
      if [ "${NROWS:-0}" -eq 1 ]; then
        kv STRIPE_SECRET_PREFIX "$(printf '%s' "$ROWS" | tr -d '\n\r')"
      else
        kv STRIPE_SECRET_PREFIX INCONNU
      fi
    fi
    exit 0
    ;;

  functions-facts)
    REQUIRE_LINE="$3"
    FUNC="$BASE/wp-content/themes/astra-child/functions.php"
    kv FUNCTIONS_SIZE "$(sz "$FUNC")"
    kv FUNCTIONS_REQUIRE_COUNT "$(count_line "$REQUIRE_LINE" "$FUNC")"
    exit 0
    ;;

  hash-manifest)
    shift 2
    WPCONTENT="$BASE/wp-content"
    for rel in "$@"; do
      f="$WPCONTENT/$rel"
      if [ -f "$f" ]; then
        printf '%s\t%s\n' "$rel" "$(sha256sum "$f" | awk '{print $1}')"
      else
        printf '%s\tABSENT\n' "$rel"
      fi
    done
    exit 0
    ;;

  backup)
    FUNC="$BASE/wp-content/themes/astra-child/functions.php"
    [ -f "$FUNC" ] || { err "functions.php introuvable, rien a sauvegarder"; exit 1; }
    TS=$(date -u +%Y%m%dT%H%M%SZ)
    DIR="$HOME/.icl-dev-deploy-backups/$HOTE/$TS"
    mkdir -p "$DIR" || { err "impossible de creer $DIR"; exit 1; }
    cp -p "$FUNC" "$DIR/functions.php" || { err "copie de sauvegarde impossible"; exit 1; }
    kv backup_dir "$DIR"
    exit 0
    ;;

  list-backups)
    DIR="$HOME/.icl-dev-deploy-backups/$HOTE"
    [ -d "$DIR" ] && find "$DIR" -mindepth 1 -maxdepth 1 -type d | sort
    exit 0
    ;;

  restore-functions)
    BACKUP_DIR="$3"
    SRC="$BACKUP_DIR/functions.php"
    DST="$BASE/wp-content/themes/astra-child/functions.php"
    [ -f "$SRC" ] || { err "sauvegarde introuvable : $SRC"; exit 1; }
    cp -p "$SRC" "$DST" || { err "restauration impossible"; exit 1; }
    kv restored_from "$BACKUP_DIR"
    kv size "$(sz "$DST")"
    exit 0
    ;;

  merge-functions)
    BASELINE_SIZE="$3"; REQUIRE_LINE="$4"
    FUNC="$BASE/wp-content/themes/astra-child/functions.php"
    [ -f "$FUNC" ] || { err "functions.php introuvable"; exit 1; }
    COUNT=$(grep -Fxc -- "$REQUIRE_LINE" "$FUNC")
    if [ "$COUNT" -ge 2 ]; then
      err "la ligne require_once apparait $COUNT fois : deja dans un etat incoherent, correction manuelle requise"
      exit 1
    fi
    if [ "$COUNT" -eq 1 ]; then
      kv already_merged 1
      kv size "$(sz "$FUNC")"
      exit 0
    fi
    CUR_SIZE=$(sz "$FUNC")
    if [ "$CUR_SIZE" -ne "$BASELINE_SIZE" ]; then
      err "functions.php ne ressemble pas au thème reel : $CUR_SIZE octets, $BASELINE_SIZE attendus avant fusion"
      exit 1
    fi
    LAST_BYTE=$(tail -c1 "$FUNC" | od -An -tx1 | tr -d ' \n')
    if [ "$LAST_BYTE" = "0a" ]; then SEP=""; else SEP=$'\n'; fi
    printf '%s%s\n' "$SEP" "$REQUIRE_LINE" >> "$FUNC"
    NEW_SIZE=$(sz "$FUNC")
    EXPECTED=$((BASELINE_SIZE + ${#SEP} + ${#REQUIRE_LINE} + 1))
    if [ "$NEW_SIZE" -ne "$EXPECTED" ]; then
      err "fusion suspecte : taille finale $NEW_SIZE, $EXPECTED attendus"
      exit 1
    fi
    kv merged 1
    kv size "$NEW_SIZE"
    exit 0
    ;;

  set-override)
    OVERRIDE_HOST="$3"; FORCE="$4"
    WPCONFIG="$BASE/wp-config.php"
    [ -f "$WPCONFIG" ] || { err "wp-config.php introuvable"; exit 1; }
    EXISTING=$(grep -F "define( 'LME_BRANDS_HOST_OVERRIDE'," "$WPCONFIG" || true)
    if [ -n "$EXISTING" ]; then
      CUR_VAL=$(printf '%s' "$EXISTING" | sed -n "s/.*define( 'LME_BRANDS_HOST_OVERRIDE', *'\([^']*\)'.*/\1/p")
      if [ "$CUR_VAL" = "$OVERRIDE_HOST" ]; then
        kv override_state unchanged
        kv override_value "$CUR_VAL"
        exit 0
      fi
      if [ "$FORCE" != "1" ]; then
        err "le levier est deja pose avec une autre valeur ($CUR_VAL) : --forcer-override requis pour le remplacer par $OVERRIDE_HOST"
        exit 1
      fi
      ESC=$(printf '%s' "$OVERRIDE_HOST" | sed 's/[&/\]/\\&/g')
      sed -i "s/define( 'LME_BRANDS_HOST_OVERRIDE', *'[^']*'/define( 'LME_BRANDS_HOST_OVERRIDE', '$ESC'/" "$WPCONFIG"
      kv override_state replaced
      kv override_value "$OVERRIDE_HOST"
      exit 0
    fi
    ANCHOR=$(grep -n -F -x "require_once ABSPATH . 'wp-settings.php';" "$WPCONFIG" | cut -d: -f1)
    NANCHOR=$(printf '%s\n' "$ANCHOR" | grep -c .)
    if [ -z "$ANCHOR" ] || [ "$NANCHOR" -ne 1 ]; then
      err "point d'ancrage 'require_once ABSPATH . wp-settings.php' introuvable ou ambigu ($NANCHOR occurrence(s)) : insertion refusee"
      exit 1
    fi
    NEWLINE="define( 'LME_BRANDS_HOST_OVERRIDE', '$OVERRIDE_HOST' );"
    sed -i "${ANCHOR}i ${NEWLINE}" "$WPCONFIG"
    kv override_state inserted
    kv override_value "$OVERRIDE_HOST"
    exit 0
    ;;

  remove-override)
    WPCONFIG="$BASE/wp-config.php"
    [ -f "$WPCONFIG" ] || { err "wp-config.php introuvable"; exit 1; }
    if grep -qF "define( 'LME_BRANDS_HOST_OVERRIDE'," "$WPCONFIG"; then
      grep -vF "define( 'LME_BRANDS_HOST_OVERRIDE'," "$WPCONFIG" > "$WPCONFIG.new" && mv "$WPCONFIG.new" "$WPCONFIG"
      kv removed 1
    else
      kv removed 0
    fi
    exit 0
    ;;

  remove-tree)
    # Chemins a retirer donnes en arguments par l'appelant local, jamais une
    # liste figee ici : voir compute_rollback_targets() cote local.
    shift 2
    WPCONTENT="$BASE/wp-content"
    for p in "$@"; do
      T="$WPCONTENT/$p"
      if [ -e "$T" ]; then rm -rf "$T"; kv "removed_$p" 1; else kv "removed_$p" 0; fi
    done
    exit 0
    ;;

  debug-log-offset)
    LOG="$BASE/wp-content/debug.log"
    [ -f "$LOG" ] && wc -c < "$LOG" | tr -d ' ' || echo 0
    exit 0
    ;;

  debug-log-tail-since)
    OFFSET="$3"
    LOG="$BASE/wp-content/debug.log"
    [ -f "$LOG" ] && tail -c +"$((OFFSET + 1))" "$LOG"
    exit 0
    ;;

  *)
    err "mode distant inconnu : $MODE"
    exit 1
    ;;
esac
REMOTE_HELPER_EOF

remote_call() {
  # ssh rejoint tous les arguments de commande en une seule chaîne, retraitée
  # par le shell de connexion distant : sans requoter nous-mêmes, une ligne
  # require_once (parenthèses, apostrophes, point-virgule) casse en transit.
  local mode="$1"; shift
  local quoted; quoted=$(printf '%q ' "$mode" "$HOTE" "$@")
  ssh "$SSH_ALIAS" "bash -s -- $quoted" <<< "$REMOTE_HELPER"
}

# --------------------------------------------------------------------- etat
PRE_WP_PATH_OK=0 PRE_WPCLI_OK=0 PRE_ENV_TYPE_ACTUAL_HEX="" PRE_FUNCTIONS_SIZE=-1
PRE_FUNCTIONS_REQUIRE_COUNT=0 PRE_STYLE_SIZE=-1 PRE_API_HOST_SIZE=-1 PRE_VRE_SIZE=-1
PRE_MUROOT_EXISTS=0 PRE_MUDIR_EXISTS=0 PRE_OVERRIDE_LINE_PRESENT=0 PRE_OVERRIDE_LINE_VALUE="-"
PRE_STRIPE_PUBLISHED_COUNT=0 PRE_STRIPE_SECRET_PREFIX="INCONNU"
PRECOND_OK=1

expected_env_hex() { printf '%s' "$1" | od -An -tx1 | tr -d ' \n'; }

check() {
  # check LABEL CONDITION_1_OU_0 [DETAIL]
  if [ "$2" = "1" ]; then vert "  OK   $1${3:+ — $3}"
  else rouge "  KO   $1${3:+ — $3}"; PRECOND_OK=0
  fi
}

run_preconditions() {
  titre "Préalables — $HOTE ($ENV)"
  local out rc
  out=$(remote_call preconditions "$REQUIRE_LINE")
  rc=$?

  if [ "$rc" -ne 0 ]; then
    rouge "  KO   connexion ou chemin distant ($HOTE via $SSH_ALIAS)"
    PRECOND_OK=0
    return
  fi

  while IFS='=' read -r k v; do
    [ -n "$k" ] || continue
    case "$k" in
      WP_PATH_OK) PRE_WP_PATH_OK="$v" ;;
      WPCLI_OK) PRE_WPCLI_OK="$v" ;;
      ENV_TYPE_ACTUAL_HEX) PRE_ENV_TYPE_ACTUAL_HEX="$v" ;;
      FUNCTIONS_SIZE) PRE_FUNCTIONS_SIZE="$v" ;;
      FUNCTIONS_REQUIRE_COUNT) PRE_FUNCTIONS_REQUIRE_COUNT="$v" ;;
      STYLE_SIZE) PRE_STYLE_SIZE="$v" ;;
      API_HOST_SIZE) PRE_API_HOST_SIZE="$v" ;;
      VRE_SIZE) PRE_VRE_SIZE="$v" ;;
      MUROOT_EXISTS) PRE_MUROOT_EXISTS="$v" ;;
      MUDIR_EXISTS) PRE_MUDIR_EXISTS="$v" ;;
      OVERRIDE_LINE_PRESENT) PRE_OVERRIDE_LINE_PRESENT="$v" ;;
      OVERRIDE_LINE_VALUE) PRE_OVERRIDE_LINE_VALUE="$v" ;;
      STRIPE_PUBLISHED_COUNT) PRE_STRIPE_PUBLISHED_COUNT="$v" ;;
      STRIPE_SECRET_PREFIX) PRE_STRIPE_SECRET_PREFIX="$v" ;;
    esac
  done <<< "$out"

  check "chemin distant et wp-config.php présents" "$PRE_WP_PATH_OK"
  check "wp-cli disponible" "$PRE_WPCLI_OK"

  local exp_hex; exp_hex=$(expected_env_hex "$ENV")
  check "wp_get_environment_type() == '$ENV'" "$([ "$PRE_ENV_TYPE_ACTUAL_HEX" = "$exp_hex" ] && echo 1 || echo 0)" "empreinte relevée $PRE_ENV_TYPE_ACTUAL_HEX, attendue $exp_hex"

  check "api-host.php = $API_HOST_BASELINE_SIZE o" "$([ "$PRE_API_HOST_SIZE" = "$API_HOST_BASELINE_SIZE" ] && echo 1 || echo 0)" "$PRE_API_HOST_SIZE o"
  check "vre-paid-autoconfirm.php = $VRE_BASELINE_SIZE o" "$([ "$PRE_VRE_SIZE" = "$VRE_BASELINE_SIZE" ] && echo 1 || echo 0)" "$PRE_VRE_SIZE o"
  check "style.css = $STYLE_BASELINE_SIZE o, jamais déployé par ce script" "$([ "$PRE_STYLE_SIZE" = "$STYLE_BASELINE_SIZE" ] && echo 1 || echo 0)" "$PRE_STYLE_SIZE o"

  if [ "$PRE_FUNCTIONS_REQUIRE_COUNT" -ge 2 ]; then
    check "functions.php : une seule ligne require_once, jamais dupliquée" "0" "trouvée $PRE_FUNCTIONS_REQUIRE_COUNT fois"
  elif [ "$PRE_FUNCTIONS_REQUIRE_COUNT" -eq 1 ]; then
    vert "  OK   functions.php déjà fusionné ($PRE_FUNCTIONS_SIZE o)"
  else
    check "functions.php ressemble au thème réel avant fusion" "$([ "$PRE_FUNCTIONS_SIZE" = "$FUNCTIONS_BASELINE_SIZE" ] && echo 1 || echo 0)" "$PRE_FUNCTIONS_SIZE o, $FUNCTIONS_BASELINE_SIZE attendus"
  fi

  if [ "$ENV" = "staging" ]; then
    check "VikStripe publié en clés de test (sk_test_)" "$([ "$PRE_STRIPE_SECRET_PREFIX" = "sk_test_" ] && echo 1 || echo 0)" "préfixe relevé : $PRE_STRIPE_SECRET_PREFIX (passerelle(s) publiée(s) : $PRE_STRIPE_PUBLISHED_COUNT)"
    if [ "$PRE_OVERRIDE_LINE_PRESENT" = "1" ]; then
      info "levier déjà posé : $PRE_OVERRIDE_LINE_VALUE"
    fi
  else
    check "aucun levier de préproduction en production" "$([ "$PRE_OVERRIDE_LINE_PRESENT" = "0" ] && echo 1 || echo 0)" "présent=$PRE_OVERRIDE_LINE_PRESENT valeur=$PRE_OVERRIDE_LINE_VALUE"
    if [ "$PRE_STRIPE_SECRET_PREFIX" != "INCONNU" ]; then
      info "pour information, non bloquant : préfixe Stripe publié en production = $PRE_STRIPE_SECRET_PREFIX"
    fi
  fi
}

# ---------------------------------------------------------------- manifeste
# Uniquement les fichiers suivis par git, sous les racines deployees : le
# dossier de travail peut porter du bruit (.DS_Store, etc.) qui n'a rien a
# faire sur le serveur, et `find` l'aurait copie sans distinction. La liste
# vient de l'arborescence du depot a chaque appel, jamais d'une enumeration
# gardee ici : un fichier ajoute sous une racine deployee part au prochain
# deploiement sans toucher a ce script.
build_local_manifest() {
  RELPATHS=()
  LOCAL_HASH=()
  local root rel
  for root in "${DEPLOY_ROOTS[@]}"; do
    while IFS= read -r rel; do
      [ -n "$rel" ] || continue
      is_excluded "$rel" && continue
      RELPATHS+=("$rel")
      LOCAL_HASH+=("$(hash_local "$REPO_ROOT/$rel")")
    done < <(git -C "$REPO_ROOT" ls-files -- "$root" | sort)
  done
}

# ------------------------------------------------------- cibles de retrait
# Pour --rollback : jamais un `rm -rf` de toute une racine deployee (mu-plugins/
# porte aussi api-host.php et vre-paid-autoconfirm.php, qui ne sont pas a nous
# -- chapitre 1 du constat de deploiement). On retire seulement les entrees de
# premier niveau que ce depot possede reellement sous chaque racine, deduites
# de la meme liste dynamique que la copie : un fichier ou dossier que ce
# chantier a fait naitre, jamais un voisin qui existait deja.
compute_rollback_targets() {
  ROLLBACK_TARGETS=()
  local root rel relative child seen
  for root in "${DEPLOY_ROOTS[@]}"; do
    seen=""
    while IFS= read -r rel; do
      [ -n "$rel" ] || continue
      is_excluded "$rel" && continue
      relative="${rel#"$root"/}"
      child="${relative%%/*}"
      case " $seen " in
        *" $child "*) continue ;;
      esac
      seen="$seen $child"
      ROLLBACK_TARGETS+=("$root/$child")
    done < <(git -C "$REPO_ROOT" ls-files -- "$root" | sort)
  done
}

# rend un diff (simulation) ou verifie l'etat apres copie (chapitre 6.1)
# Pas de tableau associatif : bash 3.2 (celui du Mac) n'en a pas. Une
# recherche awk par chemin relatif suffit, une vingtaine d'entrees au plus.
compare_manifest() {
  local remote_manifest
  remote_manifest=$(remote_call hash-manifest "${RELPATHS[@]}")

  MANIFEST_ALL_MATCH=1
  local i=0
  for rel in "${RELPATHS[@]}"; do
    local lh="${LOCAL_HASH[$i]}"
    local rh
    rh=$(printf '%s\n' "$remote_manifest" | awk -F'\t' -v r="$rel" '$1==r{print $2; exit}')
    [ -n "$rh" ] || rh="ABSENT"
    if [ "$rh" = "ABSENT" ]; then
      info "à créer      $rel"
      MANIFEST_ALL_MATCH=0
    elif [ "$rh" = "$lh" ]; then
      info "déjà à jour  $rel"
    else
      info "à mettre à jour  $rel"
      MANIFEST_ALL_MATCH=0
    fi
    i=$((i + 1))
  done
}

# ------------------------------------------------------------------ rollback
do_rollback() {
  titre "Retour arrière — $HOTE ($ENV)"
  local out
  compute_rollback_targets
  out=$(remote_call remove-tree "${ROLLBACK_TARGETS[@]}")
  while IFS='=' read -r k v; do
    [ -n "$k" ] || continue
    if [ "$v" = "1" ]; then vert "  retiré : ${k#removed_}"; else info "absent  : ${k#removed_}"; fi
  done <<< "$out"

  if [ "$ENV" = "staging" ]; then
    out=$(remote_call remove-override)
    v=$(printf '%s\n' "$out" | sed -n 's/^removed=//p')
    if [ "$v" = "1" ]; then vert "  levier de préproduction retiré du wp-config.php"; else info "  aucun levier à retirer"; fi
  fi

  local backups; backups=$(remote_call list-backups)
  if [ -z "$backups" ]; then
    if [ "$PRE_FUNCTIONS_REQUIRE_COUNT" != "0" ]; then
      mourir "aucune sauvegarde trouvée pour $HOTE, mais functions.php porte encore la ligne ajoutée : restauration manuelle requise"
    fi
    info "aucune sauvegarde à restaurer (rien n'avait été fusionné, ou déjà propre)"
    vert "Retour arrière terminé."
    return
  fi
  local latest; latest=$(printf '%s\n' "$backups" | tail -1)
  out=$(remote_call restore-functions "$latest")
  local size; size=$(printf '%s\n' "$out" | sed -n 's/^size=//p')
  vert "  functions.php restauré depuis $latest ($size o)"
  vert "Retour arrière terminé."
}

# --------------------------------------------------------------------- apply
do_apply() {
  titre "Déploiement — $HOTE ($ENV)"

  local out backup_dir
  out=$(remote_call backup) || mourir "sauvegarde impossible, rien n'a été écrit"
  backup_dir=$(printf '%s\n' "$out" | sed -n 's/^backup_dir=//p')
  vert "  sauvegarde : $backup_dir/functions.php"

  build_local_manifest
  info "copie du mu-plugin et du thème (rsync --files-from, additif, jamais --delete, fichiers suivis par git seulement)"
  printf '%s\n' "${RELPATHS[@]}" \
    | rsync -a --files-from=- "$REPO_ROOT/" "$SSH_ALIAS:www/$HOTE/public_html/wp-content/" \
    || mourir "échec de la copie (rsync)"

  compare_manifest
  if [ "$MANIFEST_ALL_MATCH" -ne 1 ]; then
    mourir "au moins un fichier copié ne correspond pas à sa source après rsync (chapitre 6.1) : ne pas continuer"
  fi
  vert "  empreintes vérifiées, douze fichiers et plus, toutes conformes"

  local log_offset log_offset_rc
  log_offset=$(remote_call debug-log-offset); log_offset_rc=$?
  [ "$log_offset_rc" -eq 0 ] && [ -n "$log_offset" ] \
    || mourir "lecture du décalage de debug.log impossible (connexion SSH) : ne pas conclure d'une absence d'erreur avant d'avoir établi la lecture"

  out=$(remote_call merge-functions "$FUNCTIONS_BASELINE_SIZE" "$REQUIRE_LINE") \
    || mourir "fusion de functions.php refusée — voir le message ci-dessus, restaurer avec --rollback si nécessaire"
  local fsize; fsize=$(printf '%s\n' "$out" | sed -n 's/^size=//p')
  if printf '%s\n' "$out" | grep -q '^already_merged=1'; then
    vert "  functions.php déjà fusionné ($fsize o), rien à changer"
  else
    vert "  functions.php fusionné ($fsize o)"
  fi

  if [ "$ENV" = "staging" ]; then
    out=$(remote_call set-override "$OVERRIDE_HOST" "$FORCE_OVERRIDE") \
      || mourir "pose du levier de préproduction refusée — voir le message ci-dessus, ou relancer avec --forcer-override si le remplacement est voulu"
    local state; state=$(printf '%s\n' "$out" | sed -n 's/^override_state=//p')
    vert "  levier de préproduction ($state) : $OVERRIDE_HOST"
  fi

  titre "Vérification — chapitre 6"

  out=$(remote_call functions-facts "$REQUIRE_LINE")
  local vcount vsize; vcount=$(printf '%s\n' "$out" | sed -n 's/^FUNCTIONS_REQUIRE_COUNT=//p')
  vsize=$(printf '%s\n' "$out" | sed -n 's/^FUNCTIONS_SIZE=//p')
  if [ "$vcount" != "1" ]; then
    mourir "après fusion, la ligne require_once apparaît $vcount fois au lieu d'une seule"
  fi
  local exp1=$((FUNCTIONS_BASELINE_SIZE + ${#REQUIRE_LINE} + 1))
  local exp2=$((FUNCTIONS_BASELINE_SIZE + ${#REQUIRE_LINE} + 2))
  if [ "$vsize" != "$exp1" ] && [ "$vsize" != "$exp2" ]; then
    mourir "taille de functions.php après fusion suspecte : $vsize o, $exp1 ou $exp2 attendus"
  fi
  vert "  OK   functions.php : une ligne, $vsize o"

  # Redirections suivies (-L) : / redirige en 301 vers /fr/ ou /en/ selon
  # TranslatePress, sur les deux marques et les deux environnements, jamais
  # une langue figée ici (brief-correctif-verification-apparence.md). Ce
  # qu'on juge est le code de statut FINAL, jamais celui du premier saut.
  local http_code
  http_code=$(curl -s -o /dev/null -L -w '%{http_code}' --max-time 15 "https://$HOTE/" || echo "000")
  case "$http_code" in
    2??)
      vert "  OK   https://$HOTE/ répond $http_code (redirections suivies)"
      ;;
    4??|5??)
      rouge "  KO   https://$HOTE/ répond $http_code après redirections"
      mourir "le site répond en erreur après déploiement — vérifier avant de continuer, --rollback si besoin"
      ;;
    *)
      rouge "  KO   https://$HOTE/ répond $http_code après redirections"
      mourir "le site ne répond pas normalement après déploiement — vérifier avant de continuer, --rollback si besoin"
      ;;
  esac

  local tail_out tail_rc
  tail_out=$(remote_call debug-log-tail-since "$log_offset"); tail_rc=$?
  [ "$tail_rc" -eq 0 ] \
    || mourir "lecture de debug.log impossible (connexion SSH) : absence d'erreur PHP non établie, ne pas conclure à un déploiement sain"
  if printf '%s' "$tail_out" | grep -qi "PHP Fatal error"; then
    rouge "  KO   une erreur fatale PHP est apparue dans debug.log depuis le déploiement"
    printf '%s\n' "$tail_out" | grep -i "PHP Fatal error" | sed 's/^/    /' >&2
    mourir "arrêt au premier contrôle rouge — --rollback pour revenir en arrière"
  fi
  vert "  OK   aucune erreur fatale PHP dans debug.log depuis le déploiement"
  if printf '%s' "$tail_out" | grep -q '\[lme-brands\]'; then
    info "lignes [lme-brands] observées depuis le déploiement :"
    printf '%s\n' "$tail_out" | grep '\[lme-brands\]' | sed 's/^/    /'
  fi

  if [ "$ENV" = "staging" ]; then
    # Meme piege que le controle de statut ci-dessus, et c'est celui qui a
    # produit le KO du 21 septembre : / redirige en 301 vers /fr/ (ou /en/,
    # jamais une langue figee ici), le corps de cette reponse est vide, et y
    # chercher le jeton d'apparence concluait a tort a son absence. On suit
    # la redirection (-L) et on lit le code de statut FINAL dans le meme
    # appel, pour juger sur la page vraiment servie
    # (brief-correctif-verification-apparence.md). Une absence de jeton ne
    # prouve rien tant que la lecture elle-meme n'est pas etablie comme
    # valide : d'abord le code de sortie de curl, puis le statut final.
    local fetch curl_rc body_http_code body
    fetch=$(curl -s -L --max-time 15 -w '\n__ICL_STATUS__%{http_code}' "https://$HOTE/")
    curl_rc=$?
    if [ "$curl_rc" -ne 0 ]; then
      mourir "lecture de https://$HOTE/ impossible (curl code $curl_rc) : apparence non vérifiée, une absence ne prouve rien tant que la lecture n'est pas établie"
    fi
    body_http_code=$(printf '%s\n' "$fetch" | sed -n 's/^__ICL_STATUS__//p')
    body=$(printf '%s\n' "$fetch" | sed '$d')
    case "$body_http_code" in
      200)
        ;;
      4??|5??)
        rouge "  KO   https://$HOTE/ répond $body_http_code après redirections"
        mourir "apparence non vérifiable : la page finale répond en erreur, ce n'est pas un défaut d'apparence"
        ;;
      *)
        rouge "  KO   https://$HOTE/ répond $body_http_code après redirections"
        mourir "apparence non vérifiable : code de statut final inattendu, une absence de jeton ne serait pas probante"
        ;;
    esac
    if printf '%s' "$body" | grep -q -- '--srlm-'; then
      vert "  OK   l'apparence Sexcape Room est active sous le levier ($OVERRIDE_HOST)"
    else
      rouge "  KO   aucun jeton --srlm- trouvé sur https://$HOTE/ (statut final $body_http_code, lecture établie) alors que le levier est actif"
      mourir "apparence non confirmée — vérifier à l'œil avant de considérer ce déploiement terminé"
    fi
  fi

  echo
  vert "Déploiement terminé et vérifié sur $HOTE."
}

# ------------------------------------------------------------------- main
if [ "$ROLLBACK" -eq 1 ]; then
  run_preconditions
  if [ "$PRE_WP_PATH_OK" != "1" ]; then mourir "impossible de joindre $HOTE, retour arrière annulé"; fi
  do_rollback
  exit 0
fi

run_preconditions

if [ "$APPLIQUER" -eq 0 ]; then
  titre "Simulation — rien n'est écrit"
  if [ "$PRE_WP_PATH_OK" = "1" ]; then
    build_local_manifest
    compare_manifest
    if [ "$PRE_FUNCTIONS_REQUIRE_COUNT" -eq 0 ] && [ "$PRECOND_OK" -eq 1 ]; then
      info "functions.php : ajouterait la ligne — $REQUIRE_LINE"
    fi
    if [ "$ENV" = "staging" ]; then
      if [ "$PRE_OVERRIDE_LINE_PRESENT" = "1" ] && [ "$PRE_OVERRIDE_LINE_VALUE" != "$OVERRIDE_HOST" ]; then
        jaune "  le levier existant ($PRE_OVERRIDE_LINE_VALUE) diffère de --override-host ($OVERRIDE_HOST) : --forcer-override serait nécessaire"
      elif [ "$PRE_OVERRIDE_LINE_PRESENT" = "0" ]; then
        info "wp-config.php : poserait define( 'LME_BRANDS_HOST_OVERRIDE', '$OVERRIDE_HOST' );"
      fi
    fi
  fi
  echo
  if [ "$PRECOND_OK" -eq 1 ]; then
    vert "Tous les préalables sont au vert. --appliquer exécuterait ce plan."
  else
    rouge "Au moins un préalable est au rouge. --appliquer serait refusé tant que ce n'est pas corrigé."
  fi
  exit 0
fi

[ "$PRECOND_OK" -eq 1 ] || mourir "au moins un préalable est au rouge — voir ci-dessus, ce script refuse de deviner"

do_apply
