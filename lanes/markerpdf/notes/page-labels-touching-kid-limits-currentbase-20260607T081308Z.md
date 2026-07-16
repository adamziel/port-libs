# markerPDF PageLabels touching kid Limits boundary

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before model execution; native PHP `/PageLabels` stays page-break and preview metadata under the current no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page index. Kid `/Limits` bound child number-tree ranges, and malformed sibling ranges must not stale-relabel pages covered by an earlier contributing child range.
- This slice extends the accepted overlapping-kid guard to the edge where a later child starts at an earlier child range upper endpoint and continues beyond it. The shared endpoint remains protected, while the later non-overlapping page still contributes.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now treats endpoint-crossing sibling kid ranges as overlapping for the shared page before adding later label sections.
- `MarkerAppPreview::pageLabelSections()` applies the same boundary so fallback preview inventory stays aligned with import text labels.
- Added a focused four-page fixture where `[0,2]` contributes `Front iv`, `Body 8`, and the continuing `Body 9`; `[2,3]` tries to stale-relabel page 3 as `stale-touch-77` while still contributing `End-` on page 4.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsTouchingKidLimitsBoundaryCurrentBaseTest.php
FAIL rejects PageLabels kid Limits touching an earlier upper endpoint before stale relabeling
Expected: ["Front iv","Body 8","Body 9","End-"]
Actual: ["Front iv","Body 8","stale-touch-77","End-"]
1 test files, 1 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsTouchingKidLimitsBoundaryCurrentBaseTest.php
1 test files, 8 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
25 test files, 642 assertions, 0 failures
```

Broader text-extractor check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPageLabelsTouchingKidLimitsBoundaryCurrentBaseTest.php
2 test files, 637 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-touching-kid-limits-currentbase.php
```

The smoke reports `touching_endpoint_rejected=true`, `earlier_endpoint_continuation_preserved=true`, `later_non_overlapping_kid_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2811 -> 2812`
- Focused PageLabels assertions: new test adds `8` assertions
- WordPress scenarios: `2361 -> 2362`

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, singleton endpoint kid behavior, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Kids` keys, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only endpoint-crossing child `/Limits` ranges whose lower bound equals an earlier claimed upper bound and whose upper bound extends beyond it.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
