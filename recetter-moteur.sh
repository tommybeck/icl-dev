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
# ou tourner ailleurs qu'en préproduction stricte. Il ne crée ni n'annule
# aucune réservation tant que Vik Channel Manager n'est pas constaté inactif
# ou absent sur la cible, --nettoyer et --reprise compris
# (docs/briefs/constat-integrations-sortantes.md §3).
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

usage() { sed -n '2,44p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

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

  orders-from-email)
    # Après une soumission finale en erreur (500 du 24 septembre 2026 :
    # l'insertion précède l'appel à Vik Channel Manager qui plante,
    # constat-recette-vcm-inactif.md §4), la page ne porte aucun sid : la
    # réservation se retrouve par l'adresse de recette que le script vient de
    # générer, unique à la seconde (recette+<epoch>@…). id, sid, ts, status :
    # données structurelles non secrètes, comme pour order-id-from-sid.
    # Un échec de la requête sort en erreur, jamais en liste vide : une
    # absence n'est affirmée que si la base a répondu.
    COURRIEL="$3"
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    Q="SELECT id, sid, ts, status FROM sir_vikbooking_orders WHERE custmail = '$(printf '%s' "$COURRIEL" | sed "s/'/''/g")' ORDER BY id"
    ROWS=$(wp db query "$Q" --path="$BASE" --skip-column-names 2>/dev/null) \
      || { err "wp db query a échoué : présence de la réservation indéterminée"; exit 1; }
    kv NROWS "$(printf '%s\n' "$ROWS" | grep -c . || true)"
    kv ROWS "$(printf '%s' "$ROWS" | tr '\n\t' ';:')"
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

  room-brands)
    # Marque de chaque chambre du registre de lme-brands, sous la forme
    # id:empreinte_hex — la seule source de la correspondance chambre/marque,
    # jamais recopiée dans ce script. bin2hex() : même précaution que
    # resolve-brand-key contre TranslatePress.
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    wp eval '$c = lme_brands_get_config(); foreach ( array_keys( (array) $c["rooms"] ) as $id ) { $r = lme_brands_resolve_room( $c, $id ); if ( "ok" === $r["status"] ) { echo (int) $id, ":", bin2hex( $r["brand_key"] ), "\n"; } }' --path="$BASE" 2>/dev/null
    exit 0
    ;;

  room-pages)
    # Page de chaque vue roomdetails : roomid, post_id, post_name, tels que
    # Vik les tient dans sa table des shortcodes pour construire ses liens.
    # Données structurelles, non secrètes.
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    Q="SELECT JSON_UNQUOTE(JSON_EXTRACT(s.json, '\$.roomid')), s.post_id, p.post_name FROM sir_vikbooking_wpshortcodes s JOIN sir_posts p ON p.ID = s.post_id WHERE s.type = 'roomdetails' AND s.post_id > 0"
    wp db query "$Q" --path="$BASE" --skip-column-names 2>/dev/null
    exit 0
    ;;

  vcm-status)
    # État de Vik Channel Manager sur la cible : active, inactive, absent, ou
    # tout autre statut que wp-cli rapporte (active-network…). Lu par
    # `wp plugin list` avec --skip-plugins : l'état vient de l'option
    # active_plugins, sans charger aucun greffon — ni VCM lui-même, ni
    # TranslatePress, qui réécrit la sortie de wp eval
    # (constat-integrations-sortantes.md §0). Aucune valeur de configuration
    # lue : un nom de greffon et un statut.
    command -v wp >/dev/null 2>&1 || { err "wp-cli introuvable"; exit 1; }
    LISTE=$(wp plugin list --fields=name,status --format=csv --path="$BASE" --skip-plugins --skip-themes 2>/dev/null) \
      || { err "wp plugin list a échoué"; exit 1; }
    [ "$(printf '%s\n' "$LISTE" | head -1)" = "name,status" ] \
      || { err "sortie de wp plugin list inattendue : état de Vik Channel Manager indéterminé"; exit 1; }
    STATUT=$(printf '%s\n' "$LISTE" | awk -F, '$1=="vikchannelmanager"{print $2; exit}')
    kv VCM_STATUS "${STATUT:-absent}"
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

# ------------------------------------------------ Vik Channel Manager éteint
# docs/briefs/incident-preproduction-vers-plateformes-2026-09-24.md : actif sur
# la préproduction, Vik Channel Manager pousse chaque réservation d'essai vers
# Airbnb, Booking.com et Expedia, qui ferment de vraies nuits. Toute création
# ET toute annulation de réservation passe par lui (l'annulation pousse une
# disponibilité calculée sur la base de préproduction, qui ignore les
# réservations de production faites depuis la copie). Seuls « inactive » et
# « absent » passent : un statut inconnu ou illisible refuse, jamais deviné.
VCM_INACTIF_VERIFIE=0

vcm_statut_cible() {
  local out statut
  out=$(remote_call vcm-status) || return 1
  statut=$(printf '%s\n' "$out" | kv_get VCM_STATUS)
  [ -n "$statut" ] || return 1
  printf '%s' "$statut"
}

vcm_statut_acceptable() {
  [ "$1" = "inactive" ] || [ "$1" = "absent" ]
}

exiger_vcm_inactif() {
  local geste="$1" statut
  statut=$(vcm_statut_cible) || mourir "état de Vik Channel Manager illisible sur $HOTE : $geste refusé"
  vcm_statut_acceptable "$statut" \
    || mourir "Vik Channel Manager est '$statut' sur $HOTE : $geste refusé, chaque réservation y part vers les plateformes. Le désactiver (Plugins) avant de relancer."
  vert "  OK   Vik Channel Manager '$statut' sur $HOTE"
  VCM_INACTIF_VERIFIE=1
}

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

  # Refus global, même sans --appliquer : la vérification 3 crée une
  # réservation si la garde de lme-brands la laisse passer.
  local vcm
  if vcm=$(vcm_statut_cible); then
    check "Vik Channel Manager inactif (aucune réservation d'essai vers les plateformes)" \
      "$(vcm_statut_acceptable "$vcm" && echo 1 || echo 0)" "statut relevé : $vcm"
    vcm_statut_acceptable "$vcm" && VCM_INACTIF_VERIFIE=1
  else
    check "Vik Channel Manager inactif (aucune réservation d'essai vers les plateformes)" 0 "état illisible"
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
# Ce qui est mesuré : les identifiants de chambre que le composant de Vik
# propose, relevés dans son seul conteneur (`div.plugin-container`, un par
# page, établi le 24 septembre 2026) — jamais le nom d'une chambre dans la
# page entière. La préproduction est une copie de linstantcle.ch, dont le
# menu nomme les chambres sur chaque page : chercher un nom dans toute la
# page rendait le KO garanti par construction (constat-correctif-room-filter.md).
#
# Identifiants relevés dans le conteneur :
#   - champs de formulaire `roomdetail`, `roomid`, `room_ids[]`, `roomopt[]` ;
#   - résultats de recherche, `vbSelectRoom('n', 'idroom')` ;
#   - paramètres `roomid` / `roomdetail` des liens ;
#   - chaque résultat de `roomslist` (`li.room_result`), dont le lien ne porte
#     pas d'identifiant : il est rapporté à sa chambre par la table des
#     shortcodes de Vik (page d'une vue roomdetails -> son roomid), la même
#     correspondance que Vik utilise pour construire ce lien.
#
# La marque de chaque identifiant est résolue sur le serveur par le registre
# de lme-brands (lme_brands_resolve_room()), jamais par une liste tenue ici.
# Un identifiant absent du registre compte comme étranger.
#
# Témoin d'abord : la page propre, sans paramètre, doit proposer sa propre
# chambre et elle seule. Sans témoin vert, la mesure n'est pas jugée.
VERIF2_JOURS_RECHERCHE=75   # hors des dates des vérifications 3 (60) et 5-6 (90)
VERIF2_MARQUES=""           # lignes id:empreinte_hex_de_la_marque
VERIF2_PAGES_FICHIER="$ETAT_DIR/verif2-pages-vik.tsv"

read -r -d '' VERIF2_EXTRACTEUR <<'PHP_EOF'
<?php
// $argv[1] : page HTML ; $argv[2] : roomid<TAB>post_id<TAB>post_name.
// Imprime « CONTENEURS n », puis une ligne « id origine » par identifiant,
// ou « ? origine » pour un résultat qu'aucune page de chambre ne rapporte.
$pages = array();
foreach ( (array) @file( $argv[2], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $l ) {
	$c = explode( "\t", $l );
	if ( count( $c ) >= 3 && ctype_digit( $c[0] ) ) {
		$pages[ 'id:' . $c[1] ]   = $c[0];
		$pages[ 'slug:' . $c[2] ] = $c[0];
	}
}
libxml_use_internal_errors( true );
$doc = new DOMDocument();
$doc->loadHTML( '<?xml encoding="UTF-8">' . (string) @file_get_contents( $argv[1] ) );
$xp  = new DOMXPath( $doc );
$box = $xp->query( "//div[contains(concat(' ', normalize-space(@class), ' '), ' plugin-container ')]" );
echo 'CONTENEURS ', $box->length, "\n";
$out = array();
foreach ( $box as $c ) {
	foreach ( $xp->query( ".//input[@name='roomdetail' or @name='roomid' or @name='room_ids[]' or @name='roomopt[]']", $c ) as $in ) {
		$v = trim( $in->getAttribute( 'value' ) );
		if ( '' !== $v && ctype_digit( $v ) ) {
			$out[] = $v . ' champ:' . $in->getAttribute( 'name' );
		}
	}
	if ( preg_match_all( "/vbSelectRoom\\(\\s*'[0-9]+'\\s*,\\s*'([0-9]+)'\\s*\\)/", $doc->saveHTML( $c ), $m ) ) {
		foreach ( $m[1] as $v ) {
			$out[] = $v . ' resultat-recherche';
		}
	}
	foreach ( $xp->query( './/a[@href]', $c ) as $a ) {
		parse_str( (string) parse_url( $a->getAttribute( 'href' ), PHP_URL_QUERY ), $q );
		foreach ( array( 'roomid', 'roomdetail' ) as $k ) {
			if ( isset( $q[ $k ] ) && is_string( $q[ $k ] ) && ctype_digit( $q[ $k ] ) ) {
				$out[] = $q[ $k ] . ' lien:' . $k;
			}
		}
	}
	foreach ( $xp->query( ".//li[contains(concat(' ', normalize-space(@class), ' '), ' room_result ')]", $c ) as $li ) {
		$a = $xp->query( './/a[@href]', $li )->item( 0 );
		$h = $a ? $a->getAttribute( 'href' ) : '';
		parse_str( (string) parse_url( $h, PHP_URL_QUERY ), $q );
		$slug = basename( rtrim( (string) parse_url( $h, PHP_URL_PATH ), '/' ) );
		if ( isset( $q['page_id'] ) && isset( $pages[ 'id:' . $q['page_id'] ] ) ) {
			$out[] = $pages[ 'id:' . $q['page_id'] ] . ' liste:page_id';
		} elseif ( '' !== $slug && isset( $pages[ 'slug:' . $slug ] ) ) {
			$out[] = $pages[ 'slug:' . $slug ] . ' liste:page';
		} else {
			$out[] = '? liste:' . ( '' !== $h ? $h : 'sans-lien' );
		}
	}
}
echo implode( "\n", array_unique( $out ) ), "\n";
PHP_EOF

verif2_charger_references() {
  [ -n "$VERIF2_MARQUES" ] && [ -s "$VERIF2_PAGES_FICHIER" ] && return 0
  VERIF2_MARQUES=$(remote_call room-brands | grep -E '^[0-9]+:[0-9a-f]+$')
  remote_call room-pages | grep -E $'^[0-9]+\t[0-9]+\t' > "$VERIF2_PAGES_FICHIER"
  [ -n "$VERIF2_MARQUES" ] && [ -s "$VERIF2_PAGES_FICHIER" ]
}

verif2_marque_de() {
  local hex; hex=$(printf '%s\n' "$VERIF2_MARQUES" | sed -n "s/^$1://p" | head -1)
  printf '%s' "${hex:--}"
}

# verif2_classer FICHIER EMPREINTE_MARQUE -> une ligne « ETAT ids_propres ids_etrangers »
# ETAT ∈ { SANS_CONTENEUR, AUCUN, PROPRE, ETRANGER, NON_RAPPORTE }
verif2_classer() {
  local fichier="$1" attendu="$2" brut n_conteneurs ligne id propres="" etrangers="" non_rapporte=0
  brut=$(php -- "$fichier" "$VERIF2_PAGES_FICHIER" <<< "$VERIF2_EXTRACTEUR")
  n_conteneurs=$(printf '%s\n' "$brut" | sed -n 's/^CONTENEURS //p')
  if [ "${n_conteneurs:-0}" -eq 0 ]; then
    echo "SANS_CONTENEUR - -"; return
  fi
  while IFS= read -r ligne; do
    case "$ligne" in CONTENEURS*|"") continue ;; esac
    id="${ligne%% *}"
    if [ "$id" = "?" ]; then non_rapporte=1; continue; fi
    if [ "$(verif2_marque_de "$id")" = "$attendu" ]; then
      case " $propres " in *" $id "*) ;; *) propres="${propres:+$propres }$id" ;; esac
    else
      case " $etrangers " in *" $id "*) ;; *) etrangers="${etrangers:+$etrangers }$id" ;; esac
    fi
  done <<< "$brut"
  propres="${propres// /,}"
  if [ -n "$etrangers" ]; then echo "ETRANGER ${propres:--} ${etrangers// /,}"
  elif [ "$non_rapporte" -eq 1 ]; then echo "NON_RAPPORTE ${propres:--} -"
  elif [ -n "$propres" ]; then echo "PROPRE $propres -"
  else echo "AUCUN - -"
  fi
}

# verif2_lire FICHIER URL [champ=valeur ...] : GET sans champ, POST sinon.
verif2_lire() {
  local fichier="$1" url="$2"; shift 2
  if [ $# -eq 0 ]; then
    curl -s -L --max-time 30 -o "$fichier" "$url"
  else
    local -a args=(); local champ
    for champ in "$@"; do args+=(--data-urlencode "$champ"); done
    curl -s -L --max-time 30 -X POST "${args[@]}" -o "$fichier" "$url"
  fi
}

verif2_vues_publiques() {
  local marque="$1" page_propre="$2" room_propre="$3" room_etranger="$4"
  titre "Vérification 2 — quatre vues publiques ($marque), chambre étrangère #$room_etranger"

  if ! verif2_charger_references; then
    rouge "  KO   correspondances du registre ou des pages Vik illisibles : mesure impossible"
    noter "2  ??  ($marque) mesure impossible, registre ou table des shortcodes illisible"
    return
  fi

  local attendu; attendu=$(printf '%s' "$marque" | od -An -tx1 | tr -d ' \n')
  local fichier="$ETAT_DIR/verif2-page.html"

  # URL finale de la page propre : WordPress redirige /en/<slug>/ vers son
  # chemin canonique par un 301, que curl -L suivrait en GET et qui ferait
  # perdre le POST de la recherche.
  local page
  page=$(curl -s -L --max-time 30 -o /dev/null -w '%{url_effective}' "https://$HOTE/en/$page_propre/")
  page="${page%%\?*}"

  # Témoin : la page sans paramètre propose sa chambre, et elle seule.
  local temoin etat propres etrangers
  verif2_lire "$fichier" "${page}?_r=t-$$"
  temoin=$(verif2_classer "$fichier" "$attendu")
  read -r etat propres etrangers <<< "$temoin"
  if [ "$etat" != "PROPRE" ] || [ "$propres" != "$room_propre" ]; then
    rouge "  ??   témoin : la page propre sans paramètre ne se mesure pas comme attendu ($etat, propres=$propres, étrangers=$etrangers, chambre attendue #$room_propre)"
    noter "2  ??  ($marque) témoin non conforme ($etat) : mesure non jugée"
    return
  fi
  vert "  OK   témoin : page propre, chambre #$room_propre et elle seule"

  local ci co
  ci=$(iso_vers_ddmmyyyy "$(date_dans_jours "$VERIF2_JOURS_RECHERCHE")")
  co=$(iso_vers_ddmmyyyy "$(date_dans_jours $((VERIF2_JOURS_RECHERCHE + 1)))")

  local -a vues=(
    "roomdetails, roomid étranger|view=roomdetails&roomid=$room_etranger|"
    "availability, room_ids étranger|view=availability&room_ids=$room_etranger|"
    "availability, sans sélection|view=availability|"
    "roomslist, category_id=$room_etranger|view=roomslist&category_id=$room_etranger|"
    "roomslist, sans sélection|view=roomslist|"
    "search, roomdetail étranger||option=com_vikbooking task=search checkindate=$ci checkinh=15 checkinm=0 checkoutdate=$co checkouth=11 checkoutm=0 roomsnum=1 adults[]=2 roomdetail=$room_etranger"
  )
  local entry libelle requete champs url all_ok=1 inconstant=0 non_mesure=0 premiere seconde
  for entry in "${vues[@]}"; do
    libelle="${entry%%|*}"; requete="${entry#*|}"; champs="${requete#*|}"; requete="${requete%%|*}"
    url="${page}?${requete:+$requete&}_r="

    # Deux lectures, jamais une seule : règle absolue n°4 de CLAUDE.md. Deux
    # résultats différents à quelques secondes d'écart pointent vers un cache,
    # pas vers ce greffon, et ne se rapportent pas comme un défaut.
    local -a champs_post=()
    [ -n "$champs" ] && read -r -a champs_post <<< "$champs"
    verif2_lire "$fichier" "${url}1-$$" ${champs_post[@]+"${champs_post[@]}"}; premiere=$(verif2_classer "$fichier" "$attendu")
    sleep 2
    verif2_lire "$fichier" "${url}2-$$" ${champs_post[@]+"${champs_post[@]}"}; seconde=$(verif2_classer "$fichier" "$attendu")

    read -r etat propres etrangers <<< "$premiere"
    if [ "$premiere" != "$seconde" ]; then
      jaune "  ??   $libelle : deux lectures discordantes ($premiere / $seconde)"
      jaune "       purger NitroPack puis le cache dynamique SiteGround et rejouer (règle absolue n°4)"
      inconstant=1
    elif [ "$etat" = "ETRANGER" ]; then
      rouge "  KO   $libelle : chambre(s) étrangère(s) #${etrangers//,/, #} proposée(s) par Vik (propres : $propres)"
      all_ok=0
    elif [ "$etat" = "PROPRE" ]; then
      vert "  OK   $libelle : Vik ne propose que #${propres//,/, #}"
    elif [ "$etat" = "AUCUN" ] && [ -z "$champs" ]; then
      vert "  OK   $libelle : aucune chambre proposée"
    else
      # SANS_CONTENEUR, NON_RAPPORTE, ou une recherche sans aucun résultat :
      # rien d'étranger n'a été vu, mais rien de propre non plus — la vue
      # n'est pas mesurée, et ne compte pas comme OK.
      jaune "  ??   $libelle : non mesurée ($etat)"
      non_mesure=1
    fi
  done

  if [ "$all_ok" -eq 0 ]; then
    noter "2  KO  au moins une vue publique ($marque) propose la chambre étrangère #$room_etranger, de façon reproductible"
  elif [ "$inconstant" -eq 1 ]; then
    noter "2  ??  ($marque) au moins une vue inconstante entre deux lectures — purger les caches et rejouer avant conclusion"
  elif [ "$non_mesure" -eq 1 ]; then
    noter "2  ??  ($marque) aucune fuite vue, mais au moins une vue non mesurée"
  else
    noter "2  OK  quatre vues publiques ($marque) : Vik ne propose aucune chambre étrangère"
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
  exiger_vcm_inactif "le nettoyage (annulations)"
  nettoyer_registre
  exit 0
fi

if [ -n "$REPRISE_IDORDER" ]; then
  OVERRIDE_RESTORE_NEEDED=0
  exiger_vcm_inactif "la reprise (rappel puis annulation)"
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
  verif2_vues_publiques "$PASSE" "$PAGE_PROPRE" "$ROOM_ACTIVE" "$ROOM_ETRANGER"
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
