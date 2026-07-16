# markerPDF pdftext dictionary layout order page-index collision current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260604T230013Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates the selected dictionary pages before Marker page conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before rendering layout/order images: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- `marker/layout/order.py::surya_order()` zips ordering predictions to the selected Marker pages, so native keyed artifacts must not be assigned to a selected page unless their page identity actually matches that selected pdftext page: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Implemented Behavior

- `PdfPageArtifactSelector` no longer falls back from page-identity markers (`page`, `pnum`, `pdftext_page`, `page_number`) to the selected source index when the selected pdftext page number is known.
- Explicit source-index markers (`page_index`, `doc_page_index`, `document_page_index`, `source_page_index`) still align by selected source index.
- Selected-only unkeyed artifact lists and exact page-keyed artifact lists keep the accepted zip-style behavior.
- Added extractor and supplied-converter regressions for a skipped one-based cover-page artifact whose `page=1` collides with selected `start_page=1`.
- Added a WordPress smoke proving those collision artifacts stay excluded and source order is preserved when no selected-page order artifact exists.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed: 2 test files / 616 assertions / 2 failures. The selected page was reordered by a `page=1` artifact and the supplied converter emitted `layout`/`order` boundaries for a skipped cover artifact.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-index-collision-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: 2 test files / 626 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: 4 test files / 676 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-index-collision-currentbase.php` passed and emitted `layout_artifacts_excluded=true`, `order_artifacts_excluded=true`, `cover_excluded=true`, `source_order_preserved_without_matching_order=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` passed.

Focused delta: +2 focused PASS cases and +15 focused assertions over the prior focused extractor/converter run.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed page-artifact selection, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and the existing WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, exact sparse keyed artifact matching, selected-count keyed mismatch exclusion, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically preventing page-identity keyed layout/order artifacts from matching selected pdftext pages only because their marker collides with the selected source index.
