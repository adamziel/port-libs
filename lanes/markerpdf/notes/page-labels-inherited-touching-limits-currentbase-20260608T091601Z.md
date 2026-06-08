# MarkerPDF PageLabels inherited touching limits current-base

- Slice: `markerpdf-page-labels-boundary-current-base-20260608T091601Z`
- Base accepted HEAD: `d9949f7212f1baa1739072f7847d9100f9fa82cb`
- Scope: native no-GPU catalog `/PageLabels` number-tree parsing for searchable PDFs and WordPress page-break metadata.

## Source Truth

PDF page labels are a catalog number tree keyed by zero-based physical page indexes. Child `/Limits` constrain the key range of each kid node, while inherited parent `/Limits` can further clip the range seen by the current traversal. A stale sibling that touches an earlier child at the shared endpoint must not relabel that endpoint, but parent clipping alone must not convert valid touching child ranges into a same-lower duplicate and suppress later non-overlapping page-label entries.

Upstream markerPDF gets searchable PDF text page-by-page before OCR/model stages. This slice keeps PageLabels metadata aligned with those page-local text rows and stays inside the current native no-GPU scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium runtime execution, Poppler, Ghostscript, Python models, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now records claimed child ranges as both inherited/clipped ranges and original child-local `/Limits`.
- `MarkerAppPreview::pageLabelSections()` mirrors that overlap guard so fallback preview and `getPageImagePlan()` labels stay aligned with extracted text labels.
- Same-lower duplicate rejection now compares original child-local lower bounds. Endpoint overlap suppression still uses inherited/clipped ranges, so stale endpoint labels remain blocked.
- Added a focused four-page fixture where parent `/Limits [2 3]` clips child ranges `[0 2]` and `[2 3]`. The first child contributes `App-Z` at page index 2, the second child must still contribute `Back-7` at page index 3 while its stale page-index-2 label is excluded.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsInheritedTouchingKidLimitsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves non-overlapping PageLabels child entries after inherited touching limit clipping
Expected: ["1","2","App-Z","Back-7"]
Actual:   ["1","2","App-Z","App-AA"]
1 test files, 1 assertions, 1 failures
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsInheritedTouchingKidLimitsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves non-overlapping PageLabels child entries after inherited touching limit clipping
1 test files, 12 assertions, 0 failures
```

Adjacent overlap guard checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsTouchingKidLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsSameLowerExtensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsMalformedSameLowerKidBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 32 assertions, 0 failures
```

Full PageLabels current-base family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfPageLabels.*CurrentBaseTest\.php$|PdfPageLabelsBoundaryCurrentBaseTest\.php$' | sort)
Focused test run: 38 selected test files (root lock skipped)
38 test files, 716 assertions, 0 failures
```

## WordPress Smoke

Added `examples/wordpress-pdf-page-labels-inherited-touching-limits-currentbase.php`.

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-inherited-touching-limits-currentbase.php
```

The smoke emits `markerpdf-page-labels-inherited-touching-limits-smoke` with `page_labels=["1","2","App-Z","Back-7"]`, `preview_page_labels=["1","2","App-Z","Back-7"]`, `selected_preview_page_label="Back-7"`, `stale_endpoint_label_rejected=true`, `inherited_clipped_same_lower_preserved=true`, `preview_alignment_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP PASS files: `+1` (`3014 -> 3015`)
- Focused assertions for the new test: `12`
- WordPress-relevant scenarios: `+1` (`2494 -> 2495`)
- Mapped upstream denominator: unchanged; this is an additive boundary inside the already mapped native PageLabels parser cluster.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, same-lower extension rejection, malformed same-lower contribution guards, endpoint-touching stale-label suppression, child-local stale `/Nums` pruning, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Kids` keys, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, page transition/action review, annotations, forms, security, image/filter, font/CMap, or supplied table/equation behavior. The bounded behavior is only inherited parent `/Limits` clipping that creates an apparent same-lower child range after two source child ranges merely touch at an endpoint.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/OCR/PDFium runtime parity remains intentionally gated by the current no-GPU/no-live-model scope.
