# Layout Table OCR Page Review Section Order Current Base

Slice: `layout-table-ocr-page-review-section-order-currentbase`
Session: `port-dev-markerpdf-layout72-20260602T221239Z`
Base accepted HEAD: `36d3abb94323edf47dc54936168141773ec380c2`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` runs `marker.convert::convert_single_pdf()` in this order: `annotate_block_types()`, `surya_order()`, `sort_blocks_in_reading_order()`, then `marker.tables.table::format_tables()`.
- Upstream `marker/layout/order.py::sort_blocks_in_reading_order()` assigns each block to the ordering bbox with maximum intersection, sorts by order position, and pins page headers/footers.
- Upstream `marker/tables/table.py::format_tables()` removes only intersecting `Table` blocks and inserts recognized table Markdown at the sorted table insertion point, preserving surrounding `Section-header`, `Caption`, and text blocks.
- Locked `tabled-pdf` table recognition keeps cell row/column structure before Markdown/HTML output drops covered span/grid context, so native WordPress review metadata needs to preserve the table's sorted context before final Markdown cleanup.

## Implementation

- `TableFormatter::formatTables()` now adds `table_context_reviews[*].section_order`.
- The new review records the final sorted section/table/caption role order, table insertion point, final block indexes after stale table removal, matched table block indexes, supplied-order positions/intersection bboxes, and whether page-review metadata was attached.
- The metadata is review-only and does not alter visible Markdown, table replacement, layout ordering, OCR routing, or page-review extraction.
- Added a current-base converter test proving a deliberately unordered pdftext source is corrected by supplied layout/order predictions before forced-OCR table replacement and page-review attachment.
- Added `examples/wordpress-layout-table-ocr-page-review-section-order-currentbase.php` as the WordPress smoke.

## Focused Evidence

- `php -l lanes/markerpdf/src/TableFormatter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfLayoutTableOcrPageReviewSectionOrderCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-layout-table-ocr-page-review-section-order-currentbase.php`: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLayoutTableOcrPageReviewSectionOrderCurrentBaseTest.php`: 1 file, 28 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/PdfLayoutTableOcrPageReviewSectionOrderCurrentBaseTest.php`: 3 files, 526 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/PdfLayoutTableOcrPageReviewSectionOrderCurrentBaseTest.php`: 4 files, 551 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-layout-table-ocr-page-review-section-order-currentbase.php`: passed; emitted section/table/caption final order, supplied-order positions `[0,1,2]`, overlapping annotation object `21`, stale table text excluded, outside page-review annotation excluded, and native-only execution flags.

## Status Delta

- Focused PHP behavior tests move `895 -> 896 pass / 0 fail`.
- WordPress scenarios move `895 -> 896`.
- Mapped semantics move `631 -> 632 / 78`.

## Dependency Closure

No new support component is needed. The slice reuses native supplied-document conversion, layout annotation/order, table formatting, forced-OCR detector-cell routing, and page-review metadata composition. Full upstream parity remains gated by the Python/PDF/model stack: `pdftext`, `pypdfium2`, Surya/Torch OCR/layout/order, `tabled-pdf` model execution, Texify, OCRMyPDF/Tesseract/Ghostscript setup, Streamlit/FastAPI runtimes, and upstream benchmark workflows.

## Non-Overlap

This does not repeat accepted rotated layout ordering, table span-grid section/caption preservation, rowspanned accessibility IDs, OCR polygon/grid-border geometry, forced-OCR table structure assignment, or page StructTree/annotation review extraction. The bounded behavior is the final section/table/caption order review that joins upstream layout reading order, forced-OCR table replacement, and page-review metadata for WordPress import.
