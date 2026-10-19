#!/bin/sh
# Genera tutte le varianti dell'icona del sito a partire da un unico SVG sorgente
# (public/icon.svg). Eseguito nello stage `icon-builder` del Dockerfile: per
# aggiornare le icone basta modificare l'SVG e ricostruire l'immagine.
#
# Uso:  generate-icons.sh <svg-sorgente> <cartella-destinazione>
#
# Variabili opzionali:
#   ICON_APP_NAME  nome nel site.webmanifest      (default: Calendario Cavalcalupi)
#   ICON_THEME     theme_color del manifest        (default: #15803d)
#   ICON_APPLE_BG  sfondo dell'apple-touch-icon    (default: #15803d; iOS non gestisce la
#                  trasparenza e la riempirebbe di nero)
#   ICON_BASE_URL  prefisso URL delle icone         (default: /build/icons)
set -eu

SRC="${1:?manca l'SVG sorgente}"
OUT="${2:?manca la cartella di destinazione}"

APP_NAME="${ICON_APP_NAME:-Calendario Cavalcalupi}"
THEME="${ICON_THEME:-#15803d}"
APPLE_BG="${ICON_APPLE_BG:-#15803d}"
BASE_URL="${ICON_BASE_URL:-/build/icons}"

[ -f "$SRC" ] || { echo "SVG sorgente non trovato: $SRC" >&2; exit 1; }
mkdir -p "$OUT"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# render <lato> <destinazione> [sfondo]
# Rasterizza l'SVG in un PNG quadrato <lato>x<lato>, centrato (le proporzioni non
# vengono deformate). Con uno sfondo diverso da "none" l'immagine viene appiattita.
render() {
    size="$1"; dest="$2"; bg="${3:-none}"
    rsvg-convert --keep-aspect-ratio -w "$size" -h "$size" "$SRC" -o "$TMP/raw.png"
    if [ "$bg" = "none" ]; then
        convert "$TMP/raw.png" -background none -gravity center -extent "${size}x${size}" "PNG32:$dest"
    else
        convert "$TMP/raw.png" -background "$bg" -gravity center -extent "${size}x${size}" \
            -alpha remove -alpha off "$dest"
    fi
}

render 16  "$OUT/favicon-16x16.png"
render 32  "$OUT/favicon-32x32.png"
render 48  "$TMP/favicon-48x48.png"
render 180 "$OUT/apple-touch-icon.png" "$APPLE_BG"
render 192 "$OUT/android-chrome-192x192.png"
render 512 "$OUT/android-chrome-512x512.png"

# favicon.ico multi-risoluzione (16/32/48) per i browser che lo richiedono alla radice
convert "$OUT/favicon-16x16.png" "$OUT/favicon-32x32.png" "$TMP/favicon-48x48.png" "$OUT/favicon.ico"

cat > "$OUT/site.webmanifest" <<EOF
{
  "name": "$APP_NAME",
  "short_name": "Cavalcalupi",
  "icons": [
    { "src": "$BASE_URL/android-chrome-192x192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "$BASE_URL/android-chrome-512x512.png", "sizes": "512x512", "type": "image/png" }
  ],
  "theme_color": "$THEME",
  "background_color": "#ffffff",
  "display": "standalone"
}
EOF

echo "Icone generate in $OUT:"
ls -l "$OUT"
