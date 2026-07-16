# markerPDF pdftext dictionary layout order partial keyed current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T000052Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates selected dictionary pages before Marker page conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before rendering layout/order images: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip model predictions to selected Marker pages, so native sparse supplied artifacts must keep selected-page slot alignment without treating a missing selected page as an assigned model prediction.

## Implemented Behavior

- `PdfPageArtifactSelector` now inserts an internal missing-page sentinel only when sparse keyed artifacts match some, but not all, selected pdftext pages.
- `LayoutAnnotator` and `LayoutOrderer` skip that sentinel during assignment and count only present images/results in their plans.
- The first selected page in a partial sparse-keyed range keeps source order when no artifact matches it, while a later selected page still receives its keyed order boxes.
- The WordPress-facing supplied converter reports one assigned layout/order artifact instead of two placeholder assignments, while still excluding skipped cover/appendix pdftext pages.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed: 2 test files / 675 assertions / 2 failures. Both new partial sparse-keyed regressions expected one assigned artifact, but current code counted two.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php && php -l lanes/markerpdf/src/LayoutAnnotator.php && php -l lanes/markerpdf/src/LayoutOrderer.php && php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php && php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php && php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-partial-keyed-currentbase.php` passed with no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: 2 test files / 690 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: 4 test files / 740 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-partial-keyed-currentbase.php` passed and emitted `layout_artifact_count=1`, `layout_assigned_pages=1`, `order_artifact_count=1`, `order_assigned_pages=1`, `unmatched_page_source_order_preserved=true`, `matched_page_order_applied=true`, `cover_excluded=true`, `appendix_excluded=true`, and no Python/model/external PDF tool execution.
- `git diff --check -- lanes/markerpdf` passed.

Focused delta: +2 focused PASS cases and +15 focused assertions over the red focused extractor/converter baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed page-artifact selection, layout annotation, ordering, supplied-document conversion, Markdown finalization, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, exact sparse keyed artifact matching, selected-count keyed mismatch exclusion, page-index collision protection, conflicting source/page identity rejection, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically partial sparse keyed artifact slot preservation with missing selected pages excluded from assigned-prediction counts.
