# markerPDF PageLabels same-lower extension boundary

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before model execution; native PHP `/PageLabels` remains page-break and preview metadata under the current no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page index. Sibling kid `/Limits` ranges bound child number-tree ranges, and a later malformed sibling with the same lower bound must not extend an already claimed source-order range to stale-relabel later pages.
- This slice extends the accepted same-lower guard from pages inside the earlier child range to the full later same-lower sibling extension. Endpoint-touching ranges with a different lower bound still preserve later non-overlapping pages.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now treats a later kid with the same lower `/Limits` bound as stale once an earlier contributing sibling claimed that lower bound, even when the later kid's upper bound extends farther.
- `MarkerAppPreview::pageLabelSections()` applies the same merge rule so `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` stay aligned with native text extraction.
- Added a four-page fixture where `[0,0]` contributes `Front 1`, a later `[0,2]` sibling attempts to inject `stale-same-extend-77` and `stale-same-extend-88`, and a disjoint `[3,3]` sibling still contributes `End-`.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsSameLowerExtensionBoundaryCurrentBaseTest.php
FAIL rejects PageLabels same-lower kid range extension before stale relabeling
Expected: ["Front 1","Front 2","Front 3","End-"]
Actual: ["Front 1","stale-same-extend-77","stale-same-extend-88","End-"]
1 test files, 1 assertions, 1 failures
```

Focused boundary family after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsSameLowerExtensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsMalformedSameLowerKidBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsTouchingKidLimitsBoundaryCurrentBaseTest.php
3 test files, 32 assertions, 0 failures
```

Broader PageLabels current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
36 test files, 792 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-same-lower-extension-currentbase.php
```

The smoke reports `same_lower_extension_rejected=true`, `earlier_range_continuation_preserved=true`, `later_disjoint_kid_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2929 -> 2930`
- Focused PageLabels assertions: new test adds `8` assertions
- WordPress scenarios: `2440 -> 2441`

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order guards inside an earlier declared range, malformed same-lower noncontributing first kids, endpoint-touching kid `/Limits`, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Kids` keys, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only later same-lower child `/Limits` ranges that try to extend beyond an already claimed source-order lower bound.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
