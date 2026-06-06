# markerPDF pdftext dictionary layout/order typed direct-envelope boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T170443Z`

Base accepted HEAD: `cd0e5891c156b74b93e3a6ddb7bf05dd8f35f257`

## Source Truth

- Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains selected pdftext dictionary pages before layout/order assignment.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order rendering.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip one layout/order model result object per selected Marker page.
- Native no-GPU adapters can store the typed model result under `layout_result` / `order_result`, while the geometry payload is cached inside one `pages` or `dictionary_output` envelope. That envelope is geometry-only input and must not become WordPress-visible payload.

## Implementation

- `LayoutAnnotator::layoutResultPayloadSource()` now applies the existing single direct-envelope fallback to each collected typed layout payload source, not only the root artifact.
- `LayoutOrderer::orderResultPayloadSource()` applies the same fallback for typed ordering payloads.
- Existing fail-closed behavior remains: multi-dictionary envelopes are not selected, ambiguous wrapper lists stay rejected, page identity still comes from trusted outer metadata, and sanitized layout/order metadata excludes raw payload dictionaries.

## Red-First Evidence

After adding the focused cases and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
=> 1 test files / 558 assertions / 2 failures
```

Failures:

- `unwraps typed direct-envelope order payloads before selected pdftext layout assignment` stayed in source order because the nested order geometry was ignored.
- `unwraps typed direct-envelope layout and order payloads for WordPress imports` did not promote the selected heading because the nested layout geometry was ignored.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
=> 1 test files / 579 assertions / 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-typed-direct-envelope-currentbase.php
=> emitted typed_layout_direct_envelope_unwrapped=true, typed_order_direct_envelope_unwrapped=true, heading_promoted=true, payload_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

Focused delta: +2 focused PASS cases, +33 assertions against the accepted 546-assertion focused file, +1 mapped manifest behavior, and +1 WordPress smoke.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page artifact selector, layout annotator, layout orderer, supplied-document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat selected range slicing, cached full-list `dictionary_output`/`pages` envelope unwrapping, root direct-envelope payload unwrapping, typed `page_result` / `page_data` payload wrappers, trusted metadata precedence, `pdftext_source` wrappers, JSON-decoded artifact normalization, named/polygon/normalized/non-finite/zero-area bbox handling, duplicate artifact rejection, row-level page-marker filtering, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically a typed `layout_result` or `order_result` wrapper that contains one direct `pages` or `dictionary_output` geometry payload for selected pdftext dictionary layout/order assignment.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser/converter behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
