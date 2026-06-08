# ODF OpenDocument Manifest Provenance

Slice: `pandoc-odf-open-document-core-current-base-20260608T233543Z`
Base accepted HEAD: `9ded36a0bdf8a38d0d938423ba129d62e7355cba`

## Behavior

- Preserves the root `manifest:version` value from `META-INF/manifest.xml`.
- Preserves per-entry `manifest:preferred-view-mode` values for the root
  document entry and media entries.
- Exposes the manifest version and preferred-view-mode provenance in the
  document manifest handoff, import report manifest summary, media metadata,
  and WordPress ODF package handoff smoke.

This is native PHP support-library work only. No Pandoc binary, Cabal
solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip,
external converter, online service, live provider test, or live-service
provider test was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this slice.
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2526 assertions, 1 failures`
  - Failure: root manifest `preferredViewMode` was missing from the package
    handoff.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2549 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native `OdfReader` DOM manifest
parsing, the existing in-memory `ZipPackage` fixture path, and the existing
WordPress ODF package handoff example.

## Non-Overlap

This does not repeat accepted ODF text:tab normalization, heading ids,
blockquote styles, table captions, style maps, data-pilot metadata, typed
fields, drop-down fields, settings.xml, draw-layer metadata, form controls,
chart metadata, table row/column visibility, linked/protected sections,
tracked changes, manifest media extraction, encrypted media reporting, image
list-style metadata, or database range subtotal-rule metadata. It is limited
to manifest root version and preferred-view-mode provenance.

## Next

Choose a non-overlapping ODF package/content gap such as tracked-change edge
metadata, data-pilot metadata, style-driven table cell semantics, or another
manifest-entry attribute not already surfaced in the package handoff.
