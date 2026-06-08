# CMap Decoy CID Map Bfrange Source-Width Fallback Current Base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T110924Z`

Accepted base: `5fdecc98c514fd489c66940ca59605872f7dcf63`

## Behavior

This patch keeps Type0/CID source-width fallback stable when an Encoding CMap
contains unrelated CID rows and the visible text is decoded by ToUnicode
`beginbfrange` source ranges. The native width splitter now treats CID mappings
as relevant only when the current source keys actually hit a CID map or CID
range, instead of letting a decoy mapping suppress later ToUnicode metric-miss
fallback.

The focused fixture uses four-byte CID source chunks with an unrelated
`<FFFFFFFF> 999` `begincidchar` row, while text decoding and geometry rely on
two-byte ToUnicode `beginbfrange` rows. WordPress import smoke output preserves
`ABCD EFGH`, keeps the expected source-width span boxes, and excludes raw CMap
program bytes or NUL leakage.

## Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfCMapDecoyCidMapBfrangeSourceWidthCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-cmap-decoy-cid-map-bfrange-source-width-currentbase.php`
  - No syntax errors detected in all changed PHP files.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapDecoyCidMapBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php`
  - `3 test files, 419 assertions, 0 failures`
  - Adds 1 focused PASS case and 11 focused assertions for the decoy-CID/bfrange source-width boundary.
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-decoy-cid-map-bfrange-source-width-currentbase.php`
  - Exits 0 and emits a WordPress paragraph for `ABCD EFGH` with all smoke flags true.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is required. The patch reuses the existing native
`pdf-text-dictionary-core` CMap, font-width, and styled-span geometry helpers.
No Python, CUDA, OCR, model execution, raster rendering, external PDF tools, or
live-service providers were used.

## Non-Overlap

This does not touch the recent xref Prev-chain inherited-trailer work, CMap
literal-target coverage, large lazy ToUnicode bfrange expansion, zero-padded
source-width collapse, OCR/model handoffs, or dashboard/publication files.
