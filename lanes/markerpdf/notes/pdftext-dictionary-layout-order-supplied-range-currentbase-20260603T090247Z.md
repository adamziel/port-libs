# markerPDF pdftext dictionary layout order supplied range current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260603T085712Z`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)` and converts the selected dictionary pages into Marker page blocks: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/convert.py::convert_single_pdf()` removes pages before `start_page` from the PDFium document before rendering low-resolution images and running layout/order stages, so layout/order artifacts are selected-page aligned before `surya_order()` zip assignment: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- `marker/layout/order.py::surya_order()` zips order predictions with pages and `sort_blocks_in_reading_order()` sorts by supplied order boxes: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Implemented behavior

- `SuppliedDocumentConverter` now slices full-document `lowres_images`, `layout_results`, `order_images`, and `order_results` to the selected `page_range` after pdftext dictionary slicing and before layout/order assignment.
- Selected-only artifact lists remain unchanged, preserving the accepted selected-page bridge and zip-style model result behavior.
- Added a regression proving a skipped cover-page layout/order result cannot be assigned to a selected pdftext page when `start_page=1` and `max_pages=1`.
- Added a WordPress smoke that emits selected-page paragraph output plus review metadata showing both layout and order artifacts were trimmed.

## Verification

- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-supplied-range-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php` passed: 4 test files, 609 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-supplied-range-currentbase.php` passed and emitted `layout_artifacts_trimmed:true`, `order_artifacts_trimmed:true`, selected-page text, and excluded cover/appendix text.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency closure

No new support component is needed. This reuses the native supplied pdftext dictionary converter, layout annotator, layout orderer, supplied document converter, Markdown finalizer, and WordPress smoke path. Full upstream parity remains gated on live `pdftext`, pypdfium2/PDFium, Surya layout/order/OCR models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark workflow tooling, and external OCR/rendering helpers.

## Non-overlap

This does not repeat accepted pdftext dictionary keep_chars sanitation, span text postprocessing, `PdfTextDocumentExtractor::getOrderedTextBlocks()` selected-order bridge, full-document order artifact trimming in that lower-level helper, rotated order-image handling, parser/xref recovery, fonts/CMaps/widths, image/filter metadata, page-box preview geometry, or table/equation supplied-boundary conversion. The bounded behavior is specifically selected `SuppliedDocumentConverter` page-range alignment for full-document supplied layout/order artifacts before WordPress import.
