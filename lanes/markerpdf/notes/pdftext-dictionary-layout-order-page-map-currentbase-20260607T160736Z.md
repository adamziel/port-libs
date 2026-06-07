# markerPDF pdftext dictionary layout order page-map current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260607T160736Z`

## Source Truth

- Upstream `marker/pdf/extract_text.py::get_text_blocks()` consumes the ordered list returned by `pdftext.extraction.dictionary_output(...)` over the selected `page_range`.
- Upstream `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order assignment.
- Upstream `marker/layout/order.py::surya_order()` zips order predictions with selected Marker pages, so native supplied page dictionaries must be in source-page order before applying `start_page` and `max_pages`.

## Change

- `PdfTextDocumentExtractor` now recognizes numeric source-page keyed pdftext dictionary page maps and orders them by key before upstream-style page slicing.
- The boundary is limited to page-shaped maps whose values contain pdftext `blocks`; ordinary list outputs and non-page envelopes keep existing behavior.
- Added a focused test file and a WordPress smoke proving selected page text, layout labels, order bboxes, and raw payload exclusion survive out-of-order keyed dictionary maps.

## Red-First Evidence

Before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL orders source-keyed pdftext dictionary page maps before selected layout order assignment
Expected: 7101
Actual: 7100
FAIL orders source-keyed pdftext dictionary page maps before WordPress supplied imports
Expected: ['layout', 'order']
Actual: []
1 test files, 4 assertions, 2 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS orders source-keyed pdftext dictionary page maps before selected layout order assignment
PASS orders source-keyed pdftext dictionary page maps before WordPress supplied imports
1 test files, 24 assertions, 0 failures
```

Adjacent family verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPolygonAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
5 test files, 1940 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-map-currentbase.php
```

Emits `source_keyed_dictionary_output_ordered=true`, `layout_assigned=true`, `order_assigned=true`, `heading_before_body=true`, `cover_excluded=true`, `appendix_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat prior sparse keyed layout/order artifact matching, direct payload envelope unwrapping, source-keyed artifact maps, singleton keyed mismatch rejection, polygon/bbox alias geometry, pdftext dictionary core sanitation, parser/xref repair, fonts/CMaps, image/filter metadata, annotations/forms/security, or OCR/model execution. The bounded behavior is only numeric source-page keyed pdftext dictionary page maps being ordered before selected-page layout/order assignment.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, page-range slicing, layout annotation, layout ordering, supplied-document conversion, Markdown finalization, and the WordPress smoke harness. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
