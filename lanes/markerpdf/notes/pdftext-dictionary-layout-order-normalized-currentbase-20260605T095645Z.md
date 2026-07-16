# markerPDF pdftext dictionary layout/order normalized bbox boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T095645Z`

Accepted base: `7b0f5549743ece5423a911d7a77b4c45652c9c8d`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` obtains selected pdftext dictionary pages before marker layout/order runs.
- `marker/layout/order.py::sort_blocks_in_reading_order()` assigns blocks by overlap with supplied ordering bboxes after order image/page-space rescaling.
- Native no-GPU scope reuses supplied pdftext/order dictionaries and does not run Surya, OCR/layout models, PDFium rendering, Python workers, or external PDF tools.

## Implemented Behavior

- `LayoutOrderer` now detects normalized supplied order bboxes when `image_bbox` has real image/page dimensions and expands those fractions against `image_bbox` before existing page-space rescaling and overlap matching.
- Stored page `order` metadata preserves the original normalized order bboxes for review.
- Selected pdftext dictionary pages with absolute text bboxes now sort correctly when an adapter supplies normalized layout-order geometry.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rescales normalized supplied order boxes before pdftext dictionary layout assignment
1 test files, 3 assertions, 1 failures
```

The failure showed the selected pdftext page columns remained in source order because normalized order boxes were too small to overlap absolute pdftext bboxes.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rescales normalized supplied order boxes before pdftext dictionary layout assignment
1 test files, 9 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 321 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 779 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-normalized-currentbase.php
```

The WordPress smoke emits `normalized_order_bboxes_preserved_for_review=true`, `normalized_order_bboxes_scaled_for_assignment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case, +6 assertions in the new focused test after the red-first failure, +1 WordPress smoke.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, supplied artifact selector, layout-order overlap matcher, supplied-document conversion path, Markdown finalizer, and WordPress smoke path. Live pdftext/PDFium rendering, Surya layout/order/OCR/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat selected page-range slicing, sparse keyed artifact matching, nested adapter payload exclusion, source-page alias matching, numeric-string absolute bbox normalization, malformed order-row rejection, zero-area order-box rejection, rotated order-image handling, parser/xref repair, font/CMap/width behavior, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically normalized supplied order bboxes with absolute order-image dimensions before selected pdftext dictionary layout assignment.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and supplied-boundary behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and table/equation handoffs.
