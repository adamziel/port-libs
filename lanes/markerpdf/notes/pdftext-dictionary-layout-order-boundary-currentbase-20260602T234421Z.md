# markerPDF pdftext dictionary layout order boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260602T234421Z`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` builds `page_range`, calls `pdftext.extraction.dictionary_output(..., keep_chars=False)`, and enumerates the selected dictionary result into Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before rendering low-resolution order images, so layout/order model artifacts are page-range aligned before `surya_order()` zips predictions with Marker pages: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- `marker/layout/order.py::surya_order()` uses zip-style assignment between pages and ordering results, and `sort_blocks_in_reading_order()` sorts by rescaled order boxes before pinning headers and footers: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Implemented behavior

- `PdfTextDocumentExtractor::getOrderedTextBlocks()` now trims supplied order images/results to the selected `page_range` when those artifact lists still span the original pdftext page list.
- Selected-only order artifacts remain unchanged, preserving the accepted selected-page layout-order bridge.
- Added a focused test proving a skipped cover-page order result cannot be zipped onto the selected pdftext dictionary page.
- Updated the WordPress smoke to pass full-document order artifacts and report `full_document_order_artifacts_trimmed=true` while still excluding cover and appendix page text.

## Verification

- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php` passed: 2 test files, 66 assertions, 0 failures, 18 PASS lines.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-currentbase.php` passed and emitted `full_document_order_artifacts_trimmed=true`.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency closure

No new support component is needed. This slice reuses the native pdftext dictionary converter, supplied layout-order bridge, Markdown post-processor, and WordPress smoke path. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya layout/order models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark workflows, and external PDF/model execution.

## Non-overlap

This does not repeat accepted keep_chars=false sanitation, the earlier selected-only pdftext dictionary layout-order bridge, rotated order-image handling, parser/xref recovery, fonts/CMaps/widths, image/filter metadata, page-box preview geometry, table/equation supplied-boundary conversion, runtime/output handoffs, or security/form/annotation/metadata slices. The bounded behavior is specifically trimming full-document supplied order artifacts to the selected pdftext dictionary page range before zip-style order assignment.
