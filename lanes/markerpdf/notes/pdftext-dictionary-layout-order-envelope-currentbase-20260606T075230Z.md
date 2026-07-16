# markerPDF pdftext dictionary layout order envelope current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T075230Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` receives `pdftext.extraction.dictionary_output(...)` pages before downstream layout/order processing.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order model zipping.
- `marker/layout/order.py::surya_order()` zips ordering results with selected Marker pages, so native cached `dictionary_output` envelopes must use the unwrapped page-list count when aligning full-document layout/order artifacts.

## Implemented Behavior

- `PdfTextDocumentExtractor::getTextBlocks()` now records `metadata.source_pages` from the normalized/unwrapped pdftext page list.
- `PdfTextDocumentExtractor::getOrderedTextBlocks()` uses `metadata.source_pages` when trimming full-document order images/results, so a cached `dictionary_output` envelope with `metadata` plus `pages` does not make the source page count look like the envelope key count.
- `SuppliedDocumentConverter` uses the same normalized source-page count for layout/order image/result alignment and default `document_page_count`.
- Added focused extractor and WordPress-facing converter coverage for full-document layout/order artifacts supplied alongside a pdftext dictionary envelope.
- Added `wordpress-pdftext-dictionary-layout-order-envelope-currentbase.php`, which renders the selected page in order and emits non-execution flags.

## Verification

Red before source changes:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed at 2 test files / 1072 assertions / 2 failures:
  - extractor selected the cover-page order artifact, yielding `Second envelope order column` before `First envelope order column`;
  - converter kept `layout_result_count` at `3` instead of selected-page `1`.

Green after source changes:

- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-envelope-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: 2 test files / 1094 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: 4 test files / 1169 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-envelope-currentbase.php` emitted `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `layout_order_assigned=true`, `first_before_second=true`, `cover_excluded=true`, `appendix_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` passed.

Focused delta: +2 focused PASS cases and +22 focused assertions over the pre-slice extractor/converter baseline.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary envelope unwrapping, supplied page-range handling, layout artifact alignment, ordering artifact alignment, supplied-document conversion, Markdown finalization, and WordPress smoke paths. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat prior pdftext dictionary object-envelope page unwrapping, sparse keyed layout/order artifact matching, wrapper-list page-marker handling, selected-index matching, page-range metadata matching, duplicate keyed artifact handling, payload-wrapper rejection, bbox normalization, zero-area order box rejection, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, or equation/image supplied-boundary work. The bounded behavior is only full-document layout/order artifact trimming after a cached pdftext dictionary envelope is unwrapped.
