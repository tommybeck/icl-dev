#!/bin/bash
#
# installer-miroir-icl-dev.sh
#
# Met en place un miroir en LECTURE SEULE du depot icl-dev, destine a etre
# connecte comme dossier Cowork, avec trois garanties superposees :
#
#   1. les fichiers du miroir ne sont pas inscriptibles (chmod a-w) ;
#   2. l'URL de poussee est neutralisee (git remote --push = no_push) ;
#   3. le miroir est un clone jetable de ton exemplaire local, sans identifiant
#      ni reference reseau, donc sans chemin vers l'origine distante.
#
# Il installe aussi la fraicheur : un crochet post-commit qui resynchronise
# immediatement, un agent launchd de secours, et un fichier ETAT-MIROIR.md
# qui rend toute peremption lisible.
#
# Idempotent : relancable sans dommage. Reversible : --uninstall.
#
# Usage :
#   ./installer-miroir-icl-dev.sh [--source CHEMIN] [--mirror CHEMIN]
#                                 [--interval SECONDES] [--no-hooks]
#                                 [--no-agent] [--check] [--uninstall]
#
set -u

LABEL="ch.linstantcle.miroir-icl-dev"
PLIST="$HOME/Library/LaunchAgents/$LABEL.plist"
SYNC="$HOME/bin/sync-icl-dev-lecture.sh"
MARKER="# >>> miroir-icl-dev >>>"
ENDMARKER="# <<< miroir-icl-dev <<<"

SOURCE=""
MIRROR="$HOME/Work/_Mirrors/icl-dev-lecture"
INTERVAL=900
WITH_HOOKS=1
WITH_AGENT=1
MODE="install"

rouge() { printf '\033[31m%s\033[0m\n' "$*"; }
vert()  { printf '\033[32m%s\033[0m\n' "$*"; }
info()  { printf '  %s\n' "$*"; }
titre() { printf '\n== %s ==\n' "$*"; }
mourir(){ rouge "ERREUR : $*"; exit 1; }

while [ $# -gt 0 ]; do
  case "$1" in
    --source)    SOURCE="${2:-}"; shift 2 ;;
    --mirror)    MIRROR="${2:-}"; shift 2 ;;
    --interval)  INTERVAL="${2:-}"; shift 2 ;;
    --no-hooks)  WITH_HOOKS=0; shift ;;
    --no-agent)  WITH_AGENT=0; shift ;;
    --check)     MODE="check"; shift ;;
    --uninstall) MODE="uninstall"; shift ;;
    -h|--help)   sed -n '2,25p' "$0"; exit 0 ;;
    *) mourir "option inconnue : $1" ;;
  esac
done

# ---------------------------------------------------------------- desinstaller
if [ "$MODE" = "uninstall" ]; then
  titre "Desinstallation"
  if [ -f "$PLIST" ]; then
    launchctl bootout "gui/$(id -u)/$LABEL" 2>/dev/null
    rm -f "$PLIST"; info "agent launchd retire"
  else
    info "aucun agent launchd"
  fi
  if [ -n "$SOURCE" ] && [ -d "$SOURCE/.git/hooks" ]; then
    for h in post-commit post-merge post-checkout; do
      f="$SOURCE/.git/hooks/$h"
      if [ -f "$f" ] && grep -q "miroir-icl-dev" "$f" 2>/dev/null; then
        awk '/# >>> miroir-icl-dev >>>/{inb=1; next} /# <<< miroir-icl-dev <<</{inb=0; next} !inb{print}' \
          "$f" > "$f.tmp" && mv "$f.tmp" "$f" && chmod +x "$f"
        info "bloc retire de $h"
      fi
    done
  else
    info "crochets non traites (passe --source pour les nettoyer)"
  fi
  rm -f "$SYNC"; info "script de synchronisation retire"
  echo
  vert "Fait. Le miroir lui-meme est conserve : supprime-le a la main si tu le veux."
  info "chmod -R u+w \"$MIRROR\" && rm -rf \"$MIRROR\""
  exit 0
fi

# ------------------------------------------------------------------- verifier
if [ "$MODE" = "check" ]; then
  titre "Etat du miroir"
  [ -d "$MIRROR" ] || mourir "miroir absent : $MIRROR"
  if [ -f "$MIRROR/ETAT-MIROIR.md" ]; then
    cat "$MIRROR/ETAT-MIROIR.md"
  else
    rouge "ETAT-MIROIR.md absent : jamais synchronise par ce dispositif."
  fi
  echo
  if git -C "$MIRROR" remote -v 2>/dev/null | grep -q "no_push"; then
    vert "Poussee neutralisee."
  else
    rouge "Poussee NON neutralisee."
  fi
  if touch "$MIRROR/.essai-ecriture" 2>/dev/null; then
    rm -f "$MIRROR/.essai-ecriture"; rouge "Le miroir est INSCRIPTIBLE."
  else
    vert "Le miroir est en lecture seule."
  fi
  if launchctl print "gui/$(id -u)/$LABEL" >/dev/null 2>&1; then
    vert "Agent launchd charge."
  else
    info "Agent launchd non charge."
  fi
  exit 0
fi

# --------------------------------------------------- localiser le depot source
titre "Depot source"
if [ -z "$SOURCE" ]; then
  info "recherche automatique de icl-dev..."
  for racine in "$HOME/Work" "$HOME/Developer" "$HOME/Documents" "$HOME/Projects" "$HOME"; do
    [ -d "$racine" ] || continue
    trouve=$(find "$racine" -maxdepth 4 -type d -name icl-dev -not -path '*/node_modules/*' 2>/dev/null | head -1)
    if [ -n "$trouve" ] && [ -d "$trouve/.git" ]; then SOURCE="$trouve"; break; fi
  done
fi
[ -n "$SOURCE" ] || mourir "depot icl-dev introuvable. Relance avec --source /chemin/vers/icl-dev"
SOURCE="${SOURCE%/}"
[ -d "$SOURCE/.git" ] || mourir "$SOURCE n'est pas un depot git"
MIRROR="${MIRROR%/}"
[ "$SOURCE" != "$MIRROR" ] || mourir "la source et le miroir ne peuvent pas etre le meme dossier"
case "$MIRROR" in "$SOURCE"/*) mourir "le miroir ne peut pas etre a l'interieur de la source" ;; esac
info "source : $SOURCE"
info "miroir : $MIRROR"

# --------------------------------------------------- creer ou reparer le clone
titre "Miroir"
if [ -d "$MIRROR/.git" ]; then
  info "clone deja present, reparation des reglages"
  chmod -R u+w "$MIRROR" 2>/dev/null
  git -C "$MIRROR" remote set-url origin "$SOURCE" || mourir "impossible de regler l'origine"
else
  mkdir -p "$(dirname "$MIRROR")" || mourir "impossible de creer $(dirname "$MIRROR")"
  git clone --quiet "$SOURCE" "$MIRROR" || mourir "clone impossible"
  info "clone cree"
fi
git -C "$MIRROR" remote set-url --push origin no_push || mourir "impossible de neutraliser la poussee"
grep -qx "ETAT-MIROIR.md" "$MIRROR/.git/info/exclude" 2>/dev/null \
  || echo "ETAT-MIROIR.md" >> "$MIRROR/.git/info/exclude"
info "poussee neutralisee (no_push)"

# ---------------------------------------- ecrire le script de synchronisation
titre "Script de synchronisation"
mkdir -p "$HOME/bin" || mourir "impossible de creer $HOME/bin"
cat > "$SYNC" <<SYNCEOF
#!/bin/bash
# Genere par installer-miroir-icl-dev.sh. Resynchronise le miroir en lecture seule.
set -u
SOURCE="$SOURCE"
MIRROR="$MIRROR"

[ -d "\$SOURCE/.git" ] || { echo "source absente : \$SOURCE" >&2; exit 1; }
[ -d "\$MIRROR/.git" ] || { echo "miroir absent : \$MIRROR" >&2; exit 1; }

chmod -R u+w "\$MIRROR" 2>/dev/null

BR=\$(git -C "\$SOURCE" rev-parse --abbrev-ref HEAD 2>/dev/null)
if [ -z "\$BR" ] || [ "\$BR" = "HEAD" ]; then BR="main"; fi

git -C "\$MIRROR" fetch --prune --quiet origin || { echo "fetch impossible" >&2; exit 1; }
git -C "\$MIRROR" checkout -q -B "\$BR" "origin/\$BR" || { echo "branche origin/\$BR introuvable" >&2; exit 1; }
git -C "\$MIRROR" reset -q --hard "origin/\$BR"

SALE=\$(git -C "\$SOURCE" status --porcelain 2>/dev/null | wc -l | tr -d ' ')

{
  echo "# Etat du miroir"
  echo
  echo "- Synchronise le : \$(date '+%Y-%m-%d %H:%M:%S %Z')"
  echo "- Branche : \$BR"
  echo "- Commit : \$(git -C "\$MIRROR" rev-parse --short HEAD)"
  echo "- Date du commit : \$(git -C "\$MIRROR" log -1 --format=%cd --date=iso)"
  echo "- Sujet : \$(git -C "\$MIRROR" log -1 --format=%s)"
  echo "- Fichiers non valides dans la source au moment de la copie : \$SALE"
  echo
  if [ "\$SALE" -gt 0 ]; then
    echo "**Attention.** La source portait du travail non valide au moment de cette copie."
    echo "Ce miroir est donc en retard sur ce que Thomas voit dans son editeur."
    echo
  fi
  echo "Miroir en LECTURE SEULE, genere automatiquement. La source fait foi."
} > "\$MIRROR/ETAT-MIROIR.md"

chmod -R a-w "\$MIRROR" 2>/dev/null
exit 0
SYNCEOF
chmod +x "$SYNC" || mourir "impossible de rendre $SYNC executable"
info "ecrit : $SYNC"

# ------------------------------------------------------------------- crochets
if [ "$WITH_HOOKS" -eq 1 ]; then
  titre "Crochets git dans la source"
  info "ces crochets vivent dans .git/hooks, ils ne sont pas versionnes"
  for h in post-commit post-merge post-checkout; do
    f="$SOURCE/.git/hooks/$h"
    if [ -f "$f" ] && grep -q "miroir-icl-dev" "$f" 2>/dev/null; then
      info "$h : deja en place"; continue
    fi
    [ -f "$f" ] || printf '#!/bin/sh\n' > "$f"
    { echo ""; echo "$MARKER"; echo "\"$SYNC\" >/dev/null 2>&1 &"; echo "$ENDMARKER"; } >> "$f"
    chmod +x "$f"
    info "$h : bloc ajoute"
  done
else
  info "crochets ignores (--no-hooks)"
fi

# -------------------------------------------------------------- agent launchd
if [ "$WITH_AGENT" -eq 1 ]; then
  titre "Agent launchd de secours"
  mkdir -p "$HOME/Library/LaunchAgents"
  cat > "$PLIST" <<PLISTEOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Label</key><string>$LABEL</string>
  <key>ProgramArguments</key>
  <array>
    <string>/bin/bash</string>
    <string>$SYNC</string>
  </array>
  <key>StartInterval</key><integer>$INTERVAL</integer>
  <key>RunAtLoad</key><true/>
  <key>StandardOutPath</key><string>/tmp/miroir-icl-dev.log</string>
  <key>StandardErrorPath</key><string>/tmp/miroir-icl-dev.err</string>
</dict>
</plist>
PLISTEOF
  launchctl bootout "gui/$(id -u)/$LABEL" 2>/dev/null
  if launchctl bootstrap "gui/$(id -u)" "$PLIST" 2>/dev/null; then
    info "agent charge, toutes les $INTERVAL secondes"
  else
    rouge "chargement refuse. A faire a la main :"
    info "launchctl bootstrap gui/\$(id -u) \"$PLIST\""
  fi
else
  info "agent ignore (--no-agent)"
fi

# ------------------------------------------------- premiere synchronisation
titre "Premiere synchronisation"
if "$SYNC"; then info "faite"; else mourir "la synchronisation a echoue"; fi

# ------------------------------------------------------------- verifications
titre "Verifications"
ok=0; ko=0
verifie() {
  if eval "$2" >/dev/null 2>&1; then vert "  OK   $1"; ok=$((ok+1))
  else rouge "  KO   $1"; ko=$((ko+1)); fi
}
verifie "poussee neutralisee" "git -C \"$MIRROR\" remote -v | grep -q no_push"
verifie "ETAT-MIROIR.md present" "test -f \"$MIRROR/ETAT-MIROIR.md\""
verifie "miroir non inscriptible" "! touch \"$MIRROR/.essai-ecriture\""
rm -f "$MIRROR/.essai-ecriture" 2>/dev/null
verifie "script de synchronisation executable" "test -x \"$SYNC\""
if [ "$WITH_AGENT" -eq 1 ]; then
  verifie "agent launchd charge" "launchctl print gui/$(id -u)/$LABEL"
fi
if [ "$WITH_HOOKS" -eq 1 ]; then
  verifie "crochet post-commit arme" "grep -q miroir-icl-dev \"$SOURCE/.git/hooks/post-commit\""
fi

echo
if [ "$ko" -eq 0 ]; then vert "Tout est en place ($ok controles)."; else rouge "$ko controle(s) en echec sur $((ok+ko))."; fi

titre "Ce qu'il te reste a faire"
echo "  1. Connecter ce dossier dans Cowork, et lui seul :"
echo "       $MIRROR"
echo "     Ne jamais connecter $SOURCE."
echo
echo "  2. Voir l'etat a tout moment :"
echo "       $0 --check"
echo
echo "  3. Tout retirer :"
echo "       $0 --uninstall --source \"$SOURCE\""
echo
echo "  Limite : un miroir ne voit que ce qui est valide. Le travail non commite"
echo "  dans la source reste invisible, et ETAT-MIROIR.md le dit en clair."
exit 0
