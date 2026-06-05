# markerPDF PageLabels malformed Limits boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T055019Z`

## Source Truth

- Upstream markerPDF obtains searchable PDF text through page-scoped PDF page iteration before model execution. Native PHP `/PageLabels` stay page-break and preview metadata aligned with page-local text, not visible body text.
- PDF catalog `/PageLabels` is a number tree. `/Limits` must be a two-integer array for the keys represented by a node. Nested dictionaries or private payload numbers inside malformed `/Limits` arrays must not be scanned as bounds.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pageLabelLimits()` now accepts only top-level `/Limits` operands that resolve to two integer entries.
- Removed the legacy fallback that scanned arbitrary digits inside malformed `/Limits` payloads such as `[<< /Low 1 /High 2 >>]`.
- Added a focused fixture where a malformed `/Limits` dictionary previously clipped out the valid first and last PageLabels sections, producing `1` and `App-AA` instead of `Cover-` and `Back-9`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Cover-`, `Body 4`, `App-Z`, and `Back-9` while proving text extraction and preview labels stay aligned.

## Evidence

Red-first focused run after adding the fixture and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL rejects malformed PageLabels Limits dictionary before nested numeric decoys
Expected: ["Cover-","Body 4","App-Z","Back-9"]
Actual: ["1","Body 4","App-Z","App-AA"]
1 test files, 123 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 129 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-malformed-limits-currentbase.php
```

The smoke emitted `page_labels=["Cover-","Body 4","App-Z","Back-9"]`, `preview_page_labels=["Cover-","Body 4","App-Z","Back-9"]`, `selected_preview_page_label="Back-9"`, `malformed_limits_ignored=true`, `nested_numeric_limits_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `1489 -> 1490`
- `wordpressScenarios`: `1396 -> 1397`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct or indirect `/Kids`, inherited/local valid `/Limits`, indirect `/Limits` operands, indirect `/Nums` key or array operands, indirect `/S` `/P` `/St` label operands, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, trailer-root catalog selection, viewer preferences, outline page-label propagation, or page transition/action review. The bounded behavior is rejecting malformed `/Limits` arrays whose nested private dictionary numbers previously acted as false number-tree bounds.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, top-level array tokenizer, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
