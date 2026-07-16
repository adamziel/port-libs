# markerPDF PageLabels duplicate Limits key boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260606T172154Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through pdftext/PDFium before OCR/model work; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page indexes. Node `/Limits` bound child ranges, and malformed duplicate dictionary keys must not let stale ranges replace WordPress page metadata.
- This no-GPU slice stays inside the native parser boundary: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, PIL rendering, Python runner, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now evaluates all top-level `/Limits` candidates on a PageLabels node and uses the first valid range before applying malformed, negative, or reversed fail-closed status.
- `MarkerAppPreview` mirrors the same duplicate `/Limits` candidate handling for fallback page-inventory parsing.
- Added a focused fixture where a root node has malformed duplicate `/Limits`, one child has malformed array arity before a valid range, and another child has a valid range before a stale reversed duplicate.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Cover-`, `Body 4`, and `App-Z` while proving stale duplicate range labels are excluded.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDuplicateLimitsKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps first usable duplicate PageLabels Limits key before stale range rejection
Expected: ["Cover-","Body 4","App-Z"]
Actual: ["1","2","App-Z"]
1 test files, 1 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDuplicateLimitsKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps first usable duplicate PageLabels Limits key before stale range rejection
1 test files, 12 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 22 selected test files (root lock skipped)
22 test files, 510 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-limits-currentbase.php
```

The smoke emits `page_labels=["Cover-","Body 4","App-Z"]`, `preview_page_labels=["Cover-","Body 4","App-Z"]`, `selected_preview_page_label="App-Z"`, `duplicate_limits_recovered=true`, `stale_duplicate_limits_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2621 -> 2622`
- WordPress scenarios: `2220 -> 2221`
- New focused assertions: `12`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed single `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, disjoint or overlapping kid ranges, duplicate `/Nums` keys, duplicate `/Kids` keys, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, direct/indirect null reset values, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only duplicate `/Limits` keys inside PageLabels nodes where the first usable duplicate range must be recovered before stale malformed or reversed duplicate ranges can replace WordPress page labels.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Live OCR, Surya/Texify/Torch models, PDFium/pypdfium raster execution, PIL, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
