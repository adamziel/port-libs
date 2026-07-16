# markerPDF pdftext dictionary layout/order decimal-key boundary current base

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T071933Z`
Base: `0beefbb15b02a8a82f64dd1fad4516dc169139da`

## Source truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable PDF page dictionaries from `pdftext.extraction.dictionary_output(..., page_range=...)` before Marker conversion.
- Upstream layout/order handling zips one supplied prediction with each selected Marker page after page-range trimming. Native no-GPU adapters that cache these handoff artifacts as source-page keyed object maps must align by source page before assignment.
- This remains in the no-GPU markerPDF scope: it does not execute OCR, Surya/Texify/Torch, pypdfium/PDFium, PDF actions, raster renderers, or external PDF tools.

## Implementation

- `PdfTextDocumentExtractor` now treats source-page object keys like `+9601.0` as integer-valued keys when ordering cached `dictionary_output` page maps.
- `PdfPageArtifactSelector` uses the same key boundary for supplied `pages`, `dictionary_output`, and `pdftext` layout/order artifact maps.
- The numeric key is selector-only metadata and is not copied into page/order/layout output. Raw adapter payloads still stay out of WordPress paragraphs and metadata.

## Red-first evidence

Before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDecimalKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL orders decimal-string keyed pdftext and order maps before selected layout assignment
Expected: 9601
Actual: 9600
FAIL uses decimal-string keyed layout and order maps before WordPress supplied imports
String does not contain '# First Converter Decimal-Key Heading.'
1 test files, 11 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDecimalKeyBoundaryCurrentBaseTest.php
PASS orders decimal-string keyed pdftext and order maps before selected layout assignment
PASS uses decimal-string keyed layout and order maps before WordPress supplied imports
1 test files, 35 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f | sort | rg 'PdfTextDictionaryLayoutOrder.*Test\.php$')
11 test files, 1241 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-decimal-key-currentbase.php
```

The smoke exits 0 and emits `decimal_keyed_dictionary_output_ordered=true`, `layout_decimal_key_map_selected=true`, `order_decimal_key_map_selected=true`, `heading_before_body=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDecimalKeyBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-decimal-key-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the existing native PHP pdftext dictionary adapter, supplied artifact selector, layout annotator, layout orderer, and supplied document converter. Live OCR, model execution, pypdfium/PDFium rendering, and external PDF tools remain intentionally out of scope.

## Non-overlap

This does not repeat accepted integer source-key maps, signed/decimal page-marker values, selected page-range slicing, direct single keyed payload envelopes, page/page_idx/pdftext_source aliases, wrapper-list rejection, duplicate artifact rejection, non-finite/zero-area bbox guards, row-level page-marker filtering, CMap/font/xref/image/filter/parser behavior, annotations/forms/security, table/equation supplied boundaries, OCR, or model parity. The bounded behavior is only integer-valued decimal source-page object keys in pdftext dictionary and layout/order artifact maps.
