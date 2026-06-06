# markerPDF PageLabels reversed kid Limits boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260606T002008Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text by physical page before OCR/layout/model work; native PHP `/PageLabels` stays page-break and preview metadata aligned to those physical pages, not visible body text.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based page index. Child nodes advertise their key range with `/Limits [lower upper]`; a reversed child range has no trustworthy subtree bounds and must not relabel WordPress page-break metadata.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now skips PageLabels kid nodes whose local `/Limits` resolve to exactly two integer operands in reversed order.
- `MarkerAppPreview` applies the same fallback parser guard so preview/page-image metadata cannot diverge from native text extraction.
- Added a focused fixture where child `21 0 R` declares `/Limits [2 1]` and stale page-0/page-1 labels before a valid sibling `22 0 R`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Front iv`, `Body 8`, and `App-Z` while proving stale reversed child labels are excluded.

## Evidence

Red-first focused run after adding the test and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsReversedKidLimitsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects reversed PageLabels kid Limits before stale child labels
Expected: ["Front iv","Body 8","App-Z"]
Actual: ["stale-reversed-99","stale-reversed-body-100","App-Z"]
1 test files, 1 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsReversedKidLimitsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects reversed PageLabels kid Limits before stale child labels
1 test files, 14 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 13 selected test files (root lock skipped)
13 test files, 495 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-reversed-kid-limits-currentbase.php
```

The smoke emits `page_labels=["Front iv","Body 8","App-Z"]`, `preview_page_labels=["Front iv","Body 8","App-Z"]`, `summary_page_labels=["Front iv","Body 8","App-Z"]`, `selected_preview_page_label="App-Z"`, `reversed_kid_limits_rejected=true`, `stale_reversed_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PASS cases: `+1`
- Focused new assertions: `14`
- `phpPass`: `2294 -> 2295`
- `wordpressScenarios`: `1971 -> 1972`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct `/Nums`, indirect `/Kids`, direct kid dictionaries, inherited/local valid `/Limits`, malformed nested-dictionary `/Limits`, malformed `/Limits` extra operands, indirect `/Limits`, no-`/Limits` kid source order, same-lower kid source order, malformed same-lower contribution guards, duplicate `/Nums` keys, descending/out-of-range `/Nums` key ordering, mixed `/Nums` plus `/Kids`, indirect `/S` `/P` `/St` operands, scalar comments, malformed prefix/style scalar tails, malformed dictionary/array object tails, null value resets, escaped names, PDFDocEncoding prefixes, UTF-16 prefix decoding, alphabetic repeated-letter formatting, generation-exact dictionaries/arrays/keys, object-stream PageLabels, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only fail-closed exclusion of PageLabels child nodes whose own `/Limits` resolve to a reversed integer range.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level array/dictionary tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
