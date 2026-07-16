# markerPDF pdftext dictionary layout-order scalar sidecar boundary current-base

## Source Truth

- Upstream `sddai/markerPDF` pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable page dictionaries through `marker/pdf/extract_text.py::get_text_blocks()`, which calls `pdftext.extraction.dictionary_output(...)` over the selected page range and enumerates those dictionaries into Marker pages.
- Upstream `marker/convert.py::convert_single_pdf()` deletes pages before `start_page` from the PDFium document before rendering layout/order images, then zips selected images, pages, layout predictions, and order predictions.
- Upstream `marker/layout/order.py::surya_order()` consumes only `order.image_bbox`, `order.bboxes`, and row `position` geometry after page selection. Native cache sidecars around a source-keyed map are adapter metadata, not model outputs.
- Source URLs previously checked for this supplied-boundary family:
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py

## Behavior

- Before the source edit, source-keyed supplied layout/order maps that also carried numeric scalar, null, metadata, or non-payload cache sidecars were not consistently reduced to the selected page payload.
- `PdfPageArtifactSelector` now ignores scalar/non-payload sidecars when normalizing direct source-keyed artifact maps and nested `pages` / `dictionary_output` / `pdftext` envelopes.
- Single selected source-keyed payloads inside wrappers keep their inner source-key page marker for mismatch rejection and copy only trusted scalar page-marker fields from the wrapper. Wrapper payloads and internal selector markers stay out of sanitized WordPress output.
- `LayoutAnnotator` and `LayoutOrderer` ignore numeric scalar/non-payload sidecars when scanning direct payload envelopes.
- `SuppliedDocumentConverter` accepts direct source-page keyed artifact maps with ignorable sidecars before selected-page artifact alignment.

## Tests

- Red-first probe before source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderScalarSidecarBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 3 assertions, 2 failures`; direct ordering stayed in source order and `lowres_images` rejected a source-keyed map with sidecars.
- Focused new test:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderScalarSidecarBoundaryCurrentBaseTest.php`
  - Result after fix: `1 test files, 38 assertions, 0 failures`
- Adjacent dictionary layout/order family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPageMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderMetadataSiblingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderWrapperGeometryEnvelopeCurrentBaseTest.php`
  - Result: `5 test files, 1041 assertions, 0 failures`
- Direct class-focused coverage:
  - `php tools/run-tests.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderScalarSidecarBoundaryCurrentBaseTest.php`
  - Result: `4 test files, 909 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-scalar-sidecar-currentbase.php`
  - Result: emits heading `First Scalar Sidecar Import Heading.`, paragraph `Second scalar sidecar import body.`, and flags `layout_scalar_sidecars_ignored=true`, `order_scalar_sidecars_ignored=true`, `first_before_second=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted JSON-decoded artifact normalization, source-keyed page-map ordering, metadata-sibling filtering, wrapper geometry envelopes, selected-page aliases, duplicate artifact rejection, non-finite markers, source-payload fallback, direct source-keyed list maps, or row-level page-marker filtering. The bounded behavior is scalar/null/non-payload sidecar tolerance in source-keyed layout/order artifact maps before selected pdftext dictionary assignment.

## Dependency Closure

No new support component is needed. This reuses the native supplied pdftext dictionary converter, page-artifact selector, layout annotator, layout orderer, finalizer, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium2 rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
