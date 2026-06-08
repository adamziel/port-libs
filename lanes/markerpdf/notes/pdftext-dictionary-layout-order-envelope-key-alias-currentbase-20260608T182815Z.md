# markerPDF pdftext dictionary layout/order envelope-key alias boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T182815Z`

Accepted base: `fe02d7a3097ad39446ef113ff421214b36621f31`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates selected searchable-PDF pages to `pdftext.extraction.dictionary_output(...)`, then converts those selected dictionary pages before layout/order assignment.
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip model outputs to selected Marker pages. In the native no-GPU PHP lane, supplied adapter artifacts must therefore be selected by trusted page identity before layout annotation or reading-order assignment.
- Direct source-keyed `pages` / `dictionary_output` maps are adapter cache identities. Explicit `page_number`, `document_page_number`, and `pdftext_page_number` metadata own one-based page-number aliases; source-map keys must not inherit that alias rule.

## Implemented Behavior

- `PdfPageArtifactSelector` no longer accepts `selected_page + 1` as a match for internal source-key envelope markers.
- `LayoutAnnotator` and `LayoutOrderer` apply the same stricter row-level marker check for direct source-keyed payloads.
- Explicit one-based page-number metadata remains accepted through the existing page-number marker fields.
- A selected-page WordPress import now rejects a source-keyed layout/order map whose only key is one above the selected pdftext page, preserving source order and preventing stale heading promotion.
- Raw adapter payloads and the internal `__markerpdf_envelope_page_key_marker` remain hidden from emitted text and metadata.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderEnvelopeKeyAliasBoundaryCurrentBaseTest.php
FAIL rejects source keyed order payload whose direct envelope key is only one above selected pdftext page
FAIL rejects source keyed layout and order payloads whose direct envelope keys are only one above selected WordPress page
1 test files, 5 assertions, 2 failures
```

The direct pdftext case reordered selected page `5701` from a stale source-key `5702` map. The WordPress case marked layout/order supplied for selected page `5711` from stale source-key `5712` artifacts.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderEnvelopeKeyAliasBoundaryCurrentBaseTest.php
1 test files, 29 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderEnvelopeKeyAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderMetadataSiblingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDuplicatePageKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomJsonArtifactEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPolygonAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedPayloadListBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBomTypedLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderRawPageArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageIdBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCoordinateOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedJsonEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderAmbiguousEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCamelCaseMarkerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonListEntryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDecimalDirectMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderWrapperGeometryEnvelopeCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMarkerConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderScalarSidecarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonKeyedValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDecimalKeyBoundaryCurrentBaseTest.php
25 test files, 1652 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php
2 test files, 75 assertions, 0 failures
```

Focused delta: +2 focused PASS cases and +29 assertions in `PdfTextDictionaryLayoutOrderEnvelopeKeyAliasBoundaryCurrentBaseTest.php`.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-envelope-key-alias-currentbase.php
```

The smoke emits `appendix_keyed_layout_excluded=true`, `appendix_keyed_order_excluded=true`, `source_order_preserved_without_trusted_order=true`, `no_heading_promotion=true`, `cover_excluded=true`, `supplied_boundaries=[]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected-page artifact selector, layout annotator, layout orderer, supplied-document converter, Markdown/WordPress rendering path, and focused TestRunner. Live `pdftext`, pypdfium/PDFium rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, benchmark runners, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted selected range slicing, sparse keyed matching, source-keyed exact matches, duplicate keyed rejection, typed payload wrappers, BOM/raw JSON envelopes, page-map envelopes, camelCase/page-id markers, explicit one-based page-number marker aliases, normalized/named/polygon/coordinate-order geometry, non-finite/zero-area bbox rejection, marker precedence, row-level stale marker filtering, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically rejecting a direct source-keyed layout/order payload map whose key is only `selected_pdftext_page + 1`.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
