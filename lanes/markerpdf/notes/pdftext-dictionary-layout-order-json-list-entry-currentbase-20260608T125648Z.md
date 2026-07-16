# markerPDF pdftext dictionary layout/order JSON list-entry boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T125648Z`

Base accepted HEAD: `d34cf5bfb31bb5ffe4f24d7cf74e71269251dd8f`

## Source Truth

- Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains searchable PDF pages through `pdftext.extraction.dictionary_output(...)`, trims pages to the selected range, then zips supplied layout/order predictions with selected page dictionaries before block annotation and reading-order sorting.
- The no-GPU native PHP lane owns the supplied-boundary handoff for cached adapter artifacts. JSONL-style caches can store each layout/order/image artifact list entry as a raw JSON object string, so artifact-shaped strings must decode before selected-page matching. Generic pdftext page text caches and arbitrary string payloads must remain inert.

## Implementation

- `PdfPageArtifactSelector::normalizeSuppliedArtifacts()` now decodes raw JSON list entries only when the decoded value has supplied artifact payload shape.
- Decoded list entries can unwrap direct source-page keyed maps or explicit `pages` / `dictionary_output` / `pdftext` envelopes before page-marker matching.
- The artifact-shape guard intentionally does not treat `blocks` or page-level `bbox` alone as enough to trust a raw JSON string, so pdftext page-copy JSON does not become an empty assigned layout/order artifact.
- Existing `LayoutAnnotator` and `LayoutOrderer` sanitization still strips adapter `raw_payload` values before WordPress text/metadata output.

## Evidence

Red-first after adding the focused case and before the selector patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonListEntryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes raw JSON list-entry order artifacts before selected pdftext layout assignment
Supplied ordering predictions must be arrays.
FAIL decodes raw JSON list-entry layout and order artifacts before WordPress pdftext imports
Supplied layout predictions must be arrays.

1 test files, 0 assertions, 2 failures
```

Focused after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonListEntryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes raw JSON list-entry order artifacts before selected pdftext layout assignment
PASS decodes raw JSON list-entry layout and order artifacts before WordPress pdftext imports

1 test files, 31 assertions, 0 failures
```

Adjacent layout-order family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonArtifactEnvelopeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderDirectKeyMarkerConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderAmbiguousEnvelopeBoundaryCurrentBaseTest.php
=> 5 test files / 1025 assertions / 0 failures
```

Syntax and status checks:

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
=> No syntax errors detected

php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonListEntryBoundaryCurrentBaseTest.php
=> No syntax errors detected

php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-json-list-entry-currentbase.php
=> No syntax errors detected

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "JSON OK\n";'
=> JSON OK

git diff --check -- lanes/markerpdf
=> no output
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-json-list-entry-currentbase.php
```

The smoke emits `layout_artifacts_decoded=true`, `order_artifacts_decoded=true`, `selected_page_ordered_by_decoded_json_list_entry=true`, `cover_page_excluded=true`, `payloads_excluded=true`, `supplied_boundaries=["layout","order"]`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

## Non-Overlap

This does not repeat accepted pdftext dictionary core normalization, text `dictionary_output` envelopes, cached artifact envelopes under explicit `pages` / `dictionary_output` / `pdftext` boundaries, source-page keyed maps, trusted metadata fallback, direct-key marker conflict handling, ambiguous unmarked envelope rejection, scalar sidecars, wrapper geometry, non-finite geometry rejection, duplicate artifact rejection, or named-destination stream-carrier value rejection. The bounded behavior is only artifact-shaped raw JSON strings that appear as individual supplied layout/order/image list entries before selected-page matching.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied artifact selector, pdftext dictionary page adapter, layout annotator, layout orderer, supplied document converter, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside this markerPDF no-GPU slice.

Root harness not run - isolated micro-slice.
