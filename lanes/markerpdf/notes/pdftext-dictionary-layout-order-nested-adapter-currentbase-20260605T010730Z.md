# markerPDF pdftext dictionary layout order nested adapter current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T010730Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates only selected dictionary pages before Marker page conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before rendering layout/order images: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- `marker/layout/order.py::surya_order()` zips ordering predictions with selected Marker pages. Native supplied adapters that wrap page identity around model payloads must therefore still align artifacts to selected pdftext page numbers before zip-style assignment: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Implemented Behavior

- `PdfPageArtifactSelector` now recognizes bounded nested adapter wrappers for page identity: `page_data`, `page_result`, `result_metadata`, and `artifact_metadata`.
- Page marker source collection now recurses through known page-metadata wrappers to depth two, so shapes like `page_data.metadata.page` and `page_result.page_info.page` are treated as keyed artifacts while preserving the existing conflict rules.
- The actual layout/order model payload remains top-level; only page identity is read from nested adapter metadata.
- Extractor and supplied-converter paths now select the matching nested adapter artifact for the selected pdftext dictionary page instead of positionally assigning a skipped cover artifact.
- Added a WordPress smoke proving cover/appendix nested adapter artifacts stay excluded while the selected page is ordered before Gutenberg paragraph output.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - failed with `2 test files, 724 assertions, 2 failures`
  - failures showed the extractor kept `Second nested selected column` before `First nested selected column`, and the converter counted two nested layout artifacts instead of the selected artifact.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-nested-adapter-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: `2 test files, 738 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: `4 test files, 788 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-nested-adapter-currentbase.php` passed and emitted `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `first_before_second=true`, `cover_excluded=true`, `appendix_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases and +20 focused assertions over the extractor/converter baseline (`718 -> 738` assertions).

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed page-artifact selection, layout annotation, ordering, supplied-document conversion, Markdown finalization, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, top-level keyed artifact matching, shallow wrapped metadata matching, selected-count keyed mismatch exclusion, page-index collision protection, conflicting source/page identity rejection, partial sparse-keyed placeholder handling, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically nested adapter page identity for supplied pdftext dictionary layout/order artifacts.
