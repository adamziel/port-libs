# markerPDF pdftext dictionary layout-order JSON artifact envelope current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T080807Z`

Accepted base: `576c1d07db28f80f3749bd13aa6f78dd425d4a62`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDFs through `marker/pdf/extract_text.py::get_text_blocks()`: it calls `pdftext.extraction.dictionary_output(..., page_range=..., keep_chars=False, ...)`, then enumerates the sliced dictionary pages into Marker page objects. Upstream `marker/layout/order.py::surya_order()` zips supplied ordering results with those selected pages, and `sort_blocks_in_reading_order()` sorts blocks using rescaled order bboxes. In the no-GPU PHP lane, caller-supplied layout/order artifacts stand in for Surya/PDFium outputs.

## Behavior

`PdfPageArtifactSelector` now decodes raw JSON strings only when they appear under supplied artifact envelope keys (`pages`, `dictionary_output`, or `pdftext`). The decoded artifact list then flows through the existing source-page keyed selection, ambiguity guards, page-marker checks, and payload sanitizers.

This lets WordPress import adapters cache layout/order artifacts as JSON strings next to pdftext dictionary output while preserving upstream-style selected-page alignment. Stale cover-page JSON payloads are excluded before layout annotation, reading-order assignment, metadata serialization, or Gutenberg text output.

## Red/Green Evidence

Pre-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL unwraps raw JSON source-keyed order artifact envelopes before selected pdftext assignment
FAIL unwraps raw JSON layout and order artifact envelopes for WordPress pdftext imports
1 test files, 12 assertions, 2 failures
```

Post-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS unwraps raw JSON source-keyed order artifact envelopes before selected pdftext assignment
PASS unwraps raw JSON layout and order artifact envelopes for WordPress pdftext imports
1 test files, 36 assertions, 0 failures
```

Adjacent family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderAmbiguousEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCamelCaseMarkerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDecimalKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderMetadataSiblingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageIdBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPolygonAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderScalarSidecarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderWrapperGeometryEnvelopeCurrentBaseTest.php
12 test files, 1277 assertions, 0 failures
```

Broader pdftext/converter check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryJsonStringEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
6 test files, 1654 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-json-artifact-currentbase.php
json_artifact_envelopes_selected=true
stale_json_artifact_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted pdftext page JSON envelope decoding, page-reference deduplication, selected page-range trimming, source-keyed artifact maps, decimal/camelCase/page_id markers, typed result wrappers, ambiguous envelope rejection, row-level stale marker filtering, or geometry envelope unwrapping. The bounded behavior is only raw JSON strings under supplied artifact envelope keys before layout/order artifact selection.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied artifact selector, layout annotator, layout orderer, pdftext dictionary converter, and WordPress smoke harness. Live OCR, Surya layout/order models, Torch/CUDA, PDFium rendering, pypdfium/PIL, raster image decoding, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
