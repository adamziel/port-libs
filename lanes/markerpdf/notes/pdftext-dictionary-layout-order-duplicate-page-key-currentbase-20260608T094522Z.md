# markerPDF pdftext dictionary layout/order duplicate page-key current base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T094522Z`
Base: `e8dffc9f0d3aa735a6dd8abc60956f05dbfe08da`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` consumes the page dictionaries returned by `pdftext.extraction.dictionary_output(...)`.
- `marker/convert.py::convert_single_pdf()` trims the PDFium document before rendering layout/order inputs, and layout/order prediction assignment is one selected page to one supplied prediction.
- Native source-page keyed artifact maps therefore cannot safely contain two keys that normalize to the same selected page, such as `09911` and `+9911.0`.

## Implementation

- `PdfPageArtifactSelector` now rejects duplicate normalized source-page keys while unwrapping direct source-keyed artifact maps and pdftext-shaped `pages` / `dictionary_output` / `pdftext` artifact envelopes.
- Unique decimal/string source-keyed layout/order maps still align to selected pdftext pages.
- Duplicate alias maps fail before stale layout/order payloads can be selected for WordPress import.

## Evidence

Red-first before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDuplicatePageKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate normalized source-keyed order artifact maps before selected pdftext assignment (lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDuplicatePageKeyBoundaryCurrentBaseTest.php)
Expected exception InvalidArgumentException was not thrown
FAIL rejects duplicate normalized layout and order artifact maps while unique maps still import (lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDuplicatePageKeyBoundaryCurrentBaseTest.php)
Expected exception InvalidArgumentException was not thrown

1 test files, 2 assertions, 2 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDuplicatePageKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate normalized source-keyed order artifact maps before selected pdftext assignment
PASS rejects duplicate normalized layout and order artifact maps while unique maps still import

1 test files, 13 assertions, 0 failures
```

Adjacent layout/order family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfTextDictionaryLayoutOrder.*CurrentBaseTest\.php' | sort)
Focused test run: 13 selected test files (root lock skipped)
13 test files, 1290 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-duplicate-page-key-currentbase.php
```

The smoke emits `duplicate_layout_artifact_keys_rejected=true`, `duplicate_order_artifact_keys_rejected=true`, `unique_keyed_layout_imported=true`, `unique_keyed_order_imported=true`, `heading_before_body=true`, `cover_excluded=true`, `payload_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the native PHP pdftext dictionary normalization, supplied artifact selector, layout/order assignment, supplied document converter, and WordPress smoke path. No Python, pypdfium, Surya layout/order models, OCR, Texify, Torch/model execution, Streamlit/FastAPI worker, live service, or external PDF tool was run.

## Non-overlap

This does not repeat duplicate pdftext page-map rejection, ordinary duplicate matching artifact tie handling, decimal-key map selection, raw JSON artifact envelopes, scalar sidecars, page-id/camelCase markers, parser/xref repair, fonts/CMaps/widths, image/filter metadata, page geometry, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is duplicate normalized source-page keys inside supplied layout/order artifact maps.

Root harness: not run - isolated micro-slice.
