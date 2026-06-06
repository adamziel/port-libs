# markerPDF pdftext dictionary layout-order JSON artifact boundary current-base

## Source Truth

- Upstream `sddai/markerPDF` pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable page dictionaries through `marker/pdf/extract_text.py::get_text_blocks()`, which calls `pdftext.extraction.dictionary_output(...)` over the selected page range and then enumerates those selected dictionaries into Marker pages.
- Upstream `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before rendering layout/order images, then calls `surya_layout(...)`, `annotate_block_types(...)`, `surya_order(...)`, and `sort_blocks_in_reading_order(...)`.
- Upstream `marker/layout/order.py::surya_order()` zips supplied images/pages/order results, and `sort_blocks_in_reading_order()` uses `order.image_bbox`, `order.bboxes`, and `position` after rescaling to page coordinates.
- Source URLs checked for this slice:
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py

## Behavior

- Native supplied pdftext dictionaries already accepted JSON-decoded page `stdClass` values, but supplied layout/order artifacts did not.
- Before the source edit, a JSON-decoded supplied order artifact raised `InvalidArgumentException: Supplied ordering predictions must be arrays.` in `LayoutOrderer::runWithSuppliedOrder()`.
- `PdfPageArtifactSelector` now recursively normalizes JSON plain objects (`stdClass`) to arrays before page-marker selection.
- `LayoutAnnotator` and `LayoutOrderer` also normalize their supplied image/result artifact lists at direct entry points, so direct callers do not depend on the selector being used first.
- Payload filtering remains unchanged: copied `raw_payload`, typed wrapper payloads, and stale cover-page artifacts stay out of WordPress text and review metadata.

## Tests

- Red-first probe before source edit:
  - `php -r '... PdfTextDocumentExtractor()->getOrderedTextBlocks(... stdClass order artifact ...)'`
  - Result: fatal `InvalidArgumentException: Supplied ordering predictions must be arrays.`
- Focused edited test:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`
  - Result after fix: `1 test files, 436 assertions, 0 failures`
- Adjacent layout/order/pdftext family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php`
  - Result: `4 test files, 809 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-json-artifact-currentbase.php`
  - Result: emits a heading `First Json Artifact Import Heading.`, a paragraph `Second JSON artifact import column.`, and summary flags `json_decoded_plain_objects_normalized=true`, `layout_assigned_pages=1`, `order_assigned_pages=1`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted normalized bbox scaling, typed result wrappers, trusted metadata precedence, `pdftext_source`, page index/page number aliases, source payload fallback, duplicate keyed artifacts, non-finite markers, row-level page-marker filtering, zero-overlap grouping, or generic pdftext dictionary sanitation. The bounded behavior is only JSON-decoded plain-object supplied artifacts crossing the pdftext dictionary to layout/order boundary.

## Dependency Closure

No new support component is needed. This reuses the native supplied pdftext dictionary converter, page-artifact selector, layout annotator, layout orderer, finalizer, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch layout/order/OCR models, Texify, tabled-pdf, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
