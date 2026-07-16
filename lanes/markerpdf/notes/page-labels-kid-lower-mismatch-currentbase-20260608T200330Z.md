# markerPDF PageLabels kid lower-limit mismatch boundary

## Source truth

- Upstream markerPDF extracts searchable PDF text by physical page before model execution; native PHP `/PageLabels` remains page-break and preview metadata under the current no-GPU scope. Source: `sddai/markerPDF` `marker/pdf/extract_text.py` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based page index. A kid node can provide `/Limits`, but a malformed child whose first usable key is above the declared lower limit should not reserve lower pages before a later precise sibling contributes those labels.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now records the actual page indexes a kid contributes. A bounded kid claim starts at the first contributed key and extends through the effective upper limit instead of blindly reserving the declared lower `/Limits` value.
- `MarkerAppPreview::pageLabelSections()` mirrors the same claim range so preview summaries, `pageLabels()`, and `getPageImagePlan()` stay aligned with text extraction.
- Added a focused fixture where the first kid declares `/Limits [0 2]` but only contributes key `2`, while a later same-lower precise kid contributes keys `0` and `1`. WordPress page metadata now preserves `Cover-`, `Body 4`, and `App-Z`.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsKidLowerMismatchBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps later PageLabels kid when earlier same-lower child starts after its lower Limit
Values are not identical
Expected: array (
  0 => 'Cover-',
  1 => 'Body 4',
  2 => 'App-Z',
)
Actual: array (
  0 => '1',
  1 => '2',
  2 => 'App-Z',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsKidLowerMismatchBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps later PageLabels kid when earlier same-lower child starts after its lower Limit

1 test files, 16 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsKidLowerMismatchBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsSameLowerExtensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsMalformedSameLowerKidBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsOverlappingKidLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsTouchingKidLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsInheritedTouchingKidLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsDisjointKidLimitsSortBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 82 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfPageLabels*CurrentBaseTest.php' | sort) lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 51 selected test files (root lock skipped)
51 test files, 1003 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsKidLowerMismatchBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-kid-lower-mismatch-currentbase.php
No syntax errors detected
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-kid-lower-mismatch-currentbase.php > /tmp/markerpdf-page-labels-kid-lower-mismatch-smoke.html
smoke markers ok
```

```text
git diff --check -- lanes/markerpdf
no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `+1`
- Focused assertions: `+16`
- WordPress scenarios: `+1`
- `lane-status.json`: `phpPass` `3458 -> 3459`, `wordpressScenarios` `2807 -> 2808`
- Mapped upstream denominator: unchanged; this is additive inside the already mapped PageLabels catalog number-tree behavior cluster.

## Non-Overlap

This does not repeat accepted PageLabels direct extraction, indirect `/Kids`, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kids, noncontributing kids, same-lower extension rejection, malformed same-lower non-claiming kids, overlapping/touching child limits, duplicate `/Nums`/`/Kids`/`/Limits`/`/Type` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding/UTF-16/UTF-8 prefixes, selected trailer-root fallback, viewer-preference composition, or outline page-label propagation. The bounded behavior is only a kid that declares a lower `/Limits` value it does not actually contribute, so it should not suppress a later precise lower-page sibling.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
