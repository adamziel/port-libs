# pdftext dictionary layout/order camelCase marker boundary current-base

Micro-slice:
`markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T060038Z`

Accepted base:
`2171358932fe02157aa7525f6f45e6130d530a6c`

## Source Truth

Upstream markerPDF keeps searchable-PDF text extraction and model layout/order
handoffs as selected page lists: `marker/pdf/extract_text.py` delegates to
`pdftext.extraction.dictionary_output(..., page_range=...)`, `convert.py`
trims/threads the selected page range into conversion, and
`marker/layout/order.py` pairs supplied order predictions with the selected
pages. This no-GPU native PHP slice preserves that zip-style selected-page
contract while widening only the page-identity boundary for JSON-style adapter
markers.

Relevant upstream source references:

- `sddai/markerPDF` `marker/pdf/extract_text.py`
- `sddai/markerPDF` `marker/convert.py`
- `sddai/markerPDF` `marker/layout/order.py`
- `VikParuchuri/pdftext` `pdftext/extraction.py`

## Behavior

Supplied layout/order artifact selectors, layout annotation, and order
application now recognize camelCase counterparts for the already-supported
snake_case page marker groups:

- source-page indexes/ranges such as `sourcePageIndex` and `pageRange`;
- selected-page indexes/ranges such as `selectedPageIndex`;
- exact pdftext/document page ids such as `pdftextPage`, `documentPage`, and
  `pageId`;
- one-based page numbers such as `pageNumber`;
- selected one-based page numbers such as `selectedPageNumber`.

This keeps oversized stale/selected artifact lists fail-closed unless a marker
matches the selected pdftext page. Row-level camelCase markers are also used to
discard stale layout/order boxes before WordPress output, and raw supplied
payloads remain excluded from converted text/metadata.

## Evidence

Red-first focused run before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCamelCaseMarkerBoundaryCurrentBaseTest.php`
- Result: `1 test files, 5 assertions, 2 failures`
- Failures: selected page was not reordered, and converter metadata had empty
  `supplied_boundaries` because `documentPage`, `pdftextPage`,
  `sourcePageIndex`, and `pageNumber` were not recognized.

Focused run after implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCamelCaseMarkerBoundaryCurrentBaseTest.php`
- Result: `1 test files, 35 assertions, 0 failures`

Final verification for the handoff also includes PHP lint for changed PHP
files, an adjacent layout/order boundary family run, the WordPress smoke
example, and `git diff --check -- lanes/markerpdf`.

## Non-overlap

This does not repeat full-document artifact trimming, source-key maps, direct
keyed maps, scalar sidecars, page-id/page-idx/page-num/page-range snake_case
markers, selected-index markers, plural marker arrays, string/decimal/signed/
nonfinite marker parsing, duplicate keyed artifact rejection, wrapper geometry,
metadata siblings, row-level snake_case page marker filtering, model execution,
OCR, table recognition, equation recognition, parser/xref repair, CMap/font
behavior, stream filters, annotations, forms, security preflight, image/filter
metadata, or page geometry. The bounded behavior is strictly camelCase marker
alias handling at the supplied pdftext layout/order boundary.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`PdfPageArtifactSelector`, `LayoutAnnotator`, `LayoutOrderer`, and supplied
document converter paths. No Python, PDFium, Surya, Texify, Torch, OCR/model
runner, raster renderer, external PDF tool, or online service is invoked.
