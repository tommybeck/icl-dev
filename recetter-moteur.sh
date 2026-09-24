#!/bin/bash
#
# recetter-moteur.sh
#
# Recette automatisée du moteur de réservation Sexcape Room, décrite dans
# docs/briefs/brief-recette-automatisee.md et le chapitre « La recette du
# moteur » de docs/briefs/plan-de-marche.md. Mêmes conventions que
# deployer-moteur.sh : préalables refusants, sortie en erreur au premier
# contrôle rouge, rapport lisible à la fin.
#
# Ce script ne déploie rien. Il tourne APRÈS un déploiement réussi
# (deployer-moteur.sh), sur la préproduction, jamais en production.
#
# Usage :
#   ./recetter-moteur.sh --hote DOMAINE [options]
#
# Options :
#   --hote DOMAINE          hôte de préproduction ciblé, ex. staging13.linstantcle.ch
#                           (obligatoire)
#   --ssh ALIAS             alias SSH (défaut : sg-linstantcle)
#   --appliquer             mène aussi les vérifications 5 et 6 (crée de vraies
#                           réservations d'essai jusqu'à Stripe Checkout) et,
#                           après --reprise, la vérification 7. Sans cette
#                           option : seules les vérifications 1, 2, 3, 4 et 8
#                           tournent — aucune ne crée quoi que ce soit.
#   --reprise IDORDER       reprend une réservation d'essai laissée en attente
#                           à la redirection Stripe (voir chapitre 4 du brief) :
#                           relit la confirmation, la transcription d'enveloppe
#                           (vérification 5), les journaux de paiement
#                           (vérification 6), déclenche le rappel avant séjour
#                           (vérification 7), puis annule la réservation.
#   --nettoyer              annule, par le contrôleur de Vik (task=docancelbooking),
#                           jamais par SQL, toute réservation d'essai encore
#                           ouverte dans le registre local pour cet hôte — y
#                           compris après un échec. Ne relance aucune vérification.
#   -h, --help              cette aide
#
# Ce que ce script ne fait jamais : lire une clé Stripe (secrète ou publiable
# au-delà de son préfixe), écrire en base par SQL, déployer quoi que ce soit,
# ou tourner ailleurs qu'en préproduction stricte.
#
set -u

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

HOTE=""
SSH_ALIAS="sg-linstantcle"
APPLIQUER=0
NETTOYER=0
REPRISE_IDORDER=""

# Identité des réservations d'essai : reconnaissable au premier coup d'œil
# dans l'administration de Vik, jamais confondue avec un vrai client
# (chapitre 3 du brief).
MARQUAGE_PRENOM="RECETTE"
MARQUAGE_NOM="AUTOMATISEE - NE PAS TRAITER"
MARQUAGE_EMAIL_DOMAINE="recette-automatisee.icl-dev.invalid"
MARQUAGE_TELEPHONE="0000000000"
MARQUAGE_RAISON_ANNULATION="Réservation d'essai de recetter-moteur.sh, jamais un vrai client."

# Chambres de test utilisées par ce script, tirées de mu-plugins/lme-brands/config/brands.php
# (registre des chambres — jamais une seconde liste tenue à la main ici pour
# la correspondance marque/chambre : seuls les identifiants numériques
# nécessaires au parcours HTTP sont repris, la marque de chacun n'est jamais
# recalculée ni supposée par ce script).
#
#   2  L'Aparté             linstantcle   page /l-aparte/
#   4  Le Boudoir du Désir  sexcaperoom   page /le-boudoir-du-desir/
#   5  chambre de test      linstantcle   avail=0, aucune page dédiée
#   6  chambre de test      sexcaperoom   avail=0, aucune page dédiée
ROOM_LINSTANTCLE_ACTIVE=2
ROOM_LINSTANTCLE_ACTIVE_SLUG="l-aparte"
ROOM_SEXCAPEROOM_ACTIVE=4
ROOM_SEXCAPEROOM_ACTIVE_SLUG="le-boudoir-du-desir"
ROOM_LINSTANTCLE_DISABLED=5
ROOM_SEXCAPEROOM_DISABLED=6

OVERRIDE_HOST_SEXCAPEROOM="reservation.sexcaperoom.ch"
OVERRIDE_HOST_LINSTANTCLE="linstantcle.ch"

rouge() { printf '\033[31m%s\033[0m\n' "$*"; }
vert()  { printf '\033[32m%s\033[0m\n' "$*"; }
jaune() { printf '\033[33m%s\033[0m\n' "$*"; }
info()  { printf '  %s\n' "$*"; }
titre() { printf '\n== %s ==\n' "$*"; }
mourir(){ rouge "ERREUR : $*"; exit 1; }

usage() { sed -n '2,40p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

while [ $# -gt 0 ]; do
  case "$1" in
    --hote)            HOTE="${2:-}"; shift 2 ;;
    --ssh)             SSH_ALIAS="${2:-}"; shift 2 ;;
    --appliquer)       APPLIQUER=1; shift ;;
    --reprise)         REPRISE_IDORDER="${2:-}"; shift 2 ;;
    --nettoyer)        NETTOYER=1; shift ;;
    -h|--help)         usage; exit 0 ;;
    *) mourir "option inconnue : $1 (--help pour l'usage)" ;;
  esac
done

[ -n "$HOTE" ] || mourir "--hote est obligatoire : le domaine de préproduction cible ne se devine pas"

command -v ssh >/dev/null || mourir "ssh introuvable"
command -v curl >/dev/null || mourir "curl introuvable"
command -v php >/dev/null || mourir "php introuvable (nécessaire en local pour lire la liste des hôtes de production de lme-mail-guard, source unique)"

# --------------------------------------------------------------- état local
ETAT_DIR="$HOME/.icl-dev-recette/$HOTE"
mkdir -p "$ETAT_DIR" || mourir "impossible de créer $ETAT_DIR"
REGISTRE="$ETAT_DIR/reservations.tsv"
touch "$REGISTRE"
COOKIES="$ETAT_DIR/cookies.txt"

# Colonnes du registre : idorder sid ts marque room_id statut horodatage
# statut ∈ { en_attente_reprise, a_nettoyer, nettoyee, echouee }
registre_ajoute() {
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$1" "$2" "$3" "$4" "$5" "$6" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$REGISTRE"
}

registre_maj_statut() {
  local idorder="$1" nouveau="$2" tmp
  tmp="$(mktemp)"
  awk -F'\t' -v id="$idorder" -v st="$nouveau" 'BEGIN{OFS="\t"} $1==id{$6=st} {print}' "$REGISTRE" > "$tmp" && mv "$tmp" "$REGISTRE"
}

registre_ligne() {
  awk -F'\t' -v id="$1" '$1==id{print; exit}' "$REGISTRE"
}

# ------------------------------------------------- hôtes de production (source unique)
# Jamais une seconde liste tenue à la main ici : lue directement dans
# mu-plugins/lme-mail-guard/includes/core.php, la même que le greffon
# applique réellement. Un hôte de production de lme-mail-guard est par
# construction un hôte de production de cette installation.
prod_hosts_lme_mail_guard() {
  php -r "
    require '$REPO_ROOT/mu-plugins/lme-mail-guard/includes/core.php';
    foreach ( lme_mail_guard_default_production_hosts() as \$h ) { echo strtolower(\$h), \"\n\"; }
  "
}

hote_est_production() {
  local h; h="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')"
  while IFS= read -r prod; do
    [ -n "$prod" ] || continue
    [ "$h" = "$prod" ] && return 0
  done < <(prod_hosts_lme_mail_guard)
  return 1
}

# --------------------------------------------------------------- remote helper
# Même principe que deployer-moteur.sh : un seul script distant, plusieurs
# modes, aucune décision de politique — il ne fait que constater ou exécuter
# un geste borné. Toute décision « on continue ou on refuse » reste locale.
read -r -d '' REMOTE_HELPER <<'REMOTE_HELPER_EOF'
set -u
err() { printf '%s\n' "$*" >&2; }
kv()  { printf '%s=%s\n' "$1" "$2"; }

MODE="${1:-}"; HOTE="${2:-}"
BASE="$HOME/www/$HOTE/public_html"

case "$MODE" in

  env-type)
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    ENV_HEX=$(wp eval 'echo bin2hex(wp_get_environment_type());' --path="$BASE" 2>/dev/null)
    kv ENV_TYPE_HEX "${ENV_HEX:-}"
    exit 0
    ;;

  get-override)
    WPCONFIG="$BASE/wp-config.php"
    [ -f "$WPCONFIG" ] || { err "wp-config.php introuvable"; exit 1; }
    LIGNE=$(grep -F "define( 'LME_BRANDS_HOST_OVERRIDE'," "$WPCONFIG" 2>/dev/null || true)
    if [ -n "$LIGNE" ]; then
      kv PRESENT 1
      kv VALEUR "$(printf '%s' "$LIGNE" | sed -n "s/.*define( 'LME_BRANDS_HOST_OVERRIDE', *'\([^']*\)'.*/\1/p")"
    else
      kv PRESENT 0
      kv VALEUR -
    fi
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
        exit 0
      fi
      if [ "$FORCE" != "1" ]; then
        err "le levier est déjà posé avec une autre valeur ($CUR_VAL) : --forcer-override requis"
        exit 1
      fi
      ESC=$(printf '%s' "$OVERRIDE_HOST" | sed 's/[&/\]/\\&/g')
      sed -i "s/define( 'LME_BRANDS_HOST_OVERRIDE', *'[^']*'/define( 'LME_BRANDS_HOST_OVERRIDE', '$ESC'/" "$WPCONFIG"
      kv override_state replaced
      exit 0
    fi
    ANCHOR=$(grep -n -F -x "require_once ABSPATH . 'wp-settings.php';" "$WPCONFIG" | cut -d: -f1)
    NANCHOR=$(printf '%s\n' "$ANCHOR" | grep -c . || true)
    if [ -z "$ANCHOR" ] || [ "$NANCHOR" -ne 1 ]; then
      err "point d'ancrage introuvable ou ambigu ($NANCHOR occurrence(s)) : insertion refusée"
      exit 1
    fi
    sed -i "${ANCHOR}i define( 'LME_BRANDS_HOST_OVERRIDE', '$OVERRIDE_HOST' );" "$WPCONFIG"
    kv override_state inserted
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

  resolve-brand-key)
    # Appelle directement le registre de lme-brands, sans requête HTTP : sous
    # le levier de préproduction, lme_brands_resolve_effective_http_host()
    # force l'hôte effectif même hors contexte HTTP (wp-cli). bin2hex() :
    # même précaution que le reste de ce chantier contre TranslatePress, qui
    # traduit jusqu'à la sortie de wp eval (constat-script-deploiement.md 3.1).
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    HEX=$(wp eval 'echo bin2hex((string) lme_brands_current_request_brand_key());' --path="$BASE" 2>/dev/null)
    kv BRAND_KEY_HEX "${HEX:-}"
    exit 0
    ;;

  resolve-brand-by-host)
    # Hôte arbitraire, indépendant du levier : teste directement
    # lme_brands_resolve_brand_by_host() sur un hôte qui n'existe dans
    # aucune marque du registre.
    HOTE_TEST="$3"
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    HEX=$(wp eval --skip-plugins=0 "echo bin2hex((string) lme_brands_resolve_brand_by_host_cached('$HOTE_TEST'));" --path="$BASE" 2>/dev/null)
    kv BRAND_KEY_HEX "${HEX:-}"
    exit 0
    ;;

  cronjob-id)
    # Identifiant de la tâche planifiée dont le fichier de classe correspond
    # au motif donné (ex. 'email_reminder'), via wp db query — identifiants
    # de site utilisés par wp-cli lui-même, jamais lus ni manipulés par ce
    # script (même choix que deployer-moteur.sh §3.2). Donnée structurelle
    # non secrète (un nom de fichier de classe et un entier), jamais une
    # valeur de configuration au sens de la règle absolue n°2.
    MOTIF="$3"
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    Q="SELECT id, class_file FROM sir_vikbooking_cronjobs WHERE class_file LIKE '%${MOTIF}%'"
    ROWS=$(wp db query "$Q" --path="$BASE" --skip-column-names 2>/dev/null)
    kv ROWS "$(printf '%s' "$ROWS" | tr '\n\t' ';:')"
    exit 0
    ;;

  order-id-from-sid)
    # sid identifie une commande de façon unique (colonne indexée par Vik),
    # mais docancelbooking() exige aussi idorder : jamais deviné depuis une
    # page HTML (aucun champ visible ne le porte de façon fiable, établi le
    # 23 septembre 2026 après un premier essai qui laissait ce champ vide
    # dans le registre) — relu ici par son seul identifiant technique, id et
    # sid, une correspondance structurelle non secrète, comme le préfixe
    # Stripe l'est déjà ailleurs dans ce fichier.
    SID="$3"
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    Q="SELECT id FROM sir_vikbooking_orders WHERE sid = '$(printf '%s' "$SID" | sed "s/'/''/g")'"
    ID=$(wp db query "$Q" --path="$BASE" --skip-column-names 2>/dev/null)
    kv IDORDER "${ID:-}"
    exit 0
    ;;

  cron-run)
    HOOK="$3"
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    OUT=$(wp cron event run "$HOOK" --path="$BASE" 2>&1)
    RC=$?
    kv RC "$RC"
    kv OUT "$(printf '%s' "$OUT" | tr '\n' '|')"
    exit 0
    ;;

  envelope-log-offset)
    LOG="$BASE/wp-content/lme-mail-guard-envelopes.log"
    [ -f "$LOG" ] && wc -c < "$LOG" | tr -d ' ' || echo 0
    exit 0
    ;;

  envelope-log-tail-since)
    OFFSET="$3"
    LOG="$BASE/wp-content/lme-mail-guard-envelopes.log"
    [ -f "$LOG" ] && tail -c +"$((OFFSET + 1))" "$LOG"
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

  vikstripe-test-keys)
    # Même geste que deployer-moteur.sh : LEFT(...,8) posé par la requête SQL
    # elle-même, jamais la clé, jamais son chargement en mémoire côté script.
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    Q="SELECT LEFT(JSON_UNQUOTE(JSON_EXTRACT(params,'\$.secretkey')),8) FROM sir_vikbooking_gpayments WHERE file LIKE '%stripe%' AND published=1;"
    ROWS=$(wp db query "$Q" --path="$BASE" --skip-column-names 2>/dev/null)
    NROWS=$(printf '%s\n' "$ROWS" | grep -c . || true)
    kv PUBLISHED_COUNT "${NROWS:-0}"
    if [ "${NROWS:-0}" -eq 1 ]; then
      kv SECRET_PREFIX "$(printf '%s' "$ROWS" | tr -d '\n\r')"
    else
      kv SECRET_PREFIX INCONNU
    fi
    # skipbtn : réglage de la passerelle, structurel et non secret (booléen),
    # relu par sa seule clé nommée — jamais le reste de params. C'est le
    # paramètre VikStripe « Auto-redirect » (wp-vikstripe/stripe.php:233,
    # options inversées, 1 => No) : il ne contourne pas Stripe, il choisit
    # entre une redirection JavaScript automatique et le clic d'un bouton
    # PAY NOW — sans effet sur ce script, qui ne clique aucun bouton et
    # relève le lien de paiement directement dans le HTML (chapitre 3.3 de
    # constat-recette-automatisee.md, corrigé le 24 septembre 2026).
    Q2="SELECT JSON_UNQUOTE(JSON_EXTRACT(params,'\$.skipbtn')) FROM sir_vikbooking_gpayments WHERE file LIKE '%stripe%' AND published=1;"
    SKIPBTN=$(wp db query "$Q2" --path="$BASE" --skip-column-names 2>/dev/null | tr -d '\n\r')
    kv SKIPBTN "${SKIPBTN:-INCONNU}"
    exit 0
    ;;

  *)
    err "mode distant inconnu : $MODE"
    exit 1
    ;;
esac
REMOTE_HELPER_EOF

remote_call() {
  local mode="$1"; shift
  local quoted; quoted=$(printf '%q ' "$mode" "$HOTE" "$@")
  ssh "$SSH_ALIAS" "bash -s -- $quoted" <<< "$REMOTE_HELPER"
}

kv_get() {
  # kv_get CLE <<< "$out" : lit une ligne CLE=valeur produite par remote_call.
  local cle="$1"
  sed -n "s/^${cle}=//p"
}

expected_env_hex() { printf '%s' "$1" | od -An -tx1 | tr -d ' \n'; }

# ---------------------------------------------------------- préalables
PRECOND_OK=1
check() {
  if [ "$2" = "1" ]; then vert "  OK   $1${3:+ — $3}"
  else rouge "  KO   $1${3:+ — $3}"; PRECOND_OK=0
  fi
}

run_preconditions() {
  titre "Préalables — $HOTE"

  hote_est_production "$HOTE" \
    && { rouge "  KO   $HOTE est un hôte de production de lme-mail-guard : ce script refuse d'y tourner"; PRECOND_OK=0; } \
    || vert "  OK   $HOTE n'est pas un hôte de production connu de lme-mail-guard"

  local out env_hex exp_hex
  out=$(remote_call env-type) || { rouge "  KO   connexion ou wp-cli indisponible sur $HOTE"; PRECOND_OK=0; return; }
  env_hex=$(printf '%s\n' "$out" | kv_get ENV_TYPE_HEX)
  exp_hex=$(expected_env_hex "staging")
  check "wp_get_environment_type() == 'staging'" "$([ "$env_hex" = "$exp_hex" ] && echo 1 || echo 0)" "empreinte relevée $env_hex, attendue $exp_hex"

  if [ "$PRECOND_OK" -ne 1 ]; then
    return
  fi

  out=$(remote_call vikstripe-test-keys)
  local prefix count
  prefix=$(printf '%s\n' "$out" | kv_get SECRET_PREFIX)
  count=$(printf '%s\n' "$out" | kv_get PUBLISHED_COUNT)
  check "VikStripe publié en clés de test (sk_test_)" "$([ "$prefix" = "sk_test_" ] && echo 1 || echo 0)" "préfixe relevé : $prefix (passerelle(s) publiée(s) : $count)"
  VIKSTRIPE_TEST_KEYS_OK=$([ "$prefix" = "sk_test_" ] && echo 1 || echo 0)

  local skipbtn; skipbtn=$(printf '%s\n' "$out" | kv_get SKIPBTN)
  if [ "$skipbtn" = "1" ]; then
    info "  passerelle Stripe publiée en 'skipbtn=1' (Auto-redirect: No) : le client verra le bouton PAY NOW plutôt qu'une redirection JavaScript automatique — sans effet sur ce script, qui relève le lien Stripe Checkout dans le HTML plutôt que de suivre ce bouton (chapitre 3.3 du constat)"
  fi
}
VIKSTRIPE_TEST_KEYS_OK=0

# --------------------------------------------------------- levier (override)
ORIGINAL_OVERRIDE_PRESENT=0
ORIGINAL_OVERRIDE_VALUE=""
OVERRIDE_RESTORE_NEEDED=0

capture_override_original() {
  local out
  out=$(remote_call get-override) || mourir "lecture du levier impossible : ne pas continuer sans connaître la valeur d'entrée à restaurer"
  ORIGINAL_OVERRIDE_PRESENT=$(printf '%s\n' "$out" | kv_get PRESENT)
  ORIGINAL_OVERRIDE_VALUE=$(printf '%s\n' "$out" | kv_get VALEUR)
  info "levier à l'entrée : présent=$ORIGINAL_OVERRIDE_PRESENT valeur=$ORIGINAL_OVERRIDE_VALUE"
}

set_override() {
  # Toujours en force (1) : ce script possède le levier pour la durée de son
  # exécution, capture sa valeur d'entrée dans capture_override_original() et
  # la restaure quoi qu'il arrive (trap ci-dessous). Passer d'une passe à
  # l'autre n'est donc jamais « écraser l'essai de quelqu'un d'autre » — c'est
  # le geste que ce script existe pour faire. --forcer-override n'a de sens
  # que pour deployer-moteur.sh, qui laisse le levier posé entre deux
  # invocations séparées.
  local host="$1"
  remote_call set-override "$host" "1" >/dev/null \
    || mourir "pose du levier refusée pour $host"
  OVERRIDE_RESTORE_NEEDED=1
}

restore_override_on_exit() {
  local rc=$?
  if [ "$OVERRIDE_RESTORE_NEEDED" -eq 1 ]; then
    titre "Restauration du levier — quoi qu'il arrive"
    if [ "$ORIGINAL_OVERRIDE_PRESENT" = "1" ]; then
      if remote_call set-override "$ORIGINAL_OVERRIDE_VALUE" "1" >/dev/null; then
        vert "  levier restauré à sa valeur d'entrée : $ORIGINAL_OVERRIDE_VALUE"
      else
        rouge "  ÉCHEC de restauration du levier à '$ORIGINAL_OVERRIDE_VALUE' : intervention manuelle requise sur $HOTE"
      fi
    else
      if remote_call remove-override >/dev/null; then
        vert "  levier retiré (absent à l'entrée)"
      else
        rouge "  ÉCHEC de retrait du levier : intervention manuelle requise sur $HOTE"
      fi
    fi
  fi
  exit "$rc"
}
trap restore_override_on_exit EXIT

# ---------------------------------------------------------------- rapport
declare -a RAPPORT
noter() { RAPPORT+=("$1"); }

# ============================================================ vérification 1
verif1_resolution_marque() {
  local marque_attendue="$1"
  titre "Vérification 1 — résolution de marque ($marque_attendue)"

  local out hex attendu_hex
  out=$(remote_call resolve-brand-key)
  hex=$(printf '%s\n' "$out" | kv_get BRAND_KEY_HEX)
  attendu_hex=$(printf '%s' "$marque_attendue" | od -An -tx1 | tr -d ' \n')
  if [ "$hex" = "$attendu_hex" ]; then
    vert "  OK   le levier résout '$marque_attendue'"
    noter "1a OK  levier -> $marque_attendue"
  else
    rouge "  KO   le levier ne résout pas '$marque_attendue' (empreinte $hex, attendue $attendu_hex)"
    noter "1a KO  levier n'a pas résolu $marque_attendue"
  fi

  out=$(remote_call resolve-brand-by-host "hote-recette-inconnu.invalid")
  hex=$(printf '%s\n' "$out" | kv_get BRAND_KEY_HEX)
  local vide_hex; vide_hex=$(printf '%s' "" | od -An -tx1 | tr -d ' \n')
  if [ "$hex" = "$vide_hex" ]; then
    vert "  OK   un hôte inconnu ne résout aucune marque"
    noter "1b OK  hôte inconnu -> aucune marque"
  else
    rouge "  KO   un hôte inconnu résout une marque (empreinte $hex) : ne devrait jamais arriver"
    noter "1b KO  hôte inconnu a résolu une marque"
  fi
}

# ============================================================ vérification 2
# Requête sur les quatre vues publiques (search, roomdetails, availability,
# roomslist) avec un identifiant de chambre étrangère à la marque du levier
# actif, assertion d'absence du nom de la chambre étrangère dans la réponse.
verif2_fetch_contient() {
  # $1 = url, $2 = motif (sous-chaîne littérale, sans apostrophe : les noms
  # de chambre qui en portent une sont rendus tantôt avec une apostrophe
  # simple, tantôt avec l'entité HTML &#8217; selon la vue — établi le
  # 23 septembre 2026, jamais supposé. Ne jamais passer un motif contenant
  # une apostrophe à cette fonction.
  local url="$1" motif="$2" body
  body=$(curl -s -L --max-time 15 "$url")
  [[ "$body" == *"$motif"* ]]
}

verif2_vues_publiques() {
  local marque="$1" page_propre="$2" room_id_etranger="$3" motif_etranger="$4"
  titre "Vérification 2 — quatre vues publiques ($marque), chambre étrangère #$room_id_etranger ($motif_etranger)"

  local base="https://$HOTE/en/$page_propre/"
  local -a vues=(
    "roomdetails|roomid=$room_id_etranger"
    "availability|room_ids=$room_id_etranger"
    "roomslist|category_id=$room_id_etranger"
    "search|"
  )
  local entry vue param url all_ok=1 une_seule_fois=0
  for entry in "${vues[@]}"; do
    vue="${entry%%|*}"; param="${entry#*|}"
    url="${base}?view=${vue}&tmpl=component"
    [ -n "$param" ] && url="${url}&${param}"

    # Deux lectures, jamais une seule : CLAUDE.md règle absolue n°4, « le
    # cache est le premier suspect devant un comportement inexpliqué en
    # frontal ». Une réponse qui change entre deux requêtes identiques,
    # quelques secondes d'écart, avec un paramètre anti-cache différent à
    # chaque fois, pointe vers NitroPack ou le cache dynamique SiteGround
    # plutôt que vers ce greffon — et ne doit jamais se rapporter comme un
    # défaut confirmé de la garde.
    local premiere seconde
    premiere=$(verif2_fetch_contient "${url}&_r=1-$$" "$motif_etranger" && echo 1 || echo 0)
    sleep 2
    seconde=$(verif2_fetch_contient "${url}&_r=2-$$" "$motif_etranger" && echo 1 || echo 0)

    if [ "$premiere" = "0" ] && [ "$seconde" = "0" ]; then
      vert "  OK   vue $vue : aucune trace de '$motif_etranger' (deux lectures concordantes)"
    elif [ "$premiere" = "1" ] && [ "$seconde" = "1" ]; then
      rouge "  KO   vue $vue : '$motif_etranger' apparaît dans les deux lectures"
      all_ok=0
    else
      jaune "  ??   vue $vue : résultat inconstant entre deux requêtes identiques (1re=$premiere, 2e=$seconde)"
      jaune "       comportement inexpliqué en frontal : purger NitroPack puis le cache dynamique SiteGround"
      jaune "       et rejouer avant de conclure (règle absolue n°4 de CLAUDE.md) — ni compté OK ni KO ici"
      une_seule_fois=1
    fi
  done

  if [ "$une_seule_fois" -eq 1 ]; then
    noter "2  ??  ($marque) au moins une vue inconstante entre deux lectures — purger les caches et rejouer avant conclusion"
  elif [ "$all_ok" -eq 1 ]; then
    noter "2  OK  quatre vues publiques ($marque) : aucune fuite de '$motif_etranger'"
  else
    noter "2  KO  au moins une vue publique ($marque) a laissé passer '$motif_etranger', de façon reproductible"
  fi
}

# ============================================================ vérification 4
verif4_apparence() {
  local marque="$1" attendu_srlm="$2"
  titre "Vérification 4 — apparence Sexcape Room ($marque)"

  local fetch curl_rc body_http_code body
  fetch=$(curl -s -L --max-time 15 -w '\n__ICL_STATUS__%{http_code}' "https://$HOTE/")
  curl_rc=$?
  if [ "$curl_rc" -ne 0 ]; then
    rouge "  KO   lecture de https://$HOTE/ impossible (curl code $curl_rc)"
    noter "4  KO  ($marque) lecture impossible"
    return
  fi
  body_http_code=$(printf '%s\n' "$fetch" | sed -n 's/^__ICL_STATUS__//p')
  body=$(printf '%s\n' "$fetch" | sed '$d')

  case "$body_http_code" in
    2??) ;;
    *) rouge "  KO   https://$HOTE/ répond $body_http_code après redirections"; noter "4  KO  ($marque) statut final $body_http_code"; return ;;
  esac

  local has_token; [[ "$body" == *'--srlm-'* ]] && has_token=1 || has_token=0

  if [ "$attendu_srlm" = "1" ]; then
    if [ "$has_token" = "1" ]; then
      vert "  OK   jetons --srlm- présents (attendu, passe Sexcape Room)"
      noter "4  OK  ($marque) apparence Sexcape Room active"
    else
      rouge "  KO   aucun jeton --srlm- alors que le levier est actif"
      noter "4  KO  ($marque) apparence absente"
    fi
  else
    if [ "$has_token" = "0" ]; then
      vert "  OK   aucun jeton --srlm- (attendu, passe L'Instant Clé)"
      noter "4  OK  ($marque) aucune fuite d'apparence"
    else
      rouge "  KO   des jetons --srlm- apparaissent en passe L'Instant Clé : fuite d'apparence"
      noter "4  KO  ($marque) fuite d'apparence --srlm- hors Sexcape Room"
    fi
  fi
}

# ============================================================ vérification 8
verif8_liste_blanche() {
  titre "Vérification 8 — liste blanche d'hôte (G2)"
  local http_code location
  http_code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://$HOTE/wp-login.php")
  location=$(curl -s -o /dev/null -w '%{redirect_url}' --max-time 15 "https://$HOTE/wp-login.php")

  if [ "$http_code" = "302" ] && printf '%s' "$location" | grep -qi 'sexcaperoom\.ch'; then
    vert "  OK   redirection 302 vers sexcaperoom.ch"
    noter "8  OK  liste blanche active"
  else
    jaune "  KO   pas de redirection 302 vers sexcaperoom.ch (statut $http_code, cible '$location')"
    jaune "       attendu tant que G2 n'existe pas (plan-de-marche.md, chantier G) — échec annoncé, pas tu"
    noter "8  KO  attendu tant que G2 n'existe pas"
  fi
}

# ==================================================== réservations d'essai
# La création réelle (recherche -> devis -> coordonnées -> saveorder) et
# l'annulation (task=docancelbooking) vivent dans les fonctions ci-dessous,
# partagées par les vérifications 3, 5, 6 et 7.
source "$REPO_ROOT/recetter-moteur-vik.sh"

# ================================================================== main

if [ "$NETTOYER" -eq 1 ]; then
  titre "Nettoyage — $HOTE"
  OVERRIDE_RESTORE_NEEDED=0
  nettoyer_registre
  exit 0
fi

if [ -n "$REPRISE_IDORDER" ]; then
  OVERRIDE_RESTORE_NEEDED=0
  reprendre_reservation "$REPRISE_IDORDER"
  exit $?
fi

run_preconditions
[ "$PRECOND_OK" -eq 1 ] || mourir "au moins un préalable est au rouge — voir ci-dessus, ce script refuse de deviner"

capture_override_original

for PASSE in sexcaperoom linstantcle; do
  if [ "$PASSE" = "sexcaperoom" ]; then
    OVERRIDE_HOST="$OVERRIDE_HOST_SEXCAPEROOM"
    ROOM_ACTIVE="$ROOM_SEXCAPEROOM_ACTIVE"
    ROOM_ETRANGER="$ROOM_LINSTANTCLE_ACTIVE"; NOM_ETRANGER="Aparté"
    ROOM_DESACTIVEE="$ROOM_SEXCAPEROOM_DISABLED"
    PAGE_PROPRE="$ROOM_SEXCAPEROOM_ACTIVE_SLUG"
    ATTENDU_SRLM=1
  else
    OVERRIDE_HOST="$OVERRIDE_HOST_LINSTANTCLE"
    ROOM_ACTIVE="$ROOM_LINSTANTCLE_ACTIVE"
    ROOM_ETRANGER="$ROOM_SEXCAPEROOM_ACTIVE"; NOM_ETRANGER="Le Boudoir du Désir"
    ROOM_DESACTIVEE="$ROOM_LINSTANTCLE_DISABLED"
    PAGE_PROPRE="$ROOM_LINSTANTCLE_ACTIVE_SLUG"
    ATTENDU_SRLM=0
  fi

  titre "PASSE — $PASSE (levier = $OVERRIDE_HOST)"
  set_override "$OVERRIDE_HOST"

  verif1_resolution_marque "$PASSE"
  verif2_vues_publiques "$PASSE" "$PAGE_PROPRE" "$ROOM_ETRANGER" "$NOM_ETRANGER"
  verif3_garde_reservation "$PASSE" "$ROOM_ETRANGER" "$NOM_ETRANGER" "$ROOM_DESACTIVEE"
  verif4_apparence "$PASSE" "$ATTENDU_SRLM"

  if [ "$APPLIQUER" -eq 1 ]; then
    if [ "$VIKSTRIPE_TEST_KEYS_OK" -eq 1 ]; then
      verif5_6_reservation_reelle "$PASSE" "$ROOM_ACTIVE"
    else
      jaune "  vérifications 5/6 sautées : VikStripe n'est pas en clés de test sur $HOTE"
      noter "5-6 KO ($PASSE) VikStripe pas en clés de test"
    fi
  else
    info "vérifications 5/6 non menées (relancer avec --appliquer)"
  fi
done

verif8_liste_blanche

titre "Rapport"
for ligne in "${RAPPORT[@]}"; do
  case "$ligne" in
    *" OK "*|*" OK"*) vert "  $ligne" ;;
    *) rouge "  $ligne" ;;
  esac
done

if [ "$APPLIQUER" -eq 0 ]; then
  echo
  jaune "Simulation partielle : --appliquer n'a pas été passé. Vérifications 5, 6 et 7 non menées."
fi

REPRISES_EN_ATTENTE=$(awk -F'\t' '$6=="en_attente_reprise"{print $1}' "$REGISTRE")
if [ -n "$REPRISES_EN_ATTENTE" ]; then
  echo
  jaune "Réservation(s) en attente de reprise (carte de test Stripe à saisir, puis --reprise IDORDER) :"
  printf '%s\n' "$REPRISES_EN_ATTENTE" | sed 's/^/    /'
fi
