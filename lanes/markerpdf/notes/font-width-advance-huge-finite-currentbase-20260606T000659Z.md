# markerpdf-font-width-advance-boundary-current-base-20260606T000659Z

## Scope

- Lane: markerPDF
- Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T000659Z`
- Accepted base: `996d008a6d589439433524500ecf697af2eedb4a`
- Native no-GPU scope: searchable-PDF font width/current-advance behavior only; no OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, or external PDF tools.

## Source Truth

Upstream markerPDF relies on native/pdftext-style searchable PDF text geometry for searchable PDFs before any model handoff. PDF simple-font `/Widths` entries are glyph-space advance metrics; normal Type1/TrueType/CID/Type3 values are page-scale, while non-finite, negative horizontal, or absurd finite metrics should not be allowed to expand current text advance or styled review bboxes into unbounded coordinates.

## Red-First Evidence

Before the source edit, a simple-font fixture with `/Widths [1e308 1e308 1000 1000]` kept `1e308` as finite horizontal metrics. Native extraction still decoded text, but styled output had bbox coordinates around `2.4E+306` and current-advance grouping joined `ABCD` where the positioned gap should preserve `AB CD`.

## Patch

- Added a generous native `MAX_FONT_ADVANCE_METRIC` ceiling in `PdfTextExtractor`.
- Bound signed vertical metrics by absolute value while preserving negative vertical displacements.
- Bound horizontal metrics to finite, non-negative, page-scale glyph advances before `/Widths`, `/DW`, `/W`, `/W2`, descriptor fallback, current advance, and styled bbox paths can consume them.
- Added a current-base regression proving finite `1e308` simple-font widths fall back to sane positive widths, preserve `AB CD`, and keep styled bboxes finite and below page-scale overflow.
- Extended the WordPress smoke to report `huge_finite_width_current_gap_preserved`, `huge_finite_width_bbox_overflow_excluded`, `huge_finite_width_styled_bboxes_preserved`, and no model/external-tool execution.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` -> no syntax errors
- `php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` -> no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php` -> no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` -> `1 test files, 481 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php > /tmp/wordpress-pdf-font-width-advance-boundary-currentbase.html` -> smoke flags true for the huge finite width boundary and model/tool execution flags false

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is required. This reuses the existing native PDF parser/font-width helpers and the existing WordPress smoke. GPU/model/OCR gaps remain an intentional no-GPU scope limit, not a blocker for this slice.

## Non-Overlap

This does not repeat the accepted non-finite width, negative width, malformed width-range, exact-generation, Type0 CID `/W`/`/W2`, Type3 FontMatrix, quote/Tc/Tw/Td/Tm/TJ, DCTDecode, xref, image, annotation, form, metadata, or supplied-boundary table/equation clusters. It owns only finite-but-pathological simple-font metric rejection before current advance and styled bbox overflow.
