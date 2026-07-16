# markerPDF pdftext dictionary layout order boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260602T230441Z`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` slices `dictionary_output(...)` by `page_range` and enumerates the selected result when converting pdftext dictionaries into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/layout/order.py::surya_order()` zips supplied order predictions with selected pages, and `sort_blocks_in_reading_order()` rescales `order.image_bbox` boxes into page space before ordering blocks and pinning page headers/footers: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Implemented behavior

- Added `PdfTextDocumentExtractor::getOrderedTextBlocks()` as a native supplied-data bridge from pdftext dictionary page extraction into supplied layout-order sorting.
- The bridge keeps upstream page-range semantics: skipped cover pages are excluded, selected-page span IDs restart from `0_*`, page `pnum` remains the original PDF page number, and order predictions zip against the selected page list rather than the unsliced source list.
- The returned metadata now records `order_plan` and the supplied boundaries `pdftext-dictionary` and `layout-order` for WordPress import review.
- Added a WordPress smoke that renders selected two-column pdftext dictionary output in supplied order and records no Python/model/external-PDF-tool execution.

## Verification

- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php` passed: 2 test files, 52 assertions, 0 failures, 16 PASS lines.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-currentbase.php` passed and emitted ordered Gutenberg paragraph output plus boundary metadata.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency closure

No new support component is needed. This slice reuses the native pdftext dictionary converter, layout orderer, Markdown post-processor, and WordPress smoke path. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya layout/order models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark workflows, and external PDF/model execution.

## Non-overlap

This does not repeat accepted parser/xref recovery, font/CMap/width boundaries, image/filter review, page-box preview geometry, rotated order-image handling, standalone supplied document pipeline conversion, JSON/output/runtime handoffs, or page annotation/StructTree review slices. The bounded behavior is specifically selected pdftext dictionary pages flowing into supplied layout-order sorting before WordPress paragraph merge.
