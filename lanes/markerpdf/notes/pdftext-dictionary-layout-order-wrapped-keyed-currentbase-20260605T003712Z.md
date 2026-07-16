# markerPDF pdftext dictionary layout order wrapped keyed current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T003712Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates selected dictionary pages before Marker page conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout and ordering images/predictions are associated with Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- `marker/layout/order.py::surya_order()` zips ordering predictions with selected Marker pages, so supplied native artifacts that wrap page identity in adapter metadata must still align to selected pdftext page numbers before zip-style assignment: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Implemented Behavior

- `PdfPageArtifactSelector` now reads page identity from shallow metadata wrappers (`metadata`, `page_metadata`, `page_meta`, `page_info`, `source`, and `pdftext`) in addition to the existing top-level marker fields.
- The existing conflict rules are preserved: if source-index and page-number markers disagree, the artifact still does not match the selected page.
- Wrapped keyed layout/order images and predictions are selected for the current pdftext dictionary page before `LayoutAnnotator` and `LayoutOrderer` perform zip-style assignment.
- Cover/appendix wrapped artifacts no longer positionally reorder the selected page when the selected pdftext page carries original document page numbers.
- Added a WordPress smoke for wrapped metadata layout/order artifacts, with no Python/model/external PDF tool execution.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed: 2 test files / 704 assertions / 2 failures. The extractor kept `Second wrapped selected column` before `First wrapped selected column`, and the converter counted 2 layout artifacts instead of the selected wrapped artifact.

Green after implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: 2 test files / 718 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-wrapped-keyed-currentbase.php` passed and emitted `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `first_before_second=true`, `cover_excluded=true`, `appendix_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases and +14 focused assertions over the current-base red focused extractor/converter baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed page-artifact selection, layout annotation, ordering, supplied-document conversion, Markdown finalization, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, top-level keyed artifact matching, selected-count keyed mismatch exclusion, page-index collision protection, conflicting source/page identity rejection, partial sparse-keyed placeholder handling, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically shallow metadata-wrapped page identity for supplied pdftext dictionary layout/order artifacts.
