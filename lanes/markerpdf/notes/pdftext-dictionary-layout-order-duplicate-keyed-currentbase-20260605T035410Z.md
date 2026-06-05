# markerPDF pdftext dictionary layout order duplicate keyed current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T035410Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)` and enumerates only the selected dictionary pages before Marker page conversion.
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before layout/order images are rendered.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` consume one supplied prediction per selected page through zip-style assignment. Native keyed artifacts therefore must not replay one prediction row across multiple selected pages when adapter metadata contains duplicate page markers.

## Implemented Behavior

- `PdfPageArtifactSelector` now consumes a matched marker-bearing artifact index once while building the selected artifact list.
- A single duplicate-keyed supplied layout/order artifact can be assigned to the first matching selected pdftext page, but subsequent selected pages with the same adapter page marker receive the existing missing-artifact sentinel.
- Existing selected-only positional artifacts, full-document range slicing, sparse keyed matching, wrapper marker matching, conflicting-marker rejection, selected-index matching, and partial sparse keyed counts keep their previous behavior.
- Added extractor and supplied-converter regressions plus a WordPress smoke for duplicate-keyed pdftext dictionary layout/order artifacts.

## Verification

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php`
  - passed
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php`
  - passed
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - passed
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-duplicate-keyed-currentbase.php`
  - passed
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`
  - `2 test files, 870 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php`
  - `2 test files, 50 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-duplicate-keyed-currentbase.php`
  - emitted `layout_artifact_count=1`, `layout_assigned_pages=1`, `order_artifact_count=1`, `order_assigned_pages=1`, `first_page_order_applied=true`, `second_page_source_order_preserved=true`, `single_artifact_not_replayed=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`

Focused delta: +2 focused PASS cases in existing extractor/converter files and +1 WordPress smoke scenario.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed page-artifact selection, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, exact sparse page-identity matching, shallow/nested adapter wrapper matching, selected-count keyed mismatch exclusion, page-index collision protection, conflicting source/page identity rejection, whitespace string marker normalization, partial sparse-keyed page-number alignment, selected-index sparse matching, marker precedence, pdftext dictionary sorting, keep-chars sanitation, blank-page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically one-use consumption of matched keyed supplied layout/order artifacts so a single prediction row cannot replay across duplicate selected pdftext page markers.
