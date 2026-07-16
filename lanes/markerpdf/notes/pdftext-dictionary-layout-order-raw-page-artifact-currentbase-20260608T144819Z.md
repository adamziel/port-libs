# markerPDF pdftext dictionary layout order raw page artifact current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T144819Z`
Session: `port-dev-markerpdf-pdf-dictionary-layout-20260608T144819Z`
Base accepted HEAD: `e204a40179162b2df94e6db36bf203fd0df70d1a`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` obtains searchable PDF page dictionaries from `pdftext.extraction.dictionary_output(...)` over the selected `page_range`, then converts those dictionaries into Marker pages.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected pages before layout/order images and model predictions are associated with Marker pages.
- `marker/layout/order.py::surya_order()` assigns Surya ordering predictions to selected pages by zipping model `order_results` with `pages`; raw pdftext page dictionaries are not order/layout prediction payloads.

## Implemented Behavior

- `PdfPageArtifactSelector` now distinguishes selectable supplied layout/order/image sidecar payloads from raw pdftext page copies.
- Source-keyed maps whose values contain only pdftext page fields (`blocks` plus page `bbox`) are rejected as layout/order/image artifacts.
- Existing selectable payload keys (`bboxes`, `image`, `image_bbox`, typed layout/order/result wrappers, and model output wrappers) still work for direct source-keyed maps, JSON artifact maps, `page_map`, and pdftext-shaped envelopes.
- WordPress supplied imports no longer create `layout_plan`, `order_plan`, assigned-page counts, heading promotion, or hidden model metadata when raw pdftext page maps are accidentally passed in layout/order sidecar slots.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderRawPageArtifactBoundaryCurrentBaseTest.php` failed: 1 test file / 9 assertions / 2 failures. Raw source-keyed pdftext page sidecars were counted as selected layout/order artifacts.

Green after implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderRawPageArtifactBoundaryCurrentBaseTest.php` passed: 1 test file / 25 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrder*CurrentBaseTest.php` passed: 19 test files / 1473 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php` passed: 4 test files / 1171 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-raw-page-artifact-currentbase.php` passed and emitted `raw_pdftext_sidecars_rejected=true`, `layout_plan_absent=true`, `order_plan_absent=true`, `raw_sidecar_text_hidden=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases, +25 focused assertions in the new file, and +1 WordPress smoke.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page artifact selection, layout/order sidecar normalization, supplied-document conversion, Markdown/WordPress rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat shallow metadata page-marker selection, source-keyed model artifact maps, decimal keys, duplicate key guards, typed JSON envelopes, `page_map` envelopes, wrapper geometry envelopes, raw JSON artifact payloads, scalar sidecar filtering, finite bbox validation, normalized geometry, page range trimming, pdftext sorting, keep-chars sanitation, table recognition, OCR, image extraction, parser/xref repair, font/CMap handling, annotation/form/security review, or equation supplied-boundary work. The bounded behavior is only the fail-closed boundary for raw pdftext page dictionaries appearing in layout/order artifact sidecar slots.
