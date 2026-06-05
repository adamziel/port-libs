# markerPDF PageLabels catalog duplicate boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T184152Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through `pdftext`/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel coverage models `/PageLabels` as a catalog number tree whose `/Nums` arrays pair integer page-index keys with page-label dictionaries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- This malformed-boundary slice keeps duplicate top-level catalog `/PageLabels` values bounded: malformed non-number-tree operands are skipped, the first usable number tree wins, and later stale duplicate number trees cannot relabel WordPress page-break or preview metadata.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now scans all top-level `/PageLabels` values on the selected catalog object, resolves each candidate through the existing generation-aware object lookup, and uses the first candidate that yields number-tree entries.
- `MarkerAppPreview` mirrors that fallback behavior through a new top-level `valuesAfterName()` helper while preserving existing first-value lookup behavior for other callers.
- Added a focused fixture where the catalog contains `/PageLabels (not-a-number-tree)`, then a valid `/PageLabels 20 0 R`, then private and later stale duplicate label trees. Native extraction and preview must emit `Cover-`, `Body 4`, and `App-Z`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Cover-`, `Body 4`, and `App-Z` while proving malformed/private/stale duplicate catalog label values stay excluded.

## Evidence

Red-first focused run before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsCatalogDuplicateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps first usable catalog PageLabels value before stale duplicate keys
Expected: ["Cover-","Body 4","App-Z"]
Actual: ["1","2","3"]
1 test files, 1 assertions, 1 failures
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsCatalogDuplicateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps first usable catalog PageLabels value before stale duplicate keys
1 test files, 9 assertions, 0 failures
```

Adjacent PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsCatalogDuplicateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsOutOfRangeOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 289 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-catalog-duplicate-currentbase.php
```

The smoke emits `page_labels=["Cover-","Body 4","App-Z"]`, `preview_page_labels=["Cover-","Body 4","App-Z"]`, `selected_preview_page_label="App-Z"`, `malformed_catalog_page_labels_skipped=true`, `stale_duplicate_catalog_page_labels_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `1 -> 9` for the new test, with adjacent PageLabels family passing at `289` assertions.
- Focused PASS cases: `+1`.
- `phpPass`: `2149 -> 2150`.
- `wordpressScenarios`: `1852 -> 1853`.
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, malformed `/Limits` extra operands, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, malformed prefix/style scalar tails, malformed dictionary or array object tails, bare-name prefix rejection, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries inside the PageLabels number tree, comment-delimited indirect references, duplicate `/Nums` keys, descending `/Nums` keys, out-of-order kid merge by `/Limits`, same-lower sibling limits, mixed `/Nums` plus `/Kids`, malformed value ordering, out-of-range key ordering, trailer `/Root` catalog selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only duplicate top-level catalog `/PageLabels` values where malformed operands precede the first usable number tree and later stale duplicate label trees must remain excluded.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-aware object lookup, top-level dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
