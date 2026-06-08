# CMap Underdeclared Code-Space Source Width - Current Base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T071530Z`

Base: `46202efa14a54e48d6402cf95aed247ffe0ec061`

Scope:
- Native searchable-PDF Type0/ToUnicode CMap parsing only.
- No OCR, Surya, Texify, Torch, model execution, pypdfium/PIL raster rendering, external PDF tools, or live-service calls.

Source-truth behavior:
- Upstream markerPDF searchable-PDF text extraction delegates font/CMap decoding to pdftext/PDFium behavior; the PHP port must preserve native CMap text and CIDFont source-width boundaries without using GPU/model paths.
- A malformed underdeclared `begincodespacerange` block should not poison later well-formed code-space blocks in the same CMap. Later valid source rows must remain available for ToUnicode text decoding and source-width grouping.
- If no valid local code-space rows survive the malformed declaration, the port still fails closed for local ToUnicode mappings as before.

Implementation:
- `PdfTextExtractor::parseToUnicodeCMap()` now always parses surviving valid code-space rows.
- Local mapping blocks are skipped only when the CMap had an underdeclared code-space block and no valid local code-space ranges remain.

Focused evidence:
- Pre-fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapUnderdeclaredCodeSpaceSourceWidthCurrentBaseTest.php` failed after 1 assertion. Expected `ABCD EFGH`; actual raw decoded text was ` !"# $%&'`.
- After fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapUnderdeclaredCodeSpaceSourceWidthCurrentBaseTest.php` passed with 1 test file, 11 assertions, 0 failures.
- Adjacent source-width run: `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapUnderdeclaredCodeSpaceSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapCidCharCodeSpaceSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapObjectUseCMapPreludeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapOverlongToUnicodeSourceWidthCurrentBaseTest.php` passed with 8 test files, 499 assertions, 0 failures.
- Adjacent malformed-CMap boundary run: `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredRowsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php` passed with 4 test files, 208 assertions, 0 failures.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-cmap-underdeclared-codespace-source-width-currentbase.php` exits 0 and emits one paragraph `ABCD EFGH`; flags report later valid code-space recovery, source-width geometry preservation, CMap program bytes excluded, no raw NUL bytes, no Python/models, and no external PDF tools.

Dependency closure:
- No new support component is needed.
- Reuses the existing bounded native `pdf-text-dictionary-core` CMap parser and source-width extraction path.

Non-overlap:
- This avoids the accepted malformed declared-count CID source-width slice, malformed CMap stream filter/token-boundary slices, Type0 `/UseCMap` resource-width slices, and lazy large CID range source-width slices.
- The new behavior is specifically recovery from an underdeclared code-space decoy when a later valid code-space block exists in the same ToUnicode CMap.
