# markerPDF pdftext dictionary layout order source payload current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T132748Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` receives already selected pdftext dictionary pages from `pdftext.extraction.dictionary_output(...)`.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout and ordering are applied.
- `marker/layout/order.py::surya_order()` zips ordering predictions with selected Marker pages, so native supplied artifacts must align by trusted adapter page identity before assignment.

## Implemented Behavior

- Copied pdftext page dictionaries under generic wrappers such as `source` are now treated the same as explicit `pdftext` payloads during the first trusted-marker pass.
- `PdfPageArtifactSelector` ignores copied page payload markers while adapter metadata already carries page identity, but still allows those payload markers as a fallback when no adapter metadata exists.
- `LayoutAnnotator` and `LayoutOrderer` apply the same fallback-only rule before sanitizing layout/order metadata, so stale copied source page numbers do not leak beside trusted `document_page` markers.
- The WordPress converter path now preserves selected-page layout and order assignment when artifacts carry `metadata.document_page` plus stale copied `source` pdftext page payloads.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` failed with 2 failures: the selected page kept source order and the converter had no `layout`/`order` supplied boundaries.

Green after implementation:

- `php -l` passed for changed PHP source, test, and example files.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` passed: 1 test files / 68 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: 5 test files / 1209 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-source-payload-currentbase.php` passed and emitted `layout_artifact_assigned=true`, `order_artifact_assigned=true`, `visible_columns_in_reading_order=true`, `source_payload_fallback_only=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` passed.

Focused delta: +2 focused PASS cases and +28 focused assertions in the current-base pdftext dictionary layout/order test file.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, page artifact selection, layout annotation, ordering, supplied-document conversion, Markdown finalization, and WordPress smoke paths. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat sparse keyed page matching, source-page alias matching, selected-index matching, ambiguous marker rejection, duplicate-keyed artifact replay prevention, nested explicit `pdftext` payload exclusion, bbox normalization, page sorting, CMap/font/xref/image/security/form/annotation behavior, or table/equation supplied-boundary handoffs. The bounded behavior is only generic copied `source` pdftext page payload markers being fallback-only when trusted layout/order adapter metadata is present.
