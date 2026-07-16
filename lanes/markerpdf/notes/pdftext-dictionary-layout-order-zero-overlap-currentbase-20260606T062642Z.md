# markerPDF pdftext dictionary layout/order zero-overlap grouping boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T062642Z`

Accepted base: `ff6d9ac7ac50ba24390bdd95da205dfc798a98c3`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates selected searchable-PDF pages to `pdftext.extraction.dictionary_output(...)`, then Marker applies layout/order results to those selected pages.
- Upstream `marker/layout/order.py::sort_blocks_in_reading_order()` initializes each block with the first supplied order-box position even when `block.intersection_pct(order_bbox)` is `0.0`, then replaces it only when a later order box has a larger overlap. Blocks are then sorted inside the selected order group with `marker/pdf/utils.py::sort_block_group()`.
- The native no-GPU PHP lane accepts supplied order predictions from adapters instead of running Surya; it still needs to preserve the upstream grouping boundary before WordPress paragraph merge.

## Implemented Behavior

- `LayoutOrderer::sortBlocksInReadingOrder()` now assigns the first order-box position even for zero-overlap intersections, matching upstream maximum-overlap initialization.
- Blocks with later positive overlap still replace the first zero-overlap position.
- Pages with no supplied order boxes still use the existing fallback append behavior.
- The non-finite bbox sanitizer remains in place; after malformed rows are dropped, the remaining first order box follows the same upstream zero-overlap grouping rule.
- Added a focused regression and WordPress smoke for partial supplied order predictions on selected pdftext dictionary pages.

## Red-First Evidence

After adding the focused regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
FAIL keeps zero-overlap blocks in the first upstream order group before selected pdftext merge
Expected [Left partial order column has zero overlap but same upstream group, Right partial order column has the only supplied bbox]
Actual   [Right partial order column has the only supplied bbox, Left partial order column has zero overlap but same upstream group]
1 test files, 390 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 394 assertions, 0 failures
```

Adjacent checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
5 test files, 1535 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/LayoutOrderer.php
php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-zero-overlap-currentbase.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-nonfinite-bbox-currentbase.php
```

All changed PHP files reported no syntax errors.

WordPress smokes:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-zero-overlap-currentbase.php
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-nonfinite-bbox-currentbase.php
```

The new smoke emits `partial_order_prediction_assigned=true`, `zero_overlap_left_block_in_first_group=true`, `right_overlap_block_preserved=true`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case, +4 focused assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, and +1 WordPress smoke scenario.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected-page artifact selector, supplied orderer, Markdown finalizer, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, benchmark runners, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted selected page slicing, keyed artifact matching, wrapper-list ambiguity rejection, typed payload unwrapping, stale payload marker sanitation, duplicate artifact rejection, normalized/named/polygon/zero-area/non-finite geometry sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR, table recognition, or equation/image supplied-boundary work. The bounded behavior is specifically upstream zero-overlap order-box initialization for partial supplied order predictions on selected pdftext dictionary pages.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
