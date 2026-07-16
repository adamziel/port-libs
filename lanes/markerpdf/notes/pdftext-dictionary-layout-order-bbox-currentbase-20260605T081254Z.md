# pdftext Dictionary Layout-Order Bbox Boundary Current Base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T080538Z`

Accepted base: `90c134ef160d0dae68131072cad507459f78c7e8`

Source truth:

- Upstream `pdftext.extraction.dictionary_output(...)` supplies page dictionaries with block/line/span bboxes.
- Upstream markerPDF applies layout/order after selected pdftext pages are built, so supplied order artifacts are a geometry-only page-order handoff.
- Native no-GPU scope reuses supplied pdftext/order dictionaries and does not run Surya, pdftext, PDFium, Python, models, or external PDF tools.

Behavior implemented:

- `LayoutOrderer` now normalizes numeric-string `image_bbox` and order `bbox` coordinates before reading-order matching.
- Malformed order bbox rows and malformed order positions are excluded before they can influence block ordering or leak into stored page `order` metadata.
- Page order metadata stores only normalized ordering geometry plus scalar page markers.

Red-first evidence:

- Current-base in-memory probe fataled with `TypeError` in `LayoutOrderer::bbox()` when supplied order bbox coordinates were strings.

Verification:

- Baseline before patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` => `1 test files, 256 assertions, 0 failures`.
- Focused after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` => `1 test files, 268 assertions, 0 failures`.
- Adjacent direct layout/order gate: `php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php` => `1 test files, 25 assertions, 0 failures`.
- Adjacent supplied-document gate: `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` => `1 test files, 753 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-bbox-currentbase.php` emits `numeric_string_image_bbox_normalized=true`, `numeric_string_order_bboxes_normalized=true`, `malformed_order_rows_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Status delta:

- `phpPass`: `1604 -> 1605`
- `wordpressScenarios`: `1485 -> 1486`
- Focused assertions in `PdfTextDocumentExtractorTest.php`: `256 -> 268`

Dependency closure:

- No new support component is needed. This reuses the native supplied pdftext dictionary and layout-order handoff path.
- GPU/model OCR/layout parity remains intentionally out of scope under the current markerPDF no-GPU directive.
