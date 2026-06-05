# markerPDF pdftext dictionary layout order string marker current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T014150Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates only the selected dictionary pages before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before layout/order images are rendered.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip supplied model predictions with the selected Marker pages. Native supplied adapters that serialize page identity through JSON/CLI metadata must therefore normalize numeric page-marker strings before selected-page matching, rather than treating them as unkeyed artifacts.

## Implemented Behavior

- `PdfPageArtifactSelector` now trims string page-marker values before accepting integer `page_index`, `doc_page_index`, `document_page_index`, `source_page_index`, `pnum`, `page`, `pdftext_page`, or `page_number` markers.
- Whitespace-padded numeric page markers now remain keyed artifacts, so skipped cover/appendix layout and order predictions cannot fall back to positional assignment.
- Existing selected-only unkeyed lists, full-document-length lists, exact numeric markers, nested adapter wrappers, mismatch rejection, collision protection, and partial sparse-keyed sentinels keep their prior behavior.
- Added extractor and supplied-converter regressions plus a WordPress smoke for the selected-page string-marker alignment boundary.

## Verification

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-string-marker-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: `2 test files, 780 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: `4 test files, 830 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-string-marker-currentbase.php` passed and emitted selected Gutenberg paragraphs in the expected order plus `string_markers_normalized=true`, `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `first_before_second=true`, `cover_excluded=true`, `appendix_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases and +12 focused assertions over the prior extractor/converter family baseline (`768 -> 780` assertions).

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed page-artifact selection, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, exact sparse keyed matching, shallow/nested adapter wrapper matching, selected-count keyed mismatch exclusion, page-index collision protection, conflicting source/page identity rejection, partial sparse-keyed missing-page handling, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically numeric string page markers with surrounding whitespace before selected pdftext dictionary layout/order assignment.
