#!/bin/bash
#
# recetter-moteur-vik.sh
#
# Parcours réel de réservation VikBooking (recherche -> devis -> coordonnées
# -> soumission), et annulation par le contrôleur de Vik (task=docancelbooking,
# jamais SQL). Sourcé par recetter-moteur.sh, qui définit REGISTRE, HOTE,
# COOKIES, remote_call(), kv_get(), noter(), titre(), les fonctions de
# couleur et de sortie, et les constantes MARQUAGE_*.
#
# Établi par lecture directe de vikbooking/site/controller.php sur le
# serveur (search() : ligne 47, showprc() : ligne 53, oconfirm() : ligne 59,
# saveorder() : lignes 228-1656, notifypayment() : lignes 1825-2572,
# docancelbooking() : lignes 2740-2989), PUIS par un relevé HTML réel, non
# destructeur (recherche -> devis -> coordonnées, arrêté avant saveorder),
# contre https://staging13.linstantcle.ch/fr/reserver/ le 23 septembre 2026,
# chambre #4 (Le Boudoir du Désir), 22-23 novembre 2026. Rien n'est deviné
# ci-dessous : chaque nom de champ vient de cette page réelle.
#
# Le parcours est un enchaînement de POST sur la même page
# (https://<hôte>/fr/reserver/), chaque étape portant un champ `task`
# différent : search -> showprc -> oconfirm -> saveorder. Aucun jeton
# anti-CSRF n'est demandé par ce parcours — relevé le 23 septembre 2026,
# absent de la page oconfirm en entier : `tokenform` n'y est pas activé.
# Ce constat ne vaut que pour la réservation : docancelbooking et
# cancelrequest exigent un nonce WordPress `vikwp_nonce` sans condition
# (voir l'annulation, plus bas).
#
# Passerelle Stripe : gpayid=3 (`sir_vikbooking_gpayments.file = 'stripe'`,
# `published = 1`), nommée « Pay (now or later) » dans l'administration —
# un intitulé trompeur, vérifié par lecture directe de la table le
# 23 septembre 2026, pas par le libellé affiché. La passerelle 6, « Stripe -
# Keep it for tests », existe mais n'est pas publiée (published = 0) : ne
# jamais l'utiliser, elle n'apparaît sur aucune page réelle.
#
# Champs personnalisés du client (sir_vikbooking_custfields, lus le
# 23 septembre 2026, jamais devinés depuis le seul rendu HTML) :
#   vbf2  ORDER_NAME           prénom, obligatoire
#   vbf3  ORDER_LNAME          nom, obligatoire
#   vbf4  ORDER_EMAIL          e-mail, obligatoire
#   vbf5  ORDER_PHONE          téléphone, obligatoire
#   vbf6  ORDER_ADDRESS        adresse, obligatoire
#   vbf7  ORDER_ZIP            code postal, obligatoire
#   vbf8  ORDER_CITY           localité, obligatoire
#   vbf14 ORDER_TERMSCONDITIONS case à cocher, obligatoire, valeur "Yes"
#
# saveorder() refuse toute soumission où l'un des champs marqués
# `required = 1` ci-dessus est vide (site/controller.php, boucle sur
# sir_vikbooking_custfields puis showSelectVb('VBINSUFDATA')) et retombe
# alors, en 200, sur la vue de recherche par défaut, le motif imprimé dans
# <p class="err"> (site/helpers/error_form.php:716-717) — établi le
# 23 septembre 2026 après un premier essai réel qui a échoué exactement de
# cette façon, faute des trois derniers
# champs ci-dessus (adresse/code postal/localité), jamais devinés depuis le
# seul rendu de la page oconfirm.
#
set -u

if [ -z "${REPO_ROOT:-}" ]; then
  echo "recetter-moteur-vik.sh doit être sourcé par recetter-moteur.sh, jamais exécuté seul." >&2
  exit 1
fi

VIK_RESERVER_URL="https://$HOTE/fr/reserver/"
VIK_ITEMID_DEFAUT="844"

VBF_PRENOM_CHAMP="vbf2"
VBF_NOM_CHAMP="vbf3"
VBF_EMAIL_CHAMP="vbf4"
VBF_TELEPHONE_CHAMP="vbf5"
VBF_ADRESSE_CHAMP="vbf6"
VBF_CODEPOSTAL_CHAMP="vbf7"
VBF_LOCALITE_CHAMP="vbf8"
VBF_CONDITIONS_CHAMP="vbf14"
GPAYID_STRIPE="3"

# Dates d'essai, en jours à compter d'aujourd'hui (UTC). La vérification 3 et
# les vérifications 5-6 ne partagent JAMAIS une date : la réservation réelle
# de la passe Sexcape Room (chambre 4) pose un verrou temporaire de Vik
# (sir_vikbooking_tmplock, minuteslock = 20 minutes sur staging13) que la
# vérification 3a de la passe L'Instant Clé, qui vise la même chambre 4,
# heurtait aux mêmes dates : Vik refusait alors avant la garde
# (VBROOMBOOKEDBYOTHER), et le script rapportait un « signal ambigu ».
# Établi le 24 septembre 2026 par rejeu, constat-recette-automatisee.md §3.2 bis.
JOURS_VERIF3=60
JOURS_VERIF3_ESSAIS=7          # dates consécutives tentées si Vik refuse avant la garde
JOURS_RESERVATION_REELLE=90

date_dans_jours() {
  date -u -v+"$1"d +%Y-%m-%d 2>/dev/null || date -u -d "+$1 days" +%Y-%m-%d
}

# --------------------------------------------------------------- extraction
# Aucune bibliothèque HTML en bash : extraction par motif sur des balises
# <input> à plat, ce que rendent les gabarits Vik (un seul niveau, jamais de
# balise imbriquée dans la valeur). Les deux ordres d'attributs (name puis
# value, ou l'inverse) sont couverts.
extraire_valeur_champ() {
  local html="$1" nom="$2"
  printf '%s' "$html" | grep -oE "<input[^>]*name=[\"']${nom}[\"'][^>]*value=[\"'][^\"']*[\"'][^>]*>|<input[^>]*value=[\"'][^\"']*[\"'][^>]*name=[\"']${nom}[\"'][^>]*>" \
    | head -1 \
    | grep -oE "value=[\"'][^\"']*[\"']" \
    | head -1 \
    | sed -E "s/^value=[\"']//; s/[\"']\$//"
}

# Lien Stripe Checkout : l'attribut href du premier enfant de
# .stripe__payment__form__wrapper (wp-vikstripe/tmpl/success.html.php,
# `<a href="<?php echo $checkout_session['url']; ?>">Pay Now</a>`), lu tel
# quel dans le HTML rendu — jamais suivi, jamais cliqué, quel que soit
# `skipbtn` (constat-recette-automatisee.md, chapitre 3.3).
extraire_href_stripe_wrapper() {
  local html="$1"
  printf '%s' "$html" | tr -d '\n' \
    | grep -oE 'class="[^"]*stripe__payment__form__wrapper[^"]*"[^>]*>[[:space:]]*<a[^>]*href="[^"]*"' \
    | grep -oE 'href="[^"]*"' \
    | tail -1 \
    | sed -E 's/^href="//; s/"$//'
}

# --------------------------------------------------------------------- dates
iso_plus_jours() {
  date -u -j -v+"$2"d -f '%Y-%m-%d' "$1" +%Y-%m-%d 2>/dev/null || date -u -d "$1 + $2 days" +%Y-%m-%d
}
iso_vers_ddmmyyyy() {
  date -u -j -f '%Y-%m-%d' "$1" +%d/%m/%Y 2>/dev/null || date -u -d "$1" +%d/%m/%Y
}

# --------------------------------------------------------------- requêtes
vik_get() {
  curl -s -c "$COOKIES" -b "$COOKIES" -L --max-time 20 "$1"
}

vik_post() {
  local url="$1"; shift
  local -a args=()
  local champ
  for champ in "$@"; do
    args+=(--data-urlencode "$champ")
  done
  curl -s -c "$COOKIES" -b "$COOKIES" -L --max-time 20 -X POST "${args[@]}" "$url"
}

# N'affecte jamais VIK_LAST_CODE/VIK_LAST_URL depuis l'intérieur d'une
# fonction dont la sortie est elle-même capturée par une substitution de
# commande : ce sous-shell perdrait l'affectation à sa fermeture (piège
# rencontré et corrigé le 23 septembre 2026, première exécution réelle
# contre staging13 — voir docs/briefs/constat-recette-automatisee.md). Cette
# fonction ne fait donc qu'imprimer le corps ET les deux marqueurs sur sa
# sortie standard ; c'est à l'appelant, dans son propre contexte (jamais
# lui-même capturé), de les en extraire et de poser VIK_LAST_CODE/VIK_LAST_URL.
vik_curl_post_brut() {
  local url="$1"; shift
  local -a args=()
  local champ
  for champ in "$@"; do
    args+=(--data-urlencode "$champ")
  done
  curl -s -c "$COOKIES" -b "$COOKIES" -L --max-time 20 -X POST "${args[@]}" \
    -w '\n__ICL_CODE__%{http_code}\n__ICL_URL__%{url_effective}' "$url"
}

# À appeler ainsi, jamais autrement :
#   local brut; brut=$(vik_curl_post_brut "$url" "${champs[@]}")
#   VIK_LAST_CODE=$(printf '%s\n' "$brut" | sed -n 's/^__ICL_CODE__//p' | tail -1)
#   VIK_LAST_URL=$(printf '%s\n' "$brut" | sed -n 's/^__ICL_URL__//p' | tail -1)
#   local page; page=$(printf '%s\n' "$brut" | sed '/^__ICL_CODE__/d; /^__ICL_URL__/d')

# ============================================================== création
#
# vik_creer_reservation ROOM_ID CHECKIN(YYYY-MM-DD) NUITS
#
# Résultat dans CREATE_STATUT : created | creee_sur_erreur | refused_403 |
#                                refus_vik | ambigu | indetermine | erreur
# Sur created : CREATE_SID, CREATE_TS, CREATE_IDORDER, CREATE_REDIRECT_URL,
#               CREATE_STRIPE_HREF (lien Stripe Checkout relevé dans la page,
#               vide si absent — voir extraire_href_stripe_wrapper()).
# Sur refused_403 : CREATE_TITRE (titre de la page wp_die).
# Sur refus_vik : CREATE_MESSAGE_VIK (texte du <p class="err"> de Vik).
# Sur creee_sur_erreur : la soumission finale a échoué (code HTTP inattendu
#               ou réponse sans sid) mais la réservation est en base,
#               retrouvée par CREATE_COURRIEL : CREATE_IDORDER, CREATE_SID,
#               CREATE_TS sont posés, jamais de lien Stripe. L'appelant
#               l'inscrit au registre.
# Sur indetermine : même échec, et la relecture en base a échoué elle
#               aussi. Ni présence ni absence ne sont affirmées.
# Sur erreur : aucune réservation. CREATE_ABSENCE dit pourquoi c'est sûr :
#               « soumission finale non envoyée » ou « absence vérifiée en
#               base ».
#
# Une erreur à la soumission finale ne prouve pas l'absence de réservation :
# saveorder() insère la commande avant d'appeler Vik Channel Manager, et le
# 500 du 24 septembre 2026 est survenu après l'insertion
# (constat-recette-vcm-inactif.md §4).
vik_creer_reservation() {
  local room_id="$1" checkin_iso="$2" nuits="$3"
  CREATE_STATUT="erreur"; CREATE_SID=""; CREATE_TS=""; CREATE_IDORDER=""; CREATE_REDIRECT_URL=""; CREATE_STRIPE_HREF=""; CREATE_TITRE=""; CREATE_MESSAGE_VIK=""
  CREATE_COURRIEL=""; CREATE_ABSENCE="soumission finale non envoyée"

  # Dernier rempart, quel que soit l'appelant : jamais une création sans que
  # Vik Channel Manager ait été constaté absent de la cible pendant cette
  # exécution (recetter-moteur.sh, run_preconditions).
  [ "${VCM_ABSENT_VERIFIE:-0}" -eq 1 ] \
    || mourir "création de réservation refusée : Vik Channel Manager n'a pas été constaté absent de $HOTE"

  local checkout_iso checkin_ddmmyyyy checkout_ddmmyyyy
  checkout_iso=$(iso_plus_jours "$checkin_iso" "$nuits")
  checkin_ddmmyyyy=$(iso_vers_ddmmyyyy "$checkin_iso")
  checkout_ddmmyyyy=$(iso_vers_ddmmyyyy "$checkout_iso")

  # 1. Recherche : les dates au format dd/mm/yyyy sont converties par Vik
  #    lui-même en horodatage Unix ; on relit sa conversion plutôt que de la
  #    refaire nous-mêmes (fuseau horaire du serveur, jamais supposé).
  local -a champs_search=(
    "option=com_vikbooking" "task=search"
    "checkindate=${checkin_ddmmyyyy}" "checkinh=15" "checkinm=0"
    "checkoutdate=${checkout_ddmmyyyy}" "checkouth=11" "checkoutm=0"
    "roomsnum=1" "adults[]=1" "Itemid=${VIK_ITEMID_DEFAUT}"
  )
  local page_recherche; page_recherche=$(vik_post "$VIK_RESERVER_URL" "${champs_search[@]}")

  local checkin_ts checkout_ts itemid
  checkin_ts=$(extraire_valeur_champ "$page_recherche" "checkin")
  checkout_ts=$(extraire_valeur_champ "$page_recherche" "checkout")
  itemid=$(extraire_valeur_champ "$page_recherche" "Itemid")
  [ -n "$itemid" ] || itemid="$VIK_ITEMID_DEFAUT"

  if [ -z "$checkin_ts" ] || [ -z "$checkout_ts" ]; then
    rouge "  aucun résultat exploitable pour la chambre #$room_id aux dates demandées ($checkin_ddmmyyyy -> $checkout_ddmmyyyy) : checkin/checkout absents de la réponse"
    CREATE_STATUT="erreur"
    return 1
  fi

  # 2. Sélection de la chambre : task=showprc. roomopt[] est normalement posé
  #    par le JavaScript vbSelectRoom(index, idroom) au clic ; on pose
  #    directement sa valeur finale, ce champ n'ayant pas d'autre rôle.
  local -a champs_showprc=(
    "option=com_vikbooking" "task=showprc"
    "roomsnum=1" "roomopt[]=${room_id}" "adults[]=1"
    "days=${nuits}" "checkin=${checkin_ts}" "checkout=${checkout_ts}"
    "category_id=0" "categories=0" "Itemid=${itemid}"
  )
  local page_devis; page_devis=$(vik_post "${VIK_RESERVER_URL}?view=vikbooking" "${champs_showprc[@]}")

  local priceid1; priceid1=$(extraire_valeur_champ "$page_devis" "priceid1")

  if [ -z "$priceid1" ]; then
    rouge "  aucun tarif proposé pour la chambre #$room_id à ces dates (priceid1 absent) : chambre indisponible à ces dates, ou hors marque sous le levier actif"
    CREATE_STATUT="erreur"
    return 1
  fi

  # 3. Confirmation de la sélection : task=oconfirm, qui rend la page des
  #    coordonnées client (destinataire de saveorder).
  local -a champs_oconfirm=(
    "option=com_vikbooking" "task=oconfirm"
    "priceid1=${priceid1}" "roomid[]=${room_id}" "adults[]=1"
    "roomsnum=1" "days=${nuits}" "checkin=${checkin_ts}" "checkout=${checkout_ts}"
    "categories=0" "Itemid=${itemid}"
  )
  local page_coordonnees; page_coordonnees=$(vik_post "${VIK_RESERVER_URL}?task=oconfirm" "${champs_oconfirm[@]}")

  local prtar priceid totdue
  prtar=$(extraire_valeur_champ "$page_coordonnees" 'prtar\[\]')
  priceid=$(extraire_valeur_champ "$page_coordonnees" 'priceid\[\]')
  totdue=$(extraire_valeur_champ "$page_coordonnees" "totdue")

  if [ -z "$prtar" ] || [ -z "$priceid" ]; then
    rouge "  page de coordonnées incomplète pour la chambre #$room_id (prtar[]/priceid[] absents) — la garde de réservation a peut-être déjà agi avant ce point, ou la chambre est indisponible"
    CREATE_STATUT="erreur"
    return 1
  fi

  # 4. Soumission finale : task=saveorder. Coordonnées d'essai reconnaissables
  #    (chapitre 3 du brief), gpayid=3 (Stripe, établi par lecture directe de
  #    sir_vikbooking_gpayments, voir l'en-tête de ce fichier).
  #
  #    vbf6/vbf7/vbf8 (adresse, code postal, localité) sont obligatoires
  #    (sir_vikbooking_custfields.required = 1) : établi le 23 septembre 2026
  #    après un premier essai réel qui retombait en silence sur la vue de
  #    recherche par défaut (showSelectVb('VBINSUFDATA'),
  #    site/controller.php ~ligne 288-367) faute de ces trois champs — jamais
  #    deviné, cause confirmée par lecture directe du contrôleur.
  local courriel="recette+$(date +%s)@${MARQUAGE_EMAIL_DOMAINE}"
  CREATE_COURRIEL="$courriel"
  local -a champs_saveorder=(
    "option=com_vikbooking" "task=saveorder"
    "${VBF_PRENOM_CHAMP}=${MARQUAGE_PRENOM}"
    "${VBF_NOM_CHAMP}=${MARQUAGE_NOM}"
    "${VBF_EMAIL_CHAMP}=${courriel}"
    "${VBF_TELEPHONE_CHAMP}=${MARQUAGE_TELEPHONE}"
    "${VBF_ADRESSE_CHAMP}=Rue de la Recette 1"
    "${VBF_CODEPOSTAL_CHAMP}=1000"
    "${VBF_LOCALITE_CHAMP}=Lausanne"
    "${VBF_CONDITIONS_CHAMP}=Yes"
    "days=${nuits}" "roomsnum=1" "checkin=${checkin_ts}" "checkout=${checkout_ts}"
    "totdue=${totdue}" "prtar[]=${prtar}" "priceid[]=${priceid}"
    "rooms[]=${room_id}" "adults[]=1" "children[]=0" "optionals="
    "gpayid=${GPAYID_STRIPE}" "Itemid=${itemid}"
  )
  local brut_saveorder; brut_saveorder=$(vik_curl_post_brut "$VIK_RESERVER_URL" "${champs_saveorder[@]}")
  VIK_LAST_CODE=$(printf '%s\n' "$brut_saveorder" | sed -n 's/^__ICL_CODE__//p' | tail -1)
  VIK_LAST_URL=$(printf '%s\n' "$brut_saveorder" | sed -n 's/^__ICL_URL__//p' | tail -1)
  local page_finale; page_finale=$(printf '%s\n' "$brut_saveorder" | sed '/^__ICL_CODE__/d; /^__ICL_URL__/d')

  # Signal du refus de la garde lme-brands : wp_die(), titre exact et 403 —
  # jamais confondu avec un refus natif de Vik, qui redirige avec un message
  # flash plutôt que d'émettre un wp_die (includes/booking-guard.php).
  if [ "$VIK_LAST_CODE" = "403" ] && printf '%s' "$page_finale" | grep -qE "Réservation refusée|Booking refused"; then
    CREATE_STATUT="refused_403"
    CREATE_TITRE=$(printf '%s' "$page_finale" | grep -oE "Réservation refusée|Booking refused" | head -1)
    return 0
  fi

  if [ "$VIK_LAST_CODE" != "200" ] && [ "$VIK_LAST_CODE" != "302" ]; then
    rouge "  code HTTP inattendu à la soumission finale : $VIK_LAST_CODE"
    vik_retrouver_par_courriel
    return 1
  fi

  CREATE_SID=$(printf '%s' "$VIK_LAST_URL" | grep -oE 'sid=[0-9]+' | head -1 | cut -d= -f2)
  [ -z "$CREATE_SID" ] && CREATE_SID=$(extraire_valeur_champ "$page_finale" "sid")
  CREATE_TS=$(printf '%s' "$VIK_LAST_URL" | grep -oE 'ts=[0-9]+' | head -1 | cut -d= -f2)
  CREATE_REDIRECT_URL="$VIK_LAST_URL"
  CREATE_STRIPE_HREF=$(extraire_href_stripe_wrapper "$page_finale")

  # Refus natif de Vik : saveorder() appelle showSelectVb($err), qui rend la
  # vue de recherche en 200 et imprime le motif dans <p class="err">
  # (site/helpers/error_form.php:716-717). Le cas rencontré le 24 septembre
  # 2026 est VBROOMBOOKEDBYOTHER (site/controller.php:903-910) : la chambre
  # est tenue par le verrou temporaire (sir_vikbooking_tmplock, minuteslock
  # minutes) d'une réservation standby aux mêmes dates. Ce refus précède le
  # crochet de la garde (controller.php:1114 et :1512) : il ne dit rien
  # d'elle, et n'est donc jamais rapporté ni comme un refus de la garde, ni
  # comme un signal ambigu.
  if [ -z "$CREATE_SID" ]; then
    CREATE_MESSAGE_VIK=$(printf '%s' "$page_finale" | tr -d '\n' | grep -oE '<p class="err">[^<]*' | head -1 | sed 's/^<p class="err">//')
    if [ -n "$CREATE_MESSAGE_VIK" ]; then
      jaune "  refus natif de Vik avant la garde (code $VIK_LAST_CODE) : « $CREATE_MESSAGE_VIK »"
      CREATE_STATUT="refus_vik"
      return 1
    fi
  fi

  if [ -z "$CREATE_SID" ]; then
    jaune "  signal ambigu : ni le refus de la garde (403 + titre attendu) ni une redirection portant 'sid' n'ont été observés"
    jaune "  code $VIK_LAST_CODE, URL finale : $VIK_LAST_URL"
    vik_retrouver_par_courriel
    [ "$CREATE_STATUT" = "erreur" ] && CREATE_STATUT="ambigu"
    return 1
  fi

  # idorder n'est porté par aucun champ fiable de la page rendue (établi le
  # 23 septembre 2026 : premier essai réel, champ resté vide) — relu par sid,
  # sa seule autre clé, via wp db query en lecture seule (id, sid : donnée
  # structurelle non secrète, même discipline que le préfixe Stripe).
  local out_id; out_id=$(remote_call order-id-from-sid "$CREATE_SID")
  CREATE_IDORDER=$(printf '%s\n' "$out_id" | sed -n 's/^IDORDER=//p')
  if [ -z "$CREATE_IDORDER" ]; then
    jaune "  réservation créée (sid=$CREATE_SID) mais son idorder est introuvable par relecture (sir_vikbooking_orders) : l'annulation par task=docancelbooking en aura besoin"
  fi

  CREATE_STATUT="created"
  return 0
}

# vik_retrouver_par_courriel
#
# Après une soumission finale envoyée mais sans succès lisible : cherche en
# base la réservation portant CREATE_COURRIEL, adresse unique à la seconde.
# Pose CREATE_STATUT à creee_sur_erreur (avec CREATE_IDORDER, CREATE_SID,
# CREATE_TS), à erreur avec CREATE_ABSENCE « absence vérifiée en base », ou à
# indetermine si la base n'a pas répondu. Jamais « non créée » sans lecture.
vik_retrouver_par_courriel() {
  local out nrows rows
  if ! out=$(remote_call orders-from-email "$CREATE_COURRIEL"); then
    rouge "  relecture de sir_vikbooking_orders impossible : présence de la réservation indéterminée ($CREATE_COURRIEL)"
    CREATE_STATUT="indetermine"
    return
  fi
  nrows=$(printf '%s\n' "$out" | kv_get NROWS)
  rows=$(printf '%s\n' "$out" | kv_get ROWS)
  case "$nrows" in
    0)
      info "  aucune réservation en base pour $CREATE_COURRIEL : absence vérifiée"
      CREATE_ABSENCE="absence vérifiée en base"
      CREATE_STATUT="erreur"
      ;;
    1)
      # ROWS = id:sid:ts:status; (tabulations et fins de ligne transcrites)
      local ligne="${rows%%;*}"
      CREATE_IDORDER=$(printf '%s' "$ligne" | cut -d: -f1)
      CREATE_SID=$(printf '%s' "$ligne" | cut -d: -f2)
      CREATE_TS=$(printf '%s' "$ligne" | cut -d: -f3)
      rouge "  réservation #$CREATE_IDORDER bel et bien insérée malgré l'échec (statut Vik '$(printf '%s' "$ligne" | cut -d: -f4)')"
      CREATE_STATUT="creee_sur_erreur"
      ;;
    *)
      rouge "  $nrows réservations en base pour $CREATE_COURRIEL, une seule attendue : $rows"
      CREATE_STATUT="indetermine"
      ;;
  esac
}

# registre_ajoute_sur_erreur MARQUE ROOM_ID VERIF
#
# Inscrit au registre une réservation creee_sur_erreur, pour que --nettoyer
# la voie, et le note au rapport.
registre_ajoute_sur_erreur() {
  local marque="$1" room_id="$2" verif="$3"
  registre_ajoute "$CREATE_IDORDER" "$CREATE_SID" "$CREATE_TS" "$marque" "$room_id" "a_nettoyer"
  jaune "  #$CREATE_IDORDER inscrite au registre, à nettoyer par --nettoyer"
  noter "$verif KO ($marque) réservation #$CREATE_IDORDER insérée malgré le code $VIK_LAST_CODE, inscrite au registre à nettoyer"
}

# ============================================================== annulation
#
# Établi le 25 septembre 2026 dans Vik Booking 1.8.15 sur staging13
# (constat-reprise-1836-1837.md §3) :
#
#   - docancelbooking() (site/controller.php:2740) commence par
#     JSession::checkToken(), qui exige un nonce WordPress `vikwp_nonce`
#     (libraries/adapter/session/session.php:214-258). Sans lui : 403
#     JINVALID_TOKEN, avant toute lecture du statut. C'était le 403 des
#     réservations 1826 à 1831, confirmed comme standby.
#   - Ce nonce n'est rendu que dans les formulaires de la page de
#     réservation. Le formulaire docancelbooking n'y est rendu que si
#     canc_allowed (site/views/booking/tmpl/default.php:235 et 1203), qui
#     exige resmodcanc > 1. Or resmodcanc vaut 1 sur cette installation,
#     « Disabled, with Request » : la page ne rend que le formulaire
#     cancelrequest, qui envoie une demande à l'administrateur et n'annule
#     rien. docancelbooking refait le même calcul et refuserait de toute façon.
#   - Changer resmodcanc serait écrire dans la configuration de Vik, copiée
#     de la production : ce script ne le fait pas, et ne fabrique pas de
#     nonce. Tant que la page ne propose pas l'annulation, elle revient à
#     Thomas, dans l'administration de Vik (Bookings).
#
# vik_annuler_reservation IDORDER SID TS
#   0 : réservation relue 'cancelled' en base après le formulaire natif.
#   1 : non annulée ; ANNULATION_MOTIF dit pourquoi.
ANNULATION_MOTIF=""

# page_reservation_url SID TS : URL de la page de réservation de Vik (la page
# qui porte le shortcode booking), ou code 1 si elle est introuvable.
PAGE_RESERVATION_POST_NAME=""
page_reservation_url() {
  if [ -z "$PAGE_RESERVATION_POST_NAME" ]; then
    local out n
    out=$(remote_call booking-page) || return 1
    n=$(printf '%s\n' "$out" | kv_get NROWS)
    [ "$n" = "1" ] || return 1
    PAGE_RESERVATION_POST_NAME=$(printf '%s\n' "$out" | kv_get POST_NAME)
    [ -n "$PAGE_RESERVATION_POST_NAME" ] || return 1
  fi
  printf 'https://%s/%s/?sid=%s&ts=%s' "$HOTE" "$PAGE_RESERVATION_POST_NAME" "$1" "$2"
}

# lire_faits_reservation IDORDER : pose FAIT_STATUS, FAIT_PAYE, FAIT_TS,
# FAIT_CHECKIN, FAIT_COURRIEL (adresse de recette, ou HORS_RECETTE). Code 1
# si la base n'a pas répondu ou si la réservation n'existe pas.
lire_faits_reservation() {
  local out
  FAIT_STATUS=""; FAIT_PAYE=""; FAIT_TS=""; FAIT_CHECKIN=""; FAIT_COURRIEL=""
  out=$(remote_call order-facts "$1" "$MARQUAGE_EMAIL_DOMAINE") || return 1
  [ "$(printf '%s\n' "$out" | kv_get NROWS)" = "1" ] || return 1
  FAIT_STATUS=$(printf '%s\n' "$out" | kv_get STATUS)
  FAIT_PAYE=$(printf '%s\n' "$out" | kv_get PAYE)
  FAIT_TS=$(printf '%s\n' "$out" | kv_get TS)
  FAIT_CHECKIN=$(printf '%s\n' "$out" | kv_get CHECKIN)
  FAIT_COURRIEL=$(printf '%s\n' "$out" | kv_get COURRIEL)
}

vik_annuler_reservation() {
  local idorder="$1" sid="$2" ts="$3"
  ANNULATION_MOTIF=""

  # Même rempart que vik_creer_reservation() : une annulation pousse elle
  # aussi une disponibilité vers les plateformes si VCM est présent.
  [ "${VCM_ABSENT_VERIFIE:-0}" -eq 1 ] \
    || mourir "annulation de réservation refusée : Vik Channel Manager n'a pas été constaté absent de $HOTE"

  if ! lire_faits_reservation "$idorder"; then
    ANNULATION_MOTIF="réservation #$idorder illisible en base"
    return 1
  fi
  if [ "$FAIT_COURRIEL" = "HORS_RECETTE" ] || [ -z "$FAIT_COURRIEL" ]; then
    ANNULATION_MOTIF="#$idorder ne porte pas une adresse de recette : ce n'est pas une réservation d'essai, aucune annulation tentée"
    return 1
  fi
  if [ "$FAIT_STATUS" = "cancelled" ]; then
    return 0
  fi
  if [ "$FAIT_STATUS" != "confirmed" ]; then
    ANNULATION_MOTIF="statut '$FAIT_STATUS' : docancelbooking ne retient que les réservations confirmed, annulation manuelle dans Bookings"
    return 1
  fi

  local url page nonce itemid
  url=$(page_reservation_url "$sid" "$ts") || { ANNULATION_MOTIF="page de la vue booking de Vik introuvable"; return 1; }
  page=$(vik_get "$url")
  if ! printf '%s' "$page" | grep -q 'vbo-booking-details-head-'; then
    ANNULATION_MOTIF="la page de réservation n'affiche pas la réservation #$idorder"
    return 1
  fi
  if ! printf '%s' "$page" | grep -q 'value="docancelbooking"'; then
    if printf '%s' "$page" | grep -q 'value="cancelrequest"'; then
      ANNULATION_MOTIF="Vik ne rend pas le formulaire docancelbooking, seulement la demande cancelrequest qui n'annule rien (mode « Disabled, with Request » relevé le 25 septembre 2026), annulation manuelle dans Bookings"
    else
      ANNULATION_MOTIF="Vik ne propose aucun formulaire d'annulation sur cette page, annulation manuelle dans Bookings"
    fi
    return 1
  fi

  # Chemin natif, rendu par Vik lui-même. Non exercé à ce jour : aucune
  # page de cette installation ne rend ce formulaire. Le résultat se juge en
  # base, jamais sur un mot de la page. Vik y rembourse aussi le montant
  # payé par la passerelle (en clés de test ici, préalable vérifié) et écrit
  # au client et à l'administrateur.
  nonce=$(extraire_valeur_champ "$page" "vikwp_nonce")
  itemid=$(extraire_valeur_champ "$page" "Itemid")
  [ -n "$nonce" ] || { ANNULATION_MOTIF="formulaire d'annulation rendu sans nonce vikwp_nonce"; return 1; }
  local -a champs=(
    "option=com_vikbooking" "task=docancelbooking"
    "sid=${sid}" "idorder=${idorder}" "email=${FAIT_COURRIEL}"
    "reason=${MARQUAGE_RAISON_ANNULATION}" "vikwp_nonce=${nonce}"
  )
  [ -n "$itemid" ] && champs+=("Itemid=${itemid}")
  local brut; brut=$(vik_curl_post_brut "$url" "${champs[@]}")
  VIK_LAST_CODE=$(printf '%s\n' "$brut" | sed -n 's/^__ICL_CODE__//p' | tail -1)

  if lire_faits_reservation "$idorder" && [ "$FAIT_STATUS" = "cancelled" ]; then
    return 0
  fi
  ANNULATION_MOTIF="formulaire natif soumis (code $VIK_LAST_CODE), statut relu '${FAIT_STATUS:-illisible}'"
  return 1
}

# nettoyer_registre : rend 0 si toutes les réservations du registre sont
# annulées (ou l'étaient déjà), 2 si au moins une reste ouverte.
nettoyer_registre() {
  local idorder sid ts marque room statut horodatage traitees=0 echecs=0
  local -a lignes=()
  while IFS= read -r ligne; do lignes+=("$ligne"); done < "$REGISTRE"
  for ligne in ${lignes[@]+"${lignes[@]}"}; do
    IFS=$'\t' read -r idorder sid ts marque room statut horodatage <<< "$ligne"
    [ -n "$idorder" ] || continue
    [ "$statut" = "nettoyee" ] && continue
    info "réservation d'essai #$idorder ($marque, chambre $room, inscrite $horodatage)"
    if vik_annuler_reservation "$idorder" "$sid" "$ts"; then
      vert "  OK   #$idorder annulée (statut relu en base : cancelled)"
      registre_maj_statut "$idorder" "nettoyee"
      traitees=$((traitees + 1))
    else
      rouge "  KO   #$idorder non annulée : $ANNULATION_MOTIF"
      echecs=$((echecs + 1))
    fi
  done
  echo
  info "$traitees annulée(s), $echecs encore ouverte(s)"
  [ "$echecs" -eq 0 ] || return 2
  return 0
}

# ============================================================ vérification 3
verif3_garde_reservation() {
  local marque="$1" room_etranger="$2" nom_etranger="$3" room_desactivee="$4"
  titre "Vérification 3 — garde de réservation ($marque)"

  local dans_60j; dans_60j=$(date_dans_jours "$JOURS_VERIF3")

  # 3a ne met la garde à l'épreuve que si Vik laisse la soumission aller
  # jusqu'à elle. Un refus natif antérieur (chambre verrouillée ou occupée à
  # cette date : refus_vik, ou aucun tarif proposé : erreur) ne dit rien de la
  # garde : on passe au jour suivant, jamais on ne conclut dessus.
  local essai date_3a
  for essai in $(seq 0 $((JOURS_VERIF3_ESSAIS - 1))); do
    date_3a=$(date_dans_jours $((JOURS_VERIF3 + essai)))
    vik_creer_reservation "$room_etranger" "$date_3a" 1
    case "$CREATE_STATUT" in
      refus_vik|erreur)
        info "  3a : Vik refuse avant la garde à l'arrivée $date_3a (statut '$CREATE_STATUT') — jour suivant"
        continue ;;
    esac
    break
  done

  case "$CREATE_STATUT" in
    refused_403)
      vert "  OK   chambre étrangère (#$room_etranger, $nom_etranger) refusée : 403, '$CREATE_TITRE' (arrivée $date_3a)"
      noter "3a OK  chambre étrangère refusée (arrivée $date_3a)"
      ;;
    refus_vik|erreur)
      jaune "  3a non concluant : Vik a refusé avant la garde sur $JOURS_VERIF3_ESSAIS dates consécutives à partir de J+$JOURS_VERIF3"
      noter "3a ??  non concluant (Vik refuse avant la garde, $JOURS_VERIF3_ESSAIS dates)"
      ;;
    created)
      rouge "  KO   chambre étrangère (#$room_etranger) acceptée : réservation #$CREATE_IDORDER créée — FUITE DE MARQUE"
      registre_ajoute "$CREATE_IDORDER" "$CREATE_SID" "$CREATE_TS" "$marque" "$room_etranger" "a_nettoyer"
      noter "3a KO  chambre étrangère acceptée (réservation #$CREATE_IDORDER, à nettoyer)"
      ;;
    creee_sur_erreur)
      rouge "  KO   chambre étrangère (#$room_etranger) insérée en base (#$CREATE_IDORDER) avant l'erreur $VIK_LAST_CODE — la garde ne l'a pas arrêtée, FUITE DE MARQUE"
      registre_ajoute_sur_erreur "$marque" "$room_etranger" "3a"
      ;;
    *)
      jaune "  3a signal ambigu (statut '$CREATE_STATUT') — voir ci-dessus"
      noter "3a ??  signal ambigu"
      ;;
  esac

  vik_creer_reservation "$room_desactivee" "$dans_60j" 1
  case "$CREATE_STATUT" in
    refused_403)
      vert "  OK   chambre désactivée (#$room_desactivee) refusée : 403, '$CREATE_TITRE'"
      noter "3b OK  chambre désactivée refusée"
      ;;
    created)
      rouge "  KO   chambre désactivée (#$room_desactivee) acceptée : réservation #$CREATE_IDORDER créée"
      registre_ajoute "$CREATE_IDORDER" "$CREATE_SID" "$CREATE_TS" "$marque" "$room_desactivee" "a_nettoyer"
      noter "3b KO  chambre désactivée acceptée (réservation #$CREATE_IDORDER, à nettoyer)"
      ;;
    creee_sur_erreur)
      rouge "  KO   chambre désactivée (#$room_desactivee) insérée en base (#$CREATE_IDORDER) avant l'erreur $VIK_LAST_CODE"
      registre_ajoute_sur_erreur "$marque" "$room_desactivee" "3b"
      ;;
    erreur)
      if [ "$CREATE_ABSENCE" = "absence vérifiée en base" ]; then
        jaune "  3b non concluant : la soumission finale a répondu $VIK_LAST_CODE, aucune réservation en base (absence vérifiée)"
        noter "3b ??  non concluant (soumission finale en $VIK_LAST_CODE, absence vérifiée en base)"
        return
      fi
      jaune "  3b non concluant : la chambre désactivée n'a produit aucun tarif exploitable (probablement absente des résultats de recherche natifs de Vik, avail=0) — comportement natif, ne met pas la garde à l'épreuve par ce canal"
      noter "3b ??  non concluant (chambre désactivée absente des résultats natifs)"
      ;;
    *)
      jaune "  3b signal ambigu (statut '$CREATE_STATUT')"
      noter "3b ??  signal ambigu"
      ;;
  esac
}

# ========================================================= vérifications 5-6
verif5_6_reservation_reelle() {
  local marque="$1" room_id="$2"
  titre "Vérifications 5-6 — réservation réelle ($marque, chambre #$room_id)"

  local date_reelle; date_reelle=$(date_dans_jours "$JOURS_RESERVATION_REELLE")
  local log_offset; log_offset=$(remote_call debug-log-offset)

  vik_creer_reservation "$room_id" "$date_reelle" 1

  case "$CREATE_STATUT" in
    created) ;;
    creee_sur_erreur)
      rouge "  KO   réservation #$CREATE_IDORDER créée, mais la soumission finale a répondu $VIK_LAST_CODE : aucune page de paiement"
      registre_ajoute_sur_erreur "$marque" "$room_id" "5-6"
      return ;;
    indetermine)
      rouge "  KO   soumission finale en échec, et présence de la réservation indéterminée : chercher $CREATE_COURRIEL dans l'administration de Vik"
      noter "5-6 KO ($marque) échec de la soumission finale, réservation peut-être créée ($CREATE_COURRIEL), hors registre"
      return ;;
    *)
      rouge "  KO   la réservation d'essai n'a pas été créée (statut '$CREATE_STATUT', $CREATE_ABSENCE)"
      noter "5-6 KO ($marque) réservation non créée, $CREATE_ABSENCE"
      return ;;
  esac

  vert "  réservation #$CREATE_IDORDER créée (sid=$CREATE_SID), en attente de paiement"
  registre_ajoute "$CREATE_IDORDER" "$CREATE_SID" "$CREATE_TS" "$marque" "$room_id" "en_attente_reprise"

  # Vérification 6, première moitié : ce que nous envoyons à Stripe.
  # payment-brand.php ne journalise qu'en cas d'erreur, jamais de ligne de
  # succès (mu-plugins/lme-brands/includes/payment-brand.php) : l'absence de
  # ces trois codes dans la fenêtre de ce test est le signal positif
  # disponible sans lire Stripe (réserve documentée du brief, chapitre 3).
  local tail; tail=$(remote_call debug-log-tail-since "$log_offset")
  if printf '%s' "$tail" | grep -qE '\[lme-brands\].*(payment_brand_undetermined|payment_object_unexpected|payment_booking_id_unreadable)'; then
    rouge "  KO   payment-brand.php a journalisé une erreur pendant cette réservation :"
    printf '%s' "$tail" | grep -E '\[lme-brands\].*(payment_brand_undetermined|payment_object_unexpected|payment_booking_id_unreadable)' | sed 's/^/    /'
    noter "6a KO ($marque) erreur payment-brand.php"
  else
    vert "  OK   aucune erreur payment-brand.php journalisée"
    noter "6a OK  ($marque) aucune erreur payment-brand.php"
  fi

  # Vérification 6, seconde moitié : le lien de Stripe Checkout, relevé dans
  # le HTML de la page qui porte le bouton PAY NOW — l'attribut href du
  # premier enfant de .stripe__payment__form__wrapper
  # (wp-vikstripe/tmpl/success.html.php), jamais suivi par ce script : un
  # parcours en curl n'exécute aucun JavaScript et ne clique aucun bouton,
  # que la passerelle publiée soit en 'skipbtn=1' (Auto-redirect: No,
  # stripe.php:233) ou non — sans effet sur ce que cette page rend, seulement
  # sur ce qu'un navigateur réel en ferait (stripe.php:549). Le préalable
  # VIKSTRIPE_TEST_KEYS_OK (chapitre 2.1) garantit déjà, par le seul préfixe,
  # que ce lien mène à des clés de test avant même de tenter une réservation.
  if printf '%s' "$CREATE_STRIPE_HREF" | grep -qiE '^https://checkout\.stripe\.com/'; then
    vert "  OK   lien Stripe Checkout relevé dans la page : $CREATE_STRIPE_HREF"
    noter "6b OK  ($marque) lien Stripe Checkout relevé dans la page"
    echo
    jaune "  ÉTAPE HUMAINE — Stripe Checkout, carte de test 4242 4242 4242 4242 :"
    jaune "  $CREATE_STRIPE_HREF"
    jaune "  Une fois la carte saisie et validée : ./recetter-moteur.sh --hote $HOTE --reprise $CREATE_IDORDER"
  else
    rouge "  KO   aucun lien checkout.stripe.com trouvé dans la page (.stripe__payment__form__wrapper absent, ou href inattendu)"
    jaune "  valeur relevée : '${CREATE_STRIPE_HREF:-vide}'"
    noter "6b KO ($marque) lien Stripe Checkout introuvable dans la page"
    noter "5  ??  ($marque) non concluant : pas de lien Stripe Checkout à rendre à l'humain"
    noter "7  ??  ($marque) non concluant : pas de lien Stripe Checkout à rendre à l'humain"
    registre_maj_statut "$CREATE_IDORDER" "a_nettoyer"
    echo
    jaune "  Réservation #$CREATE_IDORDER (sid=$CREATE_SID) laissée en 'standby' sur $HOTE, marquée dans Vik (« ${MARQUAGE_PRENOM} ${MARQUAGE_NOM} »)."
    jaune "  À examiner à l'œil avant de rejouer — relancer avec --nettoyer une fois la cause établie."
  fi
}

# ================================================================= reprise

ts_vers_iso() {
  date -u -r "$1" +%Y-%m-%d 2>/dev/null || date -u -d "@$1" +%Y-%m-%d
}

# Filtre de la transcription d'enveloppe : $argv[1] adresse de recette de la
# réservation, $argv[2] fichier du journal. Imprime, pour chaque ligne qui la
# concerne, « rôle<TAB>adresse From<TAB>ligne », où rôle vaut client (elle
# est destinataire) ou copie (elle est en Reply-To : copie de
# l'administrateur). Comparaison d'adresse entière, jamais par sous-chaîne.
read -r -d '' VERIF5_FILTRE <<'PHP_EOF'
<?php
$cible = strtolower( trim( $argv[1] ) );
$adresse = function ( $v ) {
	$v = (string) $v;
	return strtolower( trim( preg_match( '/<([^>]+)>/', $v, $m ) ? $m[1] : $v ) );
};
foreach ( (array) @file( $argv[2], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $l ) {
	$j = json_decode( $l, true );
	if ( ! is_array( $j ) ) {
		continue;
	}
	$role = null;
	foreach ( explode( ',', (string) ( isset( $j['to'] ) ? $j['to'] : '' ) ) as $to ) {
		if ( $adresse( $to ) === $cible ) {
			$role = 'client';
		}
	}
	if ( null === $role && $adresse( isset( $j['reply_to'] ) ? $j['reply_to'] : '' ) === $cible ) {
		$role = 'copie';
	}
	if ( null !== $role ) {
		echo $role, "\t", $adresse( isset( $j['from'] ) ? $j['from'] : '' ), "\t", $l, "\n";
	}
}
PHP_EOF

# Vérification 5 : les lignes de la transcription qui concernent CETTE
# réservation, par son adresse de recette relue en base (la seule clé commune
# au message client, dont l'objet ne porte pas l'idorder, et à la copie de
# l'administrateur). Toutes affichées. KO s'il n'y en a aucune, ou aucune pour
# le message client, ou si son From composé n'est pas le sender_email de la
# marque dans lme-brands.
verif5_transcription() {
  local marque="$1" courriel="$2"
  titre "Vérification 5 — transcription d'enveloppe de $courriel"

  local journal="$ETAT_DIR/enveloppes.log" lignes
  remote_call envelope-log-tail-since 0 > "$journal" \
    || { rouge "  KO   transcription d'enveloppe illisible"; noter "5  KO  ($marque) transcription illisible"; return; }
  lignes=$(php -- "$courriel" "$journal" <<< "$VERIF5_FILTRE")
  rm -f "$journal"

  if [ -z "$lignes" ]; then
    rouge "  KO   aucune ligne de transcription pour $courriel"
    noter "5  KO  ($marque) aucune ligne de transcription pour cette réservation"
    return
  fi

  local role from json n_client=0 from_client=""
  while IFS=$'\t' read -r role from json; do
    info "  [$role] $json"
    if [ "$role" = "client" ]; then
      n_client=$((n_client + 1))
      from_client="${from_client:+$from_client,}$from"
    fi
  done <<< "$lignes"

  if [ "$n_client" -eq 0 ]; then
    rouge "  KO   aucun message client transcrit (seulement la copie de l'administrateur)"
    noter "5  KO  ($marque) message client absent de la transcription"
    return
  fi

  local out hex attendu
  out=$(remote_call brand-sender "$marque")
  hex=$(printf '%s\n' "$out" | kv_get SENDER_HEX)
  attendu=$(php -r 'echo strtolower( (string) @hex2bin( $argv[1] ) );' -- "$hex")
  if [ -z "$attendu" ]; then
    rouge "  KO   sender_email de '$marque' illisible dans lme-brands : expéditeur non jugé"
    noter "5  KO  ($marque) expéditeur attendu illisible"
    return
  fi

  local f ok=1
  for f in $(printf '%s' "$from_client" | tr ',' ' '); do
    [ "$f" = "$attendu" ] || ok=0
  done
  if [ "$ok" -eq 1 ]; then
    vert "  OK   message client composé avec From $from_client, sender_email de '$marque'"
    noter "5  OK  ($marque) From composé $from_client — ce que WordPress compose, pas ce que le relais Gmail envoie"
  else
    rouge "  KO   From du message client '$from_client', attendu '$attendu'"
    noter "5  KO  ($marque) From composé $from_client, attendu $attendu"
  fi
}

# Vérification 6c : la page de réservation de Vik affiche la réservation
# confirmée, et la base la dit confirmed et payée. L'ancienne lecture de
# index.php?option=com_vikbooking&view=booking&sid=…&idorder=… rendait la page
# d'accueil en 200 (pas de shortcode, pas de ts) : faux positif constant
# jusqu'au 25 septembre 2026.
verif6c_confirmation() {
  local marque="$1" idorder="$2" sid="$3" ts="$4"
  titre "Vérification 6c — confirmation de #$idorder"

  local url fichier="$ETAT_DIR/reprise-confirmation.html" code
  if ! url=$(page_reservation_url "$sid" "$ts"); then
    rouge "  KO   page de la vue booking de Vik introuvable (table des shortcodes)"
    noter "6c KO  ($marque) page de réservation introuvable"
    return
  fi
  code=$(curl -s -o "$fichier" -w '%{http_code}' -L --max-time 20 "$url")
  local etat_page="illisible"
  if [ "$code" = "200" ]; then
    etat_page=$(grep -oE 'vbo-booking-details-head-(confirmed|pending|cancelled)' "$fichier" | head -1 | sed 's/^vbo-booking-details-head-//')
    [ -n "$etat_page" ] || etat_page="réservation non affichée"
  fi
  rm -f "$fichier"

  info "  page de réservation : HTTP $code, état affiché '$etat_page' ; base : statut '$FAIT_STATUS', payée $FAIT_PAYE"
  if [ "$etat_page" = "confirmed" ] && [ "$FAIT_STATUS" = "confirmed" ] && [ "$FAIT_PAYE" = "1" ]; then
    vert "  OK   #$idorder confirmée et payée, affichée confirmée par Vik"
    noter "6c OK  ($marque) #$idorder confirmed et payée en base, page de réservation « confirmed »"
  else
    rouge "  KO   #$idorder : page '$etat_page', base '$FAIT_STATUS', payée $FAIT_PAYE"
    noter "6c KO  ($marque) page '$etat_page', base '$FAIT_STATUS', payée $FAIT_PAYE"
  fi
}

# Vérification 7 : constate la tâche de rappel, ne la déclenche jamais.
#
# Établi dans Vik 1.8.15 (constat-reprise-1836-1837.md §4) : une tâche
# publiée est inscrite dans WP-Cron par VikBookingCron::setup(), sur
# plugins_loaded, sous vikbooking_cron_<class_file>_<id>. Son exécution
# (admin/cronjobs/email_reminder.php, execute()) écrit à TOUTES les
# réservations confirmed de la base dont l'arrivée tombe dans sa fenêtre,
# pas à une réservation choisie : sur la préproduction, ce sont les vraies
# réservations copiées de la production. L'ancien script déduisait le crochet
# de la dernière tâche email_reminder, publiée ou non (la 8, dépubliée), et le
# déclenchait : avec la 7, il aurait écrit à de vrais clients.
verif7_rappel() {
  local marque="$1" idorder="$2"
  titre "Vérification 7 — rappel avant séjour (constat, sans déclenchement)"

  local out rows
  if ! out=$(remote_call reminder-jobs); then
    rouge "  KO   tâches de rappel illisibles (sir_vikbooking_cronjobs)"
    noter "7  KO  ($marque) tâches de rappel illisibles"
    return
  fi
  rows=$(printf '%s\n' "$out" | kv_get ROWS)

  local r id classe pub sched avance moins test publiees=0 hook next debut fin arrivee resume=""
  arrivee=$(ts_vers_iso "$FAIT_CHECKIN")
  for r in $(printf '%s' "$rows" | tr ';' ' '); do
    IFS=: read -r id classe pub sched avance moins test <<< "$r"
    case "$avance" in ''|*[!0-9]*) avance=0 ;; esac
    [ "$pub" = "1" ] || { info "  tâche #$id ($classe, $sched) dépubliée : jamais inscrite dans WP-Cron"; continue; }
    publiees=$((publiees + 1))
    hook="vikbooking_cron_${classe%.php}_${id}"
    next=$(remote_call cron-next "$hook" | kv_get NEXT_RUN_GMT)
    fin=$(date_dans_jours "${avance:-0}")
    if [ "${avance:-0}" -gt 1 ] && [ "$moins" = "1" ]; then debut=$(date_dans_jours 0); else debut="$fin"; fi
    info "  tâche #$id publiée ($sched), crochet $hook, prochaine exécution WP-Cron : ${next:-illisible} UTC (WP-Cron ne tourne pas sur la préproduction)"
    info "  fenêtre si déclenchée aujourd'hui : arrivées confirmed du $debut au $fin (UTC), mode test $test ; arrivée de #$idorder : $arrivee"
    resume="${resume:+$resume ; }#$id vise les arrivées du $debut au $fin"
  done

  if [ "$publiees" -eq 0 ]; then
    rouge "  KO   aucune tâche de rappel publiée : aucun rappel ne part"
    noter "7  KO  ($marque) aucune tâche de rappel publiée"
    return
  fi
  jaune "  ??   déclenchement refusé : la tâche écrit à toutes les réservations de sa fenêtre, vraies réservations copiées comprises"
  noter "7  ??  ($marque) non établie, déclenchement refusé ($resume, #$idorder arrive le $arrivee)"
}

reprendre_reservation() {
  local idorder="$1"
  local ligne; ligne=$(registre_ligne "$idorder")
  [ -n "$ligne" ] || mourir "réservation #$idorder inconnue de $REGISTRE (relancer sans --reprise pour en créer une)"

  local sid ts marque room statut horodatage
  IFS=$'\t' read -r idorder sid ts marque room statut horodatage <<< "$ligne"

  titre "Reprise — réservation #$idorder ($marque, chambre #$room, registre '$statut')"

  lire_faits_reservation "$idorder" \
    || mourir "réservation #$idorder illisible dans sir_vikbooking_orders : reprise impossible"
  [ "$FAIT_COURRIEL" != "HORS_RECETTE" ] && [ -n "$FAIT_COURRIEL" ] \
    || mourir "#$idorder ne porte pas une adresse de recette : ce n'est pas une réservation d'essai, reprise refusée"
  [ "$FAIT_TS" = "$ts" ] \
    || jaune "  ts du registre ($ts) différent de la base ($FAIT_TS) : la base fait foi"

  verif6c_confirmation "$marque" "$idorder" "$sid" "$FAIT_TS"
  verif5_transcription "$marque" "$FAIT_COURRIEL"
  verif7_rappel "$marque" "$idorder"

  titre "Annulation de #$idorder"
  if vik_annuler_reservation "$idorder" "$sid" "$FAIT_TS"; then
    vert "  OK   #$idorder annulée (statut relu en base : cancelled)"
    registre_maj_statut "$idorder" "nettoyee"
    noter "an OK  ($marque) #$idorder annulée"
  else
    rouge "  KO   #$idorder non annulée : $ANNULATION_MOTIF"
    registre_maj_statut "$idorder" "a_nettoyer"
    noter "an KO  ($marque) #$idorder non annulée, $ANNULATION_MOTIF"
  fi

  imprimer_rapport "Rapport (reprise #$idorder)"
}
