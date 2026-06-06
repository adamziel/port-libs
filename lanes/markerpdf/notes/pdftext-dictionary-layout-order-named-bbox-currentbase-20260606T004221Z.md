# markerPDF pdftext dictionary layout/order named bbox boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T004221Z`

Accepted base: `dfbe19b18b25966b701cf815e7f2abbcc322da8f`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates selected searchable-PDF pages to `pdftext.extraction.dictionary_output(...)`, then converts each selected dictionary page before layout/order sorting: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/layout/order.py::surya_order()` zips ordering predictions to selected pages, and `sort_blocks_in_reading_order()` applies each order row's geometry after rescaling from `order.image_bbox` into page space: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py
- The native no-GPU boundary accepts supplied layout/order artifacts from PHP adapters instead of running Surya. Adapter JSON may serialize bbox geometry as named object dictionaries such as `left/top/right/bottom` or `x/y/width/height`; those should remain geometry-only inputs and must not leak raw payload fields.

## Implemented Behavior

- `LayoutOrderer` now accepts named coordinate dictionary bboxes anywhere it consumes supplied order rows or layout boxes requested for ordering.
- Supported object shapes include `x0/y0/x1/y1`, `x1/y1/x2/y2`, `x_start/y_start/x_end/y_end`, `xmin/ymin/xmax/ymax`, `min_x/min_y/max_x/max_y`, `left/top/right/bottom`, and width/height forms such as `x/y/width/height`.
- Named order rows are normalized to canonical `[x1, y1, x2, y2]` geometry before the existing positive-area, position, assignment, and reading-order paths run.
- Raw adapter payload keys remain excluded from page order metadata and WordPress-visible text.
- Added a WordPress supplied-document smoke for selected pdftext dictionary pages with named layout and order bboxes.

## Focused Evidence

Focused baseline before edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 282 assertions, 0 failures
```

After source/test/example edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 309 assertions, 0 failures
```

Focused delta: +2 focused PASS cases, +27 focused assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, +1 WordPress smoke.

## Verification

```text
php -l lanes/markerpdf/src/LayoutOrderer.php
No syntax errors detected in lanes/markerpdf/src/LayoutOrderer.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-named-bbox-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-named-bbox-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 309 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
3 test files, 854 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-named-bbox-currentbase.php

php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "valid json\n";'
valid json

git diff --check -- lanes/markerpdf
```

The example emits `first_before_second=true`, `cover_excluded=true`, `raw_payload_excluded=true`, `layout_order_artifacts_trimmed=true`, `named_layout_bboxes_requested_for_ordering=[[60,92,290,150],[318,92,570,150]]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary extractor, selected-page artifact selector, supplied layout/order boundary, reading-order sorter, supplied-document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, pypdfium2/PDFium rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, benchmark runners, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected range slicing, sparse keyed matching, duplicate keyed reuse prevention, wrapper-list marker extraction, ambiguous array/list rejection, typed order-result wrapper sanitation, normalized order bboxes, bare bbox-list rows, associative position inference, polygon-only order rows, zero-area row rejection, layout-payload marker precedence, table named-bbox geometry, table/OCR polygon geometry, pdftext dictionary core sanitation, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically named object bbox dictionaries in supplied layout/order geometry before selected pdftext dictionary pages are converted to WordPress paragraphs.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
