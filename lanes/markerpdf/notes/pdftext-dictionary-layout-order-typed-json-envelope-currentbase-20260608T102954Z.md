# markerPDF pdftext dictionary layout/order typed JSON envelope current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T102954Z`

Accepted base: `6c009f4b63e232febe2df2538598096a435fd432`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., page_range=..., keep_chars=False, ...)` and enumerates the selected dictionary pages into Marker page objects: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip supplied model outputs with the already-selected Marker pages, so typed native adapter payloads must be matched to the selected pdftext page before assignment: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/layout.py and https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py

## Behavior

`LayoutAnnotator` and `LayoutOrderer` now decode raw JSON only when it appears under explicit `pages`, `dictionary_output`, or `pdftext` payload-envelope keys inside typed `layout_result` or `order_result` wrappers. Source-page keyed JSON maps then reuse the existing selected-page marker matcher, and only a unique current-page candidate is accepted before layout annotation or reading-order sorting.

This preserves the no-GPU supplied-boundary contract for WordPress import adapters that cache Surya-style layout/order payloads as JSON strings under typed result wrappers. Stale cover-page JSON payloads are rejected before block-type annotation, reading-order assignment, metadata serialization, or Gutenberg text output.

## Red/Green Evidence

Pre-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedJsonEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL selects source-keyed typed JSON order_result envelopes before pdftext layout assignment
FAIL selects source-keyed typed JSON layout_result and order_result envelopes for WordPress imports
1 test files, 12 assertions, 2 failures
```

Post-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedJsonEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS selects source-keyed typed JSON order_result envelopes before pdftext layout assignment
PASS selects source-keyed typed JSON layout_result and order_result envelopes for WordPress imports
1 test files, 37 assertions, 0 failures
```

Adjacent family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfTextDictionaryLayoutOrder*Test.php' | sort)
Focused test run: 14 selected test files (root lock skipped)
14 test files, 1327 assertions, 0 failures
```

Broader extractor/converter/layout check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderTypedJsonEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1244 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-typed-json-envelope-currentbase.php
typed_json_artifacts_selected=true
stale_typed_json_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat top-level raw JSON artifact envelope decoding, source-keyed artifact maps, wrapper geometry envelopes, typed direct array envelopes, selected page-range trimming, page-id/camelCase/decimal marker aliases, ambiguous wrapper-list rejection, row-level stale marker filtering, normalized bbox handling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically raw JSON strings nested under typed `layout_result` and `order_result` payload-envelope keys.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied artifact selector, layout annotator, layout orderer, pdftext dictionary converter, selected page-range handling, and WordPress smoke harness. Live `pdftext`, PDFium/pypdfium rendering, Surya layout/order/OCR/table models, Texify, Torch/CUDA/model execution, Streamlit/FastAPI workers, raster image decoding, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
