# markerPDF pdftext dictionary layout/order direct envelope payload boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T162118Z`

Base accepted HEAD: `b0745b711922fec4e93573eb719ea5fcb3413b9d`

## Source Truth

- Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains selected pdftext dictionary pages before layout/order assignment.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document to the selected page range before layout/order rendering, and `marker/layout/layout.py::surya_layout()` plus `marker/layout/order.py::surya_order()` zip one model result per selected Marker page.
- Native no-GPU adapters can cache page identity at the outer artifact while storing one direct layout/order payload inside pdftext-shaped `pages` or `dictionary_output` envelopes. That single payload should be geometry-only input, not visible WordPress text or review payload.

## Implementation

- `LayoutAnnotator::layoutResultPayloadSource()` now falls back to one direct payload stored under `pages` or `dictionary_output` when the artifact itself has no `image_bbox` or `bboxes`.
- `LayoutOrderer::orderResultPayloadSource()` applies the same direct-envelope payload fallback for supplied order geometry.
- Multi-dictionary `pages` or `dictionary_output` envelopes stay fail-closed through the empty-payload path; the existing ambiguous-wrapper walker is unchanged.
- Page identity still comes from the outer artifact's trusted markers, and sanitized layout/order rows still exclude `raw_payload`, envelope dictionaries, and copied pdftext text from WordPress output.

## Red-First Evidence

After adding the focused cases and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
=> 1 test files / 525 assertions / 2 failures
```

Failures:

- `unwraps direct dictionary-output payload envelopes before selected pdftext layout order assignment` stayed in source order because the nested order geometry was ignored.
- `unwraps direct pages and dictionary-output payload envelopes for WordPress imports` did not promote the heading because the nested layout payload was ignored.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
=> 1 test files / 546 assertions / 0 failures
```

Adjacent layout/order family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
=> 5 test files / 1715 assertions / 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-direct-envelope-currentbase.php
```

The smoke emits `direct_pages_payload_unwrapped=true`, `direct_dictionary_output_payload_unwrapped=true`, `heading_promoted=true`, `heading_before_body=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases, +21 assertions in `PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php`, +1 mapped manifest behavior, and +1 WordPress smoke.

Syntax and hygiene:

```text
php -l lanes/markerpdf/src/LayoutAnnotator.php
php -l lanes/markerpdf/src/LayoutOrderer.php
php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-direct-envelope-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page artifact selector, layout annotator, layout orderer, supplied-document converter, Markdown finalizer, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat selected range slicing, cached full-list `dictionary_output`/`pages` envelope unwrapping, direct pdftext page-list source counting, top-level keyed matching, wrapper-list marker extraction, typed `page_result`/`layout_result`/`order_result` payload wrappers, trusted metadata precedence, `pdftext_source` wrappers, JSON-decoded artifact normalization, named/polygon/normalized/non-finite/zero-area bbox handling, duplicate artifact rejection, row-level page-marker filtering, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is only one direct layout/order payload stored under `pages` or `dictionary_output` while page identity remains on the selected outer artifact.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser/converter behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
