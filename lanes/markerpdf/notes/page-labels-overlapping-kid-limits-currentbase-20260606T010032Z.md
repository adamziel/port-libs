# markerPDF PageLabels overlapping kid limits boundary

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before model execution; native PHP `/PageLabels` stays page-break and preview metadata under the current no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page index. Kid `/Limits` bound child number-tree ranges, and sibling children should not stale-relabel pages covered by an earlier contributing child range.
- This slice extends the accepted same-lower malformed-kid guard to one additional malformed boundary: a later child whose lower `/Limits` value starts strictly inside an earlier contributing kid range.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now records contributed kid ranges and rejects later sibling labels when the later child starts at the same lower bound or strictly inside a previously contributed range.
- `MarkerAppPreview::pageLabelSections()` applies the same native boundary so fallback preview inventory remains aligned with import text labels.
- Added a focused four-page fixture where `[0,2]` contributes the front/body and appendix sections, `[1,1]` tries to stale-relabel page 2, and `[3,3]` remains a valid later page-label child.

## Evidence

Red-first in-memory probe before source edit:

```text
Expected labels: ["Front iv","Front v","App-Z","End-"]
Actual labels:   ["Front iv","stale-overlap-77","App-Z","End-"]
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsOverlappingKidLimitsBoundaryCurrentBaseTest.php
1 test files, 10 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
14 test files, 505 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-overlapping-kid-limits-currentbase.php
```

The smoke reports `overlapping_kid_limits_rejected=true`, `earlier_kid_range_preserved=true`, `later_non_overlapping_kid_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsOverlappingKidLimitsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-overlapping-kid-limits-currentbase.php
git diff --check -- lanes/markerpdf
```

All returned clean.

## Delta

- Focused PHP PASS cases: `2307 -> 2308`
- Focused PageLabels assertions: new test adds `10` assertions
- WordPress scenarios: `1980 -> 1981`

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only later kid `/Limits` ranges whose lower bound starts strictly inside an earlier contributing kid range.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
