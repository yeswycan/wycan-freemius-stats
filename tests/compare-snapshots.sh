#!/usr/bin/env bash
# Compare deux dossiers d'instantanés. Sortie 0 si identiques à l'octet.
set -euo pipefail
if [ $# -ne 2 ]; then
	echo "Usage : $0 <dossier référence> <dossier candidat>" >&2
	exit 2
fi
diff -r --brief "$1" "$2" && echo "Instantanés identiques ($(ls "$1" | wc -l | tr -d ' ') fichiers)."
