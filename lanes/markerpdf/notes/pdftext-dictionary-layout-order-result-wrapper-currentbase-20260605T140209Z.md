# markerPDF pdftext dictionary layout order result-wrapper current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T140209Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and converts the selected pdftext dictionaries into `Page` objects.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout and ordering are associated with Marker pages.
- `marker/layout/layout.py::surya_layout()` zips Surya `LayoutResult` values to selected pages, and `marker/layout/order.py::surya_order()` zips Surya `OrderResult` values to selected pages before `sort_blocks_in_reading_order()`.
- Native PHP supplied artifacts may serialize those typed model objects under adapter keys such as `layout_result` or `order_result`; those wrappers are not WordPress-visible payload.

## Implemented Behavior

- `PdfPageArtifactSelector` now treats typed result wrappers (`layout`, `layout_result`, `order`, `order_result`, `prediction`, `result`, `model_output`, and `output`) as page-marker sources when aligning sparse supplied artifacts to selected pdftext dictionary pages.
- `LayoutAnnotator` unwraps typed layout-result payloads before copying `image_bbox` and `bboxes`, while still sanitizing away wrapper payloads and preserving only scalar page markers.
- `LayoutOrderer` unwraps typed order-result payloads before copying `image_bbox` and `bboxes`, while preserving the existing bbox normalization and wrapper-payload exclusion rules.
- Added focused current-base coverage for both the lower-level `PdfTextDocumentExtractor::getOrderedTextBlocks()` path and the WordPress-facing `SuppliedDocumentConverter` path.
- Added a WordPress smoke for typed layout/order result wrappers.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` failed: 1 test files / 74 assertions / 2 failures. The selected page stayed in source order because `order_result.bboxes` were not unwrapped, and the converter counted both sparse wrapper artifacts because page markers under typed result wrappers were not selected.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` passed.
- `php -l lanes/markerpdf/src/LayoutOrderer.php` passed.
- `php -l lanes/markerpdf/src/LayoutAnnotator.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-result-wrapper-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` passed: 1 test files / 97 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php` passed: 5 test files / 1238 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-result-wrapper-currentbase.php` passed and emitted `layout_artifact_count=1`, `order_artifact_count=1`, `first_before_second=true`, `cover_excluded=true`, `appendix_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases and +23 focused assertions over the prior focused current-base file baseline.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary conversion, selected page-range artifact selector, layout annotation, order sorting, supplied-document conversion, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat full-document artifact trimming, top-level keyed matching, shallow/nested metadata wrapper matching, source/pdftext payload fallback handling, numeric page-marker normalization, normalized order bbox rescaling, zero-area order rejection, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is typed `LayoutResult`/`OrderResult` wrapper unwrapping and selected-page alignment before WordPress import.
