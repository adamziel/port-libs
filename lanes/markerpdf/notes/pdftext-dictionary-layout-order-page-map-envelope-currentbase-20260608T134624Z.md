# markerPDF pdftext dictionary layout/order page_map envelope current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T134624Z`

## Source Truth

- Upstream `sddai/markerPDF` pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable page dictionaries through `marker/pdf/extract_text.py::get_text_blocks()`, which calls `pdftext.extraction.dictionary_output(...)` over the selected page range and enumerates those dictionaries into Marker pages.
- Upstream `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before rendering layout/order images, then zips selected images, pages, layout predictions, and order predictions.
- Upstream `marker/layout/order.py::surya_order()` consumes only ordering `image_bbox`, `bboxes`, and row `position` geometry after page selection. Native `page_map`/`pageMap` sidecar names are adapter cache envelopes around that page-list boundary, not model payload text.
- Source URLs previously checked for this supplied-boundary family:
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Behavior

- Before the source edit, a supplied pdftext dictionary cache shaped as `page_map => {source_page => page_dictionary}` was treated as one wrapper page. With `start_page=1`, selected-page extraction failed before layout/order assignment.
- `PdfTextDocumentExtractor` now accepts `page_map` and `pageMap` as page-list envelope aliases beside existing `dictionary_output`, `pdftext`, and `pages`.
- `PdfPageArtifactSelector` now unwraps `page_map`/`pageMap` layout, order, and image artifact envelopes before page-marker selection.
- `LayoutAnnotator` and `LayoutOrderer` accept direct `page_map`/`pageMap` payload envelopes for selected singleton layout/order payloads.
- Wrapper metadata, stale cover artifacts, raw payload strings, and internal selector page-key markers remain excluded from sanitized WordPress output.

## Tests

- Red-first probe before source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapEnvelopeBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 0 assertions, 2 failures`
  - Failure: `start_page must be within supplied pdftext pages.`
- Focused new test after fix:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapEnvelopeBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 43 assertions, 0 failures`
- Adjacent dictionary layout/order family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrder*CurrentBaseTest.php`
  - Result: `16 test files, 1392 assertions, 0 failures`
- Direct class-focused coverage:
  - `php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapEnvelopeBoundaryCurrentBaseTest.php`
  - Result: `5 test files, 1214 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-page-map-envelope-currentbase.php`
  - Result: emits heading `First Page Map Envelope Import Heading.`, paragraph `Second page map envelope import body.`, and flags `layout_page_map_envelope_unwrapped=true`, `order_page_map_envelope_unwrapped=true`, `first_before_second=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +2 focused PASS cases, +43 focused assertions in the new current-base test, and +1 WordPress smoke scenario.

## Non-Overlap

This does not repeat accepted source-keyed `dictionary_output`/`pdftext`/`pages` map ordering, direct source-key artifact maps, metadata-sibling filtering, wrapper geometry envelopes, scalar sidecar tolerance, direct-key marker conflict rejection, JSON artifact envelopes, selected-page aliases, duplicate artifact rejection, row-level page-marker filtering, normalized/named/polygon order geometry, parser/xref repair, fonts/CMaps/widths, images/filter metadata, annotations/forms/security, OCR/model handoffs, table recognition, or equation/image supplied-boundary work. The bounded behavior is only `page_map`/`pageMap` envelope aliasing for supplied pdftext dictionaries and layout/order artifacts before selected-page assignment.

## Dependency Closure

No new support component is needed. This reuses the native supplied pdftext dictionary converter, page-artifact selector, layout annotator, layout orderer, supplied-document converter, finalizer, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium2 rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
