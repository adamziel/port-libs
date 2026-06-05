# markerPDF table source review boundary current-base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T132145Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes supplied table boxes through rendered-page image crops before table recognition and Markdown formatting.
- Locked `tabled-pdf==0.1.4` table recognition keeps saved row, column, and cell geometry as user-review evidence while assignment and formatting work in the localized table-crop coordinate space.
- For the native no-GPU PHP lane, supplied table rows/cells may enter as multiple bbox field shapes: `[x0, y0, x1, y1]`, `bbox: {x,y,width,height}`, `bbox: {left,top,width,height}`, or polygon-derived bboxes. After localizing page-image geometry into table-crop coordinates, the review path must still expose the original source field shape so WordPress overlays can explain which saved boundary source was used.

## Implementation

- `TableRecognizer` now stores `source_coordinate_source` and `source_endpoint_order_normalized` next to `source_bbox`, `source_page_image_bbox`, and `source_coordinate_space` whenever table rows, columns, cells, and OCR conflict rows are localized from page, page-image, or normalized page-image input.
- The same source review fields are now copied through assigned crop reviews, active-band reviews, normalized row/column lists, bounded grid band rows, cell boundary rows, span-grid cell summaries, and render-cell summaries.
- Cell group summaries now preserve source page-image bbox unions and unique source coordinate-source lists, which keeps span/render review metadata tied to the saved upstream boundary representation.
- Added a supplied WordPress smoke that proves source-shape review metadata survives localization while stale pdftext table text and off-crop table cells remain excluded.

## Verification

- Red-first before source change:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySourceReviewBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 17 assertions, 2 failures`.
- Focused after patch:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySourceReviewBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 39 assertions, 0 failures`.
- Broader table family:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/TableUtilsTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - Result: `24 test files, 2051 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-table-source-review-boundary-currentbase.php`
  - Result: emitted `source_shape_preserved_for_row_bands=true`, `source_shape_preserved_for_cells=true`, `offcrop_source_shape_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Syntax checks:
  - `php -l lanes/markerpdf/src/TableRecognizer.php`
  - `php -l lanes/markerpdf/tests/TableGeometrySourceReviewBoundaryCurrentBaseTest.php`
  - `php -l lanes/markerpdf/examples/wordpress-table-source-review-boundary-currentbase.php`
  - Result: no syntax errors.
- Whitespace:
  - `git diff --check -- lanes/markerpdf`
  - Result: passed.

## Status Delta

- `phpPass`: `1861 -> 1863`.
- `wordpressScenarios`: `1689 -> 1690`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native supplied-document converter, table recognizer, geometry normalizer, table-crop localization, spanning-grid review, Markdown table formatting, and WordPress smoke path. Full upstream parity remains gated by the no-GPU/no-live-model scope: no Surya, tabled model inference, OCR, Texify, Torch, PDFium rendering, Python workers, external PDF tools, or benchmark/model runners were executed.

## Non-Overlap

This does not repeat accepted table geometry extent, named-bbox, numeric-string, reversed endpoint, normalized page-image, page-image localization, polygon, assigned-cell crop boundary, active-band clipping, merged-cell geometry, OCR grid-border, OCR polygon, scalar-span, or table text-cell boundary slices. The bounded behavior here is specifically preserving the saved source bbox field-shape and endpoint-order review metadata after localization into table-crop coordinates and surfacing it through WordPress table review metadata.
