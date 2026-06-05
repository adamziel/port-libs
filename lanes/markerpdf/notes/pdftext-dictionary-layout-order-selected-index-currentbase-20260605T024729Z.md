# markerPDF pdftext dictionary layout order selected-index current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T024729Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates only the selected dictionary pages before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before layout/order images are rendered.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip model predictions with those selected Marker pages. Native supplied artifacts that serialize sparse post-trim prediction indexes must therefore align by selected-page index before zip-style assignment.

## Implemented Behavior

- `PdfPageArtifactSelector` now accepts explicit post-trim page markers:
  `selected_page_index`, `trimmed_page_index`, `relative_page_index`,
  `selected_page_number`, `trimmed_page_number`, and `relative_page_number`.
- Sparse selected-range artifacts for a later selected page are padded with the existing missing-page sentinel instead of being positionally assigned to the first selected page.
- Existing source-document markers (`page_index`, `doc_page_index`, `document_page_index`, `source_page_index`), page identity markers (`pnum`, `page`, `pdftext_page`, `page_number`), nested wrappers, string normalization, mismatch rejection, and conflicting-marker guards keep their prior behavior.
- Added extractor and supplied-converter regressions plus a WordPress smoke for sparse selected-index layout/order artifacts across a two-page selected range.

## Verification

Baseline before new tests:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - `2 test files, 801 assertions, 0 failures`

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - `2 test files, 818 assertions, 2 failures`
  - Failures: the sparse selected-index order artifact was assigned to the first selected page; the WordPress converter also attached the sparse selected-index layout/order artifact to the first selected page.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php`
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php`
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-selected-index-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - `2 test files, 828 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php`
  - `4 test files, 878 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-selected-index-currentbase.php`
  - emitted `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `selected_index_artifact_attached_to_second_page=true`, `first_page_kept_source_order=true`, `second_page_reordered=true`, `cover_excluded=true`, `appendix_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases and +27 assertions over the prior focused extractor/converter baseline (`801 -> 828`).

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed page-artifact selection, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, exact sparse page-identity matching, shallow/nested adapter wrapper matching, selected-count keyed mismatch exclusion, page-index collision protection, conflicting source/page identity rejection, whitespace string marker normalization, partial sparse-keyed page-number alignment, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically sparse supplied layout/order artifacts keyed by explicit post-trim selected-page indexes across a selected pdftext dictionary page range.
