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
# anti-CSRF (`viktoken`/`vikwp_nonce`) n'existe sur cette installation —
# relevé le 23 septembre 2026, absent de la page oconfirm en entier
# (recherche insensible à la casse de « token », « nonce », « csrf »,
# « _wpnonce ») : `tokenform` n'y est pas activé.
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
# Résultat dans CREATE_STATUT : created | refused_403 | refus_vik | ambigu | erreur
# Sur created : CREATE_SID, CREATE_TS, CREATE_IDORDER, CREATE_REDIRECT_URL,
#               CREATE_STRIPE_HREF (lien Stripe Checkout relevé dans la page,
#               vide si absent — voir extraire_href_stripe_wrapper()).
# Sur refused_403 : CREATE_TITRE (titre de la page wp_die).
# Sur refus_vik : CREATE_MESSAGE_VIK (texte du <p class="err"> de Vik).
vik_creer_reservation() {
  local room_id="$1" checkin_iso="$2" nuits="$3"
  CREATE_STATUT="erreur"; CREATE_SID=""; CREATE_TS=""; CREATE_IDORDER=""; CREATE_REDIRECT_URL=""; CREATE_STRIPE_HREF=""; CREATE_TITRE=""; CREATE_MESSAGE_VIK=""

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
    CREATE_STATUT="erreur"
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
    CREATE_STATUT="ambigu"
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

# ============================================================== annulation
#
# task=docancelbooking (site/controller.php:2740) : sid + idorder + email +
# reason. `JSession::checkToken()` y est vérifié sans condition (contrairement
# à saveorder, qui l'ignore quand tokenform est désactivé) ; aucun champ
# viktoken/vikwp_nonce n'a été trouvé sur cette installation (voir en-tête de
# ce fichier), donc rien n'est ajouté au-delà de ce que la page de
# confirmation porterait elle-même — au pire cette étape échoue proprement et
# le nettoyage le rapporte, jamais en silence. La validation ne retient une
# réservation que si son statut vaut 'confirmed' : une réservation encore en
# 'standby' (carte de test jamais saisie) ne peut pas être annulée par ce
# chemin, c'est une propriété du contrôleur de Vik, pas une limite de ce
# script — le balayage natif de la parade de paiement (plan-de-marche.md,
# chantier D) reprend les commandes standby abandonnées.
vik_annuler_reservation() {
  local idorder="$1" sid="$2" courriel="$3"

  local page_confirmation; page_confirmation=$(vik_get "https://$HOTE/index.php?option=com_vikbooking&view=booking&sid=${sid}&idorder=${idorder}")
  local viktoken vikwp_nonce
  viktoken=$(extraire_valeur_champ "$page_confirmation" "viktoken")
  vikwp_nonce=$(extraire_valeur_champ "$page_confirmation" "vikwp_nonce")

  local -a champs=(
    "option=com_vikbooking" "task=docancelbooking"
    "sid=${sid}" "idorder=${idorder}" "email=${courriel}"
    "reason=${MARQUAGE_RAISON_ANNULATION}"
  )
  [ -n "$viktoken" ] && champs+=("viktoken=${viktoken}")
  [ -n "$vikwp_nonce" ] && champs+=("vikwp_nonce=${vikwp_nonce}")

  local brut_annulation; brut_annulation=$(vik_curl_post_brut "https://$HOTE/index.php" "${champs[@]}")
  VIK_LAST_CODE=$(printf '%s\n' "$brut_annulation" | sed -n 's/^__ICL_CODE__//p' | tail -1)
  VIK_LAST_URL=$(printf '%s\n' "$brut_annulation" | sed -n 's/^__ICL_URL__//p' | tail -1)
  local page_resultat; page_resultat=$(printf '%s\n' "$brut_annulation" | sed '/^__ICL_CODE__/d; /^__ICL_URL__/d')

  if { [ "$VIK_LAST_CODE" = "200" ] || [ "$VIK_LAST_CODE" = "302" ]; } && printf '%s' "$page_resultat" | grep -qiE "cancel|annul"; then
    return 0
  fi
  jaune "  annulation non confirmée pour idorder=$idorder (code $VIK_LAST_CODE) — probablement encore 'standby' (carte de test non saisie), politique d'annulation refusée (délai minimal), ou jeton anti-CSRF requis et non transmis"
  return 1
}

nettoyer_registre() {
  local idorder sid ts marque room statut horodatage traitees=0 echecs=0
  while IFS=$'\t' read -r idorder sid ts marque room statut horodatage; do
    [ -n "$idorder" ] || continue
    [ "$statut" = "nettoyee" ] && continue
    info "annulation de la réservation d'essai #$idorder (sid=$sid, $marque, chambre $room, créée $horodatage)"
    if vik_annuler_reservation "$idorder" "$sid" "recette@${MARQUAGE_EMAIL_DOMAINE}"; then
      vert "  OK   #$idorder annulée"
      registre_maj_statut "$idorder" "nettoyee"
      traitees=$((traitees + 1))
    else
      rouge "  KO   #$idorder non annulée — reste dans le registre, à retraiter par --nettoyer"
      echecs=$((echecs + 1))
    fi
  done < "$REGISTRE"
  echo
  info "$traitees annulée(s), $echecs échec(s) ou en attente"
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
    erreur)
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

  if [ "$CREATE_STATUT" != "created" ]; then
    rouge "  KO   la réservation d'essai n'a pas pu être créée (statut '$CREATE_STATUT')"
    noter "5-6 KO ($marque) création impossible"
    return
  fi

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
reprendre_reservation() {
  local idorder="$1"
  local ligne; ligne=$(registre_ligne "$idorder")
  [ -n "$ligne" ] || mourir "réservation #$idorder inconnue de $REGISTRE (relancer sans --reprise pour en créer une)"

  local sid ts marque room statut horodatage
  IFS=$'\t' read -r idorder sid ts marque room statut horodatage <<< "$ligne"

  titre "Reprise — réservation #$idorder ($marque, chambre #$room)"

  local http_code; http_code=$(curl -s -o /tmp/icl-recette-confirmation.html -w '%{http_code}' -L --max-time 20 "https://$HOTE/index.php?option=com_vikbooking&view=booking&sid=${sid}&idorder=${idorder}")
  local page_confirmation; page_confirmation=$(cat /tmp/icl-recette-confirmation.html 2>/dev/null)
  rm -f /tmp/icl-recette-confirmation.html

  if [ "$http_code" != "200" ]; then
    rouge "  KO   page de confirmation illisible (statut $http_code) : le paiement de test a-t-il été mené à bien ?"
    return 1
  fi

  if printf '%s' "$page_confirmation" | grep -qiE 'standby|en attente|pending'; then
    jaune "  la réservation semble encore en attente de paiement ('standby') : la carte de test a-t-elle été validée ?"
  fi

  vert "  OK   page de confirmation lisible (statut 200)"
  noter "6c OK  ($marque) page de confirmation affichée après retour de paiement, sur $HOTE"

  # Vérification 5 : transcription d'enveloppe (chapitre 2 du brief) — jamais
  # la boîte fourre-tout elle-même.
  local env_tail; env_tail=$(remote_call envelope-log-tail-since 0)
  if printf '%s' "$env_tail" | grep -qE "\"host\":\"$HOTE\""; then
    vert "  OK   au moins une ligne de transcription d'enveloppe pour cet hôte"
    local derniere; derniere=$(printf '%s' "$env_tail" | grep -E "\"host\":\"$HOTE\"" | tail -1)
    info "  dernière ligne : $derniere"
    noter "5  OK  ($marque) transcription d'enveloppe présente — vérifier à l'œil From/Sender/Reply-To ci-dessus"
  else
    rouge "  KO   aucune ligne de transcription d'enveloppe trouvée pour $HOTE"
    noter "5  KO  ($marque) transcription d'enveloppe absente"
  fi

  # Vérification 7 : rappel avant séjour, tâche planifiée de Vik.
  local out rows job_id
  out=$(remote_call cronjob-id "email_reminder")
  rows=$(printf '%s\n' "$out" | kv_get ROWS)
  job_id=$(printf '%s' "$rows" | tr ';' '\n' | grep -oE '^[0-9]+' | tail -1)

  if [ -z "$job_id" ]; then
    rouge "  KO   impossible de déterminer l'identifiant de la tâche planifiée 'email_reminder' (sir_vikbooking_cronjobs)"
    noter "7  KO  identifiant de tâche introuvable"
  else
    local hook="vikbooking_cron_email_reminder_${job_id}"
    info "  déclenchement de $hook (wp cron event run)"
    local env_offset_avant; env_offset_avant=$(remote_call envelope-log-offset)
    out=$(remote_call cron-run "$hook")
    local rc; rc=$(printf '%s\n' "$out" | kv_get RC)
    if [ "$rc" = "0" ]; then
      vert "  OK   $hook exécutée"
      local env_tail_apres; env_tail_apres=$(remote_call envelope-log-tail-since "$env_offset_avant")
      if [ -n "$env_tail_apres" ]; then
        vert "  OK   nouvelle(s) ligne(s) de transcription d'enveloppe après le rappel"
        noter "7  OK  ($marque) rappel avant séjour déclenché et transcrit"
      else
        jaune "  aucune nouvelle ligne transcrite : normal si l'arrivée d'essai n'est pas à J-2 (fenêtre du rappel)"
        noter "7  ??  ($marque) rappel exécuté sans envoi observé — attendu si l'arrivée n'est pas à J-2"
      fi
    else
      rouge "  KO   échec de $hook : $(printf '%s\n' "$out" | kv_get OUT)"
      noter "7  KO  ($marque) échec de la tâche planifiée"
    fi
  fi

  echo
  info "annulation de la réservation d'essai #$idorder"
  if vik_annuler_reservation "$idorder" "$sid" "recette@${MARQUAGE_EMAIL_DOMAINE}"; then
    vert "  OK   #$idorder annulée"
    registre_maj_statut "$idorder" "nettoyee"
  else
    rouge "  KO   #$idorder non annulée automatiquement — relancer avec --nettoyer"
    registre_maj_statut "$idorder" "a_nettoyer"
  fi

  titre "Rapport (reprise #$idorder)"
  for l in "${RAPPORT[@]}"; do
    case "$l" in
      *" OK "*|*" OK"*) vert "  $l" ;;
      *) rouge "  $l" ;;
    esac
  done
}
