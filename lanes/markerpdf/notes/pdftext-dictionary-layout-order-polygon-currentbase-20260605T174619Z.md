# markerPDF pdftext dictionary layout/order polygon geometry boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T174619Z`

Accepted base: `165d00972e222ec74a0a4ac65ceaafba6ceef98e`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates selected searchable-PDF pages to `pdftext.extraction.dictionary_output(...)`, then converts each selected dictionary page before layout/order sorting: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/layout/order.py::surya_order()` zips ordering predictions to selected pages, and `sort_blocks_in_reading_order()` applies each order row's geometry after rescaling from `order.image_bbox` into page space: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py
- Surya layout/order JSON exposes four-corner `polygon` geometry alongside axis-aligned boxes. Native supplied adapters may therefore provide polygon-only order rows; the PHP no-GPU boundary can reduce those to bboxes without running Surya, PDFium, or pdftext.

## Implemented Behavior

- `LayoutOrderer::sanitizeSuppliedOrderBboxes()` now accepts a valid four-corner `polygon` when an associative order row has no usable `bbox`.
- The polygon is normalized to `[min_x, min_y, max_x, max_y]` before the existing positive-area, position, image-bbox scaling, and overlap sorting paths run.
- Raw order adapter payload keys still stay out of page `order` metadata and WordPress-visible text.
- Added a WordPress smoke for polygon-only supplied order rows on selected pdftext dictionary pages.

## Red-First Evidence

After adding the focused regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
FAIL uses polygon-only order rows as bbox geometry before pdftext layout assignment
Expected: ['First polygon order column', 'Second polygon order column']
Actual: ['Second polygon order column', 'First polygon order column']

1 test files, 185 assertions, 1 failures
```

The failure showed polygon-only order rows were ignored, leaving the selected pdftext dictionary page in source order.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 192 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php
1 test files, 25 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
1 test files, 287 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
1 test files, 779 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-polygon-currentbase.php
```

The example emits `polygon_order_rows_used=true`, `polygon_order_artifact_assigned=true`, `cover_excluded=true`, `raw_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case, +7 focused assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, +1 WordPress smoke.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary extractor, supplied page artifact selector, layout/order sanitizer, reading-order sorter, supplied-document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, pypdfium2/PDFium rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, benchmark runners, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected range slicing, sparse keyed matching, duplicate keyed reuse prevention, wrapper-list marker extraction, ambiguous array/list rejection, order-result wrapper sanitation, normalized order bboxes, bare bbox-list rows, associative position inference, zero-area row rejection, layout polygon annotation, table/OCR polygon geometry, pdftext dictionary core sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically polygon-only supplied order rows before selected pdftext dictionary pages are converted to WordPress paragraphs.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
