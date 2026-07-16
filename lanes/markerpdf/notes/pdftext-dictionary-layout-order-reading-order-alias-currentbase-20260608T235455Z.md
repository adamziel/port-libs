# markerPDF pdftext dictionary layout/order reading-order aliases

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T235455Z`

Accepted base: `b48611b83a6995fd80354d1b5a87a4206fee1258`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)`, then enumerates only selected pdftext page dictionaries into Marker pages.
- `marker/layout/order.py::surya_order()` zips supplied ordering predictions to selected pages, and `sort_blocks_in_reading_order()` uses row order positions before geometry tie-breaking.
- Current PHP adapter sidecars may expose the row order under `reading_order`, `readingOrder`, `order_position`, or adjacent order/rank aliases instead of the already accepted `position` key.

## Implemented Behavior

- `LayoutOrderer::sanitizeSuppliedOrderBboxes()` now normalizes explicit order-position aliases before falling back to the accepted list-position inference.
- `position` keeps first precedence; malformed alias values still reject the row, matching the existing malformed `position` behavior.
- The new focused test proves selected pdftext pages and the WordPress supplied-document path sort by alias order fields even when source geometry and source row order would put the right column first.
- Added a WordPress smoke showing selected pdftext dictionary imports honor `reading_order` and `order_position` aliases while layout labels, page selection, and raw sidecar payload exclusion remain intact.

## Red-First Evidence

Before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderReadingOrderAliasBoundaryCurrentBaseTest.php
FAIL uses supplied reading_order rows before source geometry order for selected pdftext pages
Expected: ['First reading-order alias column', 'Second reading-order alias column']
Actual: ['Second reading-order alias column', 'First reading-order alias column']
FAIL uses readingOrder and order_position rows for WordPress supplied pdftext imports
1 test files, 14 assertions, 2 failures
```

The failure showed the current sanitizer preserved the selected order artifact but ignored alias positions, so rows defaulted to array order.

## Verification

```text
php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderReadingOrderAliasBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderReadingOrderAliasBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-reading-order-alias-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-reading-order-alias-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderReadingOrderAliasBoundaryCurrentBaseTest.php
1 test files, 29 assertions, 0 failures

php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfTextDictionaryLayoutOrder*CurrentBaseTest.php' | sort)
31 test files, 1817 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-reading-order-alias-currentbase.php
exit 0; emits reading_order_alias_applied=true, order_position_alias_applied=true, layout_assigned=true, order_assigned=true, cover_excluded=true, payload_excluded=true, executes_python_or_models=false, and executes_external_pdf_tools=false

git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page artifact selector, layout annotator, layout orderer, supplied-document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected range slicing, page marker aliases, source-keyed maps, pageMap precedence, direct payload envelopes, JSON artifact envelopes, duplicate artifact rejection, normalized bbox coordinate handling, positionless row inference, zero-overlap behavior, zero-area/non-finite rejection, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is only row-level ordering aliases for valid supplied order bbox dictionaries before pdftext block sorting.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
