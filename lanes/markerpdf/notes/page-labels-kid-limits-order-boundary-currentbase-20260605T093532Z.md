# markerPDF PageLabels kid Limits order boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T093532Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through pdftext/PDFium before model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical pages, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF `/PageLabels` is a catalog number tree. Number-tree kid nodes are bounded by `/Limits`, and page-label keys are page indexes; malformed PDFs can list kid references out of key order.
- This slice stays in the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now sorts catalog PageLabels kid nodes by effective inherited/local `/Limits` before merging child entries and applying duplicate page-index protection.
- `MarkerAppPreview` applies the same fallback ordering, so `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` stay aligned when native text labels are unavailable or mismatched.
- Added a focused fixture where root `/Kids [22 0 R 21 0 R 23 0 R]` lists the `[1 2]` child before the `[0 1]` child. Before the fix, the stale page-index `1` entry from child `22` won; after the fix, child `21` supplies `Body 6`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Front iv`, `Body 6`, `App-Z`, and `End-` while proving `stale-late-99` is excluded.

## Evidence

Red-first focused run before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL orders PageLabels kid nodes by Limits before duplicate merge boundaries
Expected: ["Front iv","Body 6","App-Z","End-"]
Actual:   ["Front iv","stale-late-99","App-Z","End-"]
1 test files, 169 assertions, 1 failures
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 176 assertions, 0 failures
```

Adjacent PageLabels/preview run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
3 test files, 300 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-kid-limits-order-currentbase.php
```

The smoke emits `page_labels=["Front iv","Body 6","App-Z","End-"]`, `preview_page_labels=["Front iv","Body 6","App-Z","End-"]`, `selected_preview_page_label="End-"`, `stale_out_of_order_kid_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `168 -> 176`.
- Focused PASS cases: `+1`.
- `phpPass`: `1678 -> 1679`.
- `wordpressScenarios`: `1541 -> 1542`.
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, inherited/local valid `/Limits`, malformed `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, trailer `/Root` catalog selection, duplicate `/Nums` keys inside one leaf, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only out-of-order PageLabels kid-node merge order by effective `/Limits` before duplicate page-index protection.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
