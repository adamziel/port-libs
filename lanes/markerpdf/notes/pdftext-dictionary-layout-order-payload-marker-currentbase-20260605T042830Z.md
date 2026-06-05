# markerPDF pdftext dictionary layout order payload marker current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T042830Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates the selected dictionary pages into Marker pages.
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before layout/order images are rendered.
- `marker/layout/order.py::surya_order()` zips order predictions with those selected Marker pages, and `sort_blocks_in_reading_order()` uses the order geometry only for block ordering.

## Implemented Behavior

- `PdfPageArtifactSelector` now treats nested `pdftext` dictionaries as fallback page-marker sources.
- Normal adapter metadata wrappers such as `metadata`, `page_metadata`, `page_data`, `source`, and nested adapter envelopes remain authoritative when they carry page identity.
- If no normal metadata marker exists, a nested `pdftext` dictionary can still provide backward-compatible fallback identity.
- Added extractor and supplied-document regressions proving a stale nested pdftext payload no longer prevents selected-page layout/order assignment.
- Updated the WordPress payload-boundary smoke so stale nested pdftext page copies stay out of visible text, page identity, and sanitized order metadata.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - `2 test files, 875 assertions, 2 failures`
  - Failures: stale nested pdftext payload markers blocked selected order assignment and removed supplied layout/order boundaries.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php`
  - `No syntax errors detected in lanes/markerpdf/src/PdfPageArtifactSelector.php`
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php`
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-boundary-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-boundary-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - `2 test files, 894 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php`
  - `4 test files, 944 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-payload-boundary-currentbase.php`
  - emitted `stale_pdftext_payload_ignored_for_identity=true`, `visible_columns_in_reading_order=true`, `order_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf`
  - passed.

Focused delta: +2 focused PASS cases and +19 assertions over the red-first focused baseline after adding the new regressions.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed artifact selection, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, sparse page-keyed matching, selected-index matching, nested adapter marker discovery, page-index collision protection, duplicate-keyed artifact reuse prevention, order payload sanitation, pdftext dictionary sorting, keep-chars sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically that nested copied `pdftext` dictionaries are fallback-only marker sources, so stale payload page markers cannot override trusted adapter metadata before selected-page layout/order assignment.
