# markerPDF pdftext dictionary layout-order BOM typed layout current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T155937Z`

Accepted base: `88918a69038ea1f5dab678b0be595fb89790e664`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF text to `pdftext.extraction.dictionary_output(..., page_range=..., keep_chars=False, ...)`, then enumerates those selected page dictionaries into Marker page objects.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip layout/order results against the selected page list after page-range trimming. In this no-GPU PHP lane, supplied JSON sidecars stand in for Surya/PDFium output without executing Python, OCR, models, raster rendering, or external PDF tools.

## Implemented Behavior

- `LayoutAnnotator::decodeDirectLayoutResultPayloadJsonEnvelope()` now strips a leading UTF-8 BOM at the explicit typed layout payload-envelope boundary before decoding raw JSON.
- This closes the parity gap with the already accepted artifact-envelope and order-result BOM handling: `layout_result.dictionary_output` can unwrap the current source-page keyed layout payload while stale cover-page payloads stay out of layout metadata and visible WordPress text.
- Added a focused PHP test covering direct layout annotation and a WordPress supplied-import path.
- Added `wordpress-pdftext-dictionary-layout-order-bom-typed-layout-currentbase.php` smoke for Gutenberg heading/body ordering with no Python/model/external-tool execution.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomTypedLayoutBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL unwraps BOM-prefixed typed layout-result payload envelopes before pdftext layout annotation
Expected: Title/Text layout bboxes
Actual: array ()
FAIL unwraps BOM-prefixed typed layout-result envelopes for WordPress supplied imports
String does not contain '# First Converter Bom Typed Layout Heading.'
1 test files, 13 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/LayoutAnnotator.php
No syntax errors detected in lanes/markerpdf/src/LayoutAnnotator.php

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomTypedLayoutBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomTypedLayoutBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-bom-typed-layout-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-bom-typed-layout-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomTypedLayoutBoundaryCurrentBaseTest.php
1 test files, 28 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomJsonArtifactEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedJsonEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php
3 test files, 109 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrder*CurrentBaseTest.php
21 test files, 1537 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-bom-typed-layout-currentbase.php
```

The smoke exits 0 with `bom_typed_layout_result_selected=true`, `stale_bom_typed_layout_payload_excluded=true`, `layout_assigned_pages=1`, `order_assigned_pages=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP pdftext dictionary converter, supplied artifact selector, layout annotator, layout orderer, Markdown finalizer, focused TestRunner, and WordPress smoke harness. Live `pdftext`, pypdfium2/PDFium rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, benchmark runners, raster rendering, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted pdftext page-list BOM decoding, top-level supplied artifact-envelope BOM decoding, typed order-result BOM decoding, plain raw JSON artifact decoding, source-keyed artifact maps, metadata sibling exclusion, selected page-range trimming, page marker aliases, ambiguous envelope rejection, row-level stale marker filtering, geometry envelope unwrapping, non-finite geometry guards, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work.

The bounded behavior is only UTF-8 BOM handling for raw JSON strings inside explicit typed `layout_result` payload envelopes.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
