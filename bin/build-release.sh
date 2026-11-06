#!/usr/bin/env bash
# Build the installable Joomla package: dist/mod_mystorelocator-<version>.zip (runtime files only).
# Usage: bin/build-release.sh
set -euo pipefail
cd "$(dirname "$0")/.."
SRC=mod_mystorelocator
VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$SRC/mod_mystorelocator.xml" | head -1)
[ -n "$VERSION" ] || { echo "version not found in manifest" >&2; exit 1; }

# syntax check when php is available (Docker fallback otherwise)
if command -v php >/dev/null 2>&1; then
  find "$SRC" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
fi

mkdir -p dist
OUT="dist/mod_mystorelocator-$VERSION.zip"
rm -f "$OUT"
FILES=(mod_mystorelocator.xml LICENSE.txt services src tmpl language media)
if command -v zip >/dev/null 2>&1; then
  ( cd "$SRC" && zip -qrX "../$OUT" "${FILES[@]}" -x '*.DS_Store' '*~' '*.swp' '*.env' '.git*' )
else
  # fallback without the zip binary
  python3 - "$SRC" "$OUT" "${FILES[@]}" <<'PY'
import os, sys, zipfile
src, out, items = sys.argv[1], sys.argv[2], sys.argv[3:]
skip = lambda n: n.startswith('.') or n.endswith(('~', '.swp', '.env'))
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for item in items:
        path = os.path.join(src, item)
        if os.path.isfile(path):
            z.write(path, item); continue
        for root, dirs, files in os.walk(path):
            dirs[:] = sorted(d for d in dirs if not skip(d))
            for f in sorted(files):
                if not skip(f):
                    full = os.path.join(root, f)
                    z.write(full, os.path.relpath(full, src))
PY
fi
echo "built $OUT ($(du -h "$OUT" | cut -f1))"
( cd dist && sha256sum "$(basename "$OUT")" > "$(basename "$OUT").sha256" )
python3 -c 'import sys,zipfile;[print(i.file_size, i.filename) for i in zipfile.ZipFile(sys.argv[1]).infolist()]' "$OUT"

# keep the update server file in sync (version, download file name, checksum)
SHA=$(cut -d' ' -f1 "$OUT.sha256")
sed -i -E \
  -e "s#<version>[^<]*</version>#<version>$VERSION</version>#" \
  -e "s#downloads/mod_mystorelocator-[0-9.]+\.zip#downloads/mod_mystorelocator-$VERSION.zip#" \
  -e "s#<sha256>[^<]*</sha256>#<sha256>$SHA</sha256>#" updates.xml
echo "updates.xml -> $VERSION / sha256 $SHA"
