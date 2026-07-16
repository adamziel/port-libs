# markerPDF pdftext dictionary layout order keyed mismatch current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260604T145613Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` over the selected `page_range` and enumerates those selected dictionary pages into Marker pages.
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before rendering layout/order images.
- `marker/layout/order.py::surya_order()` zips order predictions to the selected Marker pages, and `sort_blocks_in_reading_order()` applies the selected page's order boxes.

## Implemented Behavior

- `PdfPageArtifactSelector` now resolves marker-bearing artifacts by page identity before selected-only positional fallback.
- A sparse keyed artifact list that happens to have the same count as the selected page range no longer assigns an unselected cover-page layout/order artifact to the selected pdftext page.
- Selected-only artifact lists without page markers still keep positional zip-style behavior.
- Added lower-level `PdfTextDocumentExtractor` and WordPress-facing `SuppliedDocumentConverter` regression cases.
- Added a WordPress smoke for keyed layout/order artifacts that identify an unselected page.

## Verification

- `php -l lanes/markerpdf/src/PdfPageArtifactSelector.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-keyed-mismatch-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: 2 test files / 611 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: 4 test files / 661 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-keyed-mismatch-currentbase.php` passed and emitted `layout_artifacts_excluded=true`, `order_artifacts_excluded=true`, `cover_excluded=true`, and `source_order_preserved_without_matching_order=true`.
- `git diff --check -- lanes/markerpdf` passed.

Focused delta: +2 focused PASS cases over the prior green extractor/converter baseline.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed page-artifact selection, layout annotation, ordering, supplied-document conversion, Markdown finalization, and WordPress smoke paths. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat prior selected-page trimming for full-document artifact lists, sparse keyed artifact matching when keys match the selected page, pdftext dictionary sorting, keep-chars sanitation, blank page handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically mismatched keyed supplied layout/order artifact exclusion before selected pdftext dictionary pages are imported into WordPress.
