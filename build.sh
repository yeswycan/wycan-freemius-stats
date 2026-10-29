#!/usr/bin/env bash
# Produit l'archive livrable du plugin : dist/<dossier>.zip, contenant uniquement le code à installer.
# Usage : ./build.sh [nom du dossier]   (défaut : wycan-plugins-stats, le dossier installé sur wycan.fr)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
FOLDER="${1:-wycan-plugins-stats}"
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/wycan-freemius-stats.php" | head -1)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

if [ -f "$ROOT/languages/wycan-freemius-stats-fr_FR.po" ] && command -v msgfmt >/dev/null 2>&1; then
	msgfmt --check -o "$ROOT/languages/wycan-freemius-stats-fr_FR.mo" "$ROOT/languages/wycan-freemius-stats-fr_FR.po"
fi

mkdir -p "$STAGE/$FOLDER" "$ROOT/dist"
rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$STAGE/$FOLDER/"

ZIP="$ROOT/dist/$FOLDER-$VERSION.zip"
rm -f "$ZIP"
( cd "$STAGE" && zip -qr -X "$ZIP" "$FOLDER" )

echo "Archive : $ZIP"
unzip -l "$ZIP" | tail -1
