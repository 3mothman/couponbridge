#!/usr/bin/env bash
# Packs plugin + theme for Hostinger upload.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="$ROOT/dist"
mkdir -p "$OUT"
rm -f "$OUT/angebot-deals.zip" "$OUT/angebot-theme.zip"
(cd "$ROOT/plugin/angebot-deals (3)" && zip -r "$OUT/angebot-deals.zip" angebot-deals -x "*.DS_Store")
(cd "$ROOT/theme/angebot-theme (5)" && zip -r "$OUT/angebot-theme.zip" angebot -x "*.DS_Store")
echo "Created:"
ls -lh "$OUT"/*.zip
