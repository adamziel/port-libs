# markerPDF pdftext dictionary layout order keyed boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260604T124352Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)` over the selected `page_range`.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout and ordering are applied.
- `marker/layout/order.py::surya_order()` zips supplied order predictions with selected Marker pages, so native supplied artifacts must be aligned to selected pdftext pages before zip-style assignment.

## Implemented Behavior

- Added `PdfPageArtifactSelector` for selected-page artifact alignment.
- Existing selected-only and full-document-length artifact lists keep their previous behavior.
- Sparse supplied layout/order artifacts that carry page markers (`page_index`, `doc_page_index`, `document_page_index`, `source_page_index`, `pnum`, `page`, `pdftext_page`, or `page_number`) are matched to the selected pdftext page before assignment.
- The lower-level `PdfTextDocumentExtractor::getOrderedTextBlocks()` and WordPress-facing `SuppliedDocumentConverter` now share this boundary so a keyed cover-page artifact cannot reorder the selected page.
- Added a WordPress smoke for sparse keyed pdftext dictionary layout/order artifacts.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` failed because the selected page received the keyed cover-page ordering result.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed because sparse keyed layout/order artifact counts stayed at `2` instead of selected-page `1`.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` passed.
- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-keyed-boundary-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: 2 test files / 584 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: 4 test files / 634 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-keyed-boundary-currentbase.php` passed and emitted `layout_artifacts_trimmed=true`, `order_artifacts_trimmed=true`, `first_before_second=true`, `cover_excluded=true`, and `appendix_excluded=true`.
- `git diff --check -- lanes/markerpdf` passed.

Focused delta: +2 focused PASS cases and +18 focused assertions over the prior green extractor/converter baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, layout annotation, ordering, supplied-document conversion, Markdown finalization, and WordPress smoke paths. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat prior selected-page trimming for full-document artifact lists, pdftext dictionary sorting, keep-chars sanitation, runtime preflight, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, or equation/image supplied-boundary work. The bounded behavior is sparse keyed supplied layout/order artifact matching to selected pdftext page numbers before WordPress import.
