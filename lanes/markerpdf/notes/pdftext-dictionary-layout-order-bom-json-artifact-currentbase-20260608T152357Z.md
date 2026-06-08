# markerPDF pdftext dictionary layout-order BOM JSON artifact envelope current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T152357Z`

Accepted base: `090466a02f0c71eccc4b93b9164c9203b62ed93c`

## Source Truth

Pinned upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDFs through `marker/pdf/extract_text.py::get_text_blocks()`: it calls `pdftext.extraction.dictionary_output(..., page_range=..., keep_chars=False, ...)`, then enumerates that sliced page list into Marker page objects.

The same upstream commit applies reading order in `marker/layout/order.py::surya_order()` by zipping selected pages with supplied order results; `sort_blocks_in_reading_order()` then sorts blocks from rescaled order bboxes. In this no-GPU PHP lane, supplied layout/order JSON sidecars stand in for Surya/PDFium output without executing models.

## Behavior

`PdfPageArtifactSelector::decodeSuppliedArtifactJsonEnvelope()` now strips a leading UTF-8 BOM only at explicit supplied-artifact JSON envelope boundaries before decoding `pages`, `dictionary_output`, `pdftext`, `page_map`, or `pageMap` payloads.

`LayoutOrderer::decodeDirectOrderResultPayloadJsonEnvelope()` applies the same boundary for typed order-result payload envelopes, so a selected wrapper such as `order_result.dictionary_output` can still unwrap the current source-page keyed payload.

This preserves upstream-style selected-page alignment for WordPress import caches that save layout/order artifacts as BOM-prefixed JSON strings. Stale cover-page payloads remain excluded from layout annotation, reading-order assignment, metadata serialization, and visible Gutenberg text.

## Red/Green Evidence

Pre-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomJsonArtifactEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL unwraps BOM-prefixed typed order-result payload envelopes before pdftext layout assignment
Values are not identical
Expected: array (
  0 => 'First BOM typed order column',
  1 => 'Second BOM typed order column',
)
Actual: array (
  0 => 'Second BOM typed order column',
  1 => 'First BOM typed order column',
)
FAIL unwraps BOM-prefixed layout and order artifact envelopes for WordPress pdftext imports
String does not contain '# First Converter Bom Artifact Heading.'
1 test files, 12 assertions, 2 failures
```

Post-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomJsonArtifactEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS unwraps BOM-prefixed typed order-result payload envelopes before pdftext layout assignment
PASS unwraps BOM-prefixed layout and order artifact envelopes for WordPress pdftext imports
1 test files, 36 assertions, 0 failures
```

Adjacent checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php
1 test files, 36 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 896 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBomJsonEnvelopeBoundaryCurrentBaseTest.php
1 test files, 24 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-bom-json-artifact-currentbase.php
bom_json_artifact_envelopes_selected=true
stale_bom_json_artifact_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted pdftext page-list BOM decoding, plain raw JSON artifact-envelope decoding, source-keyed artifact maps, metadata sibling exclusion, selected page-range trimming, page marker aliases, typed result wrappers, ambiguous envelope rejection, row-level stale marker filtering, geometry envelope unwrapping, nonfinite geometry guards, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work.

The bounded behavior is only UTF-8 BOM handling for raw JSON strings at explicit supplied layout/order artifact envelope decode boundaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied artifact selector, layout annotator, layout orderer, pdftext dictionary converter, focused TestRunner, and WordPress smoke harness. Live `pdftext`, pypdfium2/PDFium rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, benchmark runners, raster decoding, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
