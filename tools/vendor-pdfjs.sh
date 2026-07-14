#!/usr/bin/env sh
set -eu

PDFJS_VERSION="${PDFJS_VERSION:-6.1.200}"
PDFJS_SHA1="4d957377ab57e397172fccb96bfe008dbcb2ddd6"
ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
DEST="$ROOT/pandoc-showcase/vendor/pdfjs"
TEMP="$(mktemp -d "${TMPDIR:-/tmp}/port-libs-pdfjs.XXXXXX")"

cleanup() {
  rm -rf "$TEMP"
}
trap cleanup EXIT INT TERM

npm pack "pdfjs-dist@$PDFJS_VERSION" --pack-destination "$TEMP" >/dev/null
TARBALL="$TEMP/pdfjs-dist-$PDFJS_VERSION.tgz"
if [ ! -f "$TARBALL" ]; then
  echo "Could not obtain pdfjs-dist $PDFJS_VERSION" >&2
  exit 1
fi

ACTUAL_SHA1="$(shasum "$TARBALL" | awk '{print $1}')"
if [ "$ACTUAL_SHA1" != "$PDFJS_SHA1" ]; then
  echo "pdfjs-dist checksum mismatch: expected $PDFJS_SHA1, got $ACTUAL_SHA1" >&2
  exit 1
fi

tar -xzf "$TARBALL" -C "$TEMP"
PACKAGE="$TEMP/package"
mkdir -p "$DEST"
cp "$PACKAGE/build/pdf.min.mjs" "$DEST/pdf.min.mjs"
cp "$PACKAGE/build/pdf.worker.min.mjs" "$DEST/pdf.worker.min.mjs"
cp "$PACKAGE/LICENSE" "$DEST/LICENSE"
rm -rf "$DEST/cmaps" "$DEST/standard_fonts" "$DEST/wasm" "$DEST/image_decoders"
cp -R "$PACKAGE/cmaps" "$DEST/cmaps"
cp -R "$PACKAGE/standard_fonts" "$DEST/standard_fonts"
cp -R "$PACKAGE/wasm" "$DEST/wasm"
cp -R "$PACKAGE/image_decoders" "$DEST/image_decoders"

echo "Vendored pdfjs-dist $PDFJS_VERSION into $DEST"
