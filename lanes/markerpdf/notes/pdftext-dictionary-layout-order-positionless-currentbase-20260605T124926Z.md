# markerPDF pdftext dictionary layout/order positionless bbox rows

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T124926Z`

Accepted base: `c1cf1f37714011b48942dddb280e21fdc933c11e`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=...)`, then enumerates only those selected dictionaries into Marker pages.
- `marker/layout/order.py::surya_order()` zips supplied ordering results to selected Marker pages, and `sort_blocks_in_reading_order()` uses each order bbox row's model order before falling back to geometry within equal-position groups.
- Native supplied artifacts can arrive as JSON dictionaries with only `bbox` rows. When `position` is missing, the stable row sequence is the only model-order signal available before WordPress paragraph merge.

## Implemented Behavior

- `LayoutOrderer::sanitizeSuppliedOrderBboxes()` now infers `position` from `index + 1` for any valid order bbox row that omits `position`.
- Explicit `position` values are still preserved, including `0` for page-edge header/footer order rows.
- The new focused test proves positionless bbox dictionaries preserve supplied row order instead of collapsing into position `0` and falling back to left-to-right geometry.
- Added a WordPress smoke showing selected pdftext dictionary pages import in supplied positionless order while raw adapter payload strings remain review-only and excluded from output metadata.

## Red-First Evidence

Before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
FAIL infers missing order positions from bbox dictionary row order before pdftext layout assignment
Expected: ['Second positionless row supplied first', 'First positionless row supplied second']
Actual: ['First positionless row supplied second', 'Second positionless row supplied first']
1 test files, 33 assertions, 1 failures
```

The failure showed positionless bbox dictionaries collapsed into the same order position and were then sorted by page geometry.

## Verification

```text
php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-positionless-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-positionless-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php
3 test files, 352 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-positionless-currentbase.php
exit 0; emits positionless_rows_ordered=true, cover_excluded=true, raw_payload_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false

git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page-range handling, supplied artifact selector, layout ordering sanitizer, supplied-document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected range slicing, sparse keyed matching, duplicate keyed reuse prevention, wrapper-list marker extraction, ambiguous array/list rejection, ordering-result payload sanitation, normalized order bboxes, bare bbox-list rows, zero-area row rejection, pdftext dictionary core sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is only position inference for valid associative order bbox dictionaries that omit `position`.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and converter behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
