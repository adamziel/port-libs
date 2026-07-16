# CMap Malformed Scalar Bfrange Target Source-Width Current Base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T120003Z`

Base: `4ddf14ad85da5fb33d38631852b70aaae3e4a2e4`

## Source Truth

The native no-GPU markerPDF lane owns searchable-PDF text extraction, font CMap handling, CID widths, stream filters, and WordPress import boundaries. ToUnicode `beginbfrange` rows map source character-code ranges to Unicode strings, but malformed scalar hex targets should not replace already valid exact `bfchar` mappings used by CMap source-width grouping.

## Behavior

`PdfTextExtractor` now validates scalar hex ToUnicode bfrange targets as complete UTF-16BE code-unit strings before removing earlier source mappings. Literal scalar targets keep the existing decoded-byte fallback, and bfrange arrays remain governed by the existing array-length/source-count guards.

The fixture maps `<20>` through `<27>` to `ABCDEFGH` with exact `bfchar` rows, then presents a later malformed scalar bfrange row `<20> <27> <0058FF>`. Before the patch, that row erased the exact mappings and emitted raw NUL/replacement bytes. After the patch, the malformed row is ignored and source-width grouping preserves `ABCD` and `EFGH` spans with the expected CIDFont `/W` geometry.

## Verification

Red-first current-base check before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedBfrangeTargetSourceWidthCurrentBaseTest.php`

Result before fix: `1 test files, 1 assertions, 1 failures`; extracted line contained raw NUL/replacement bytes from `<0058FF>` instead of `ABCDEFGH`.

Passing focused checks after the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedBfrangeTargetSourceWidthCurrentBaseTest.php`

Result: `1 test files, 14 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedBfrangeTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php`

Result: `7 test files, 540 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-malformed-bfrange-target-source-width-currentbase.php`

Result: exits 0 with `visible_text_preserved=true`, `text_runs_preserved=true`, `source_width_bbox_preserved=true`, `malformed_scalar_bfrange_target_rejected=true`, `cmap_program_bytes_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Final hygiene:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfCMapMalformedBfrangeTargetSourceWidthCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-malformed-bfrange-target-source-width-currentbase.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`: `json ok`.
- `git diff --check -- lanes/markerpdf`: exits 0.

## Status Delta

- `phpPass`: `3081` -> `3082`
- `wordpressScenarios`: `2543` -> `2544`
- Manifest behavior: `pdfCMapMalformedBfrangeTargetSourceWidthCurrentBase`

## Non-Overlap

This slice does not repeat existing short/overlong bfrange array target handling, literal target decoding, long surrogate bfrange increments, CMap notdef source-width fallback, delayed/malformed codespace handling, CMap filter-boundary rejection, AcroForm, annotations, xref, image, metadata, runtime, or model/OCR behavior. It is limited to malformed scalar hex ToUnicode bfrange targets that would otherwise remove exact mappings.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP CMap parser, ToUnicode decoder, CIDFont width grouping, styled-span extraction, TestRunner, and WordPress smoke path. It does not run OCR, Surya, Texify, Torch, PDFium, PIL, raster rendering, Python models, live services, or external PDF tools.
