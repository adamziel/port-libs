# markerPDF PageLabels Limits extra operand boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T134157Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through pdftext/PDFium before model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical pages, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF `/PageLabels` is a catalog number tree; `/Limits` is a two-entry key range. A malformed top-level array such as `[1 2 99]` must not be treated as `[1 2]`, because doing so clips valid label entries outside the false range.
- This slice stays in the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pageLabelLimits()` now accepts only exactly two top-level `/Limits` operands.
- `MarkerAppPreview::pageLabelLimits()` applies the same exact two-operand rule for its fallback catalog parser path.
- Added a focused fixture where `/Limits [1 2 99]` previously clipped the valid page-index `0` and `3` labels, producing `1` and continued `App-AA` instead of `Cover-` and `Back-9`.
- Added a WordPress smoke proving extraction labels, preview labels, summary labels, and selected page-image labels stay aligned without executing Python, models, or external PDF tools.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL rejects malformed PageLabels Limits extra operands before clipping valid labels
Expected: ["Cover-","Body 4","App-Z","Back-9"]
Actual:   ["1","Body 4","App-Z","App-AA"]
1 test files, 217 assertions, 1 failures
```

Focused PageLabels gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 224 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-limits-extra-operand-currentbase.php
```

The smoke emits `page_labels=["Cover-","Body 4","App-Z","Back-9"]`, `preview_page_labels=["Cover-","Body 4","App-Z","Back-9"]`, `selected_preview_page_label="Back-9"`, `malformed_limits_ignored=true`, `stale_clipped_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PageLabels assertions: `216 -> 224`.
- Focused PASS cases: `+1`.
- `phpPass`: `1877 -> 1878`.
- `wordpressScenarios`: `1701 -> 1702`.
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, inherited/local valid `/Limits`, malformed nested-dictionary `/Limits`, indirect `/Limits`, indirect `/S` `/P` `/St` operands, scalar comments, malformed prefix/style scalar tails, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, missing-generation fallback, indirect `/Nums` key/array generation handling, object-stream PageLabels, top-level token boundaries, comment-delimited indirect references, trailer `/Root` catalog selection, duplicate `/Nums` keys inside one leaf, out-of-order kid merge by `/Limits`, same-lower kid ordering, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only malformed `/Limits` arrays with extra top-level operands clipping valid labels.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
