# markerPDF PageLabels non-contributing Kids boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260606T215729Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before model execution; native PHP `/PageLabels` stays page-break and preview metadata aligned to physical page text under the current no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page index. Malformed duplicate dictionary keys appear in real PDFs, so this lane keeps the existing PageLabels policy: skip unusable earlier duplicate values and keep the first usable value.
- This slice covers duplicate `/Kids` keys where the first value resolves to a child number-tree dictionary, but that child contributes no usable `/Nums` label sections. That non-contributing child must not block a later valid duplicate `/Kids` value.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now evaluates duplicate `/Kids` array groups until the first group actually contributes a page-label section.
- `MarkerAppPreview::pageLabelSections()` mirrors the same contribution-based duplicate `/Kids` behavior so preview summaries and page-image plans stay aligned with native text extraction.
- Added a focused fixture where `/Kids [21 0 R]` resolves to a valid child with malformed label values, while the later `/Kids [22 0 R 23 0 R]` supplies `Front iv`, `Body 8`, and `App-Z`.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNonContributingKidsBoundaryCurrentBaseTest.php
FAIL keeps later PageLabels Kids key after earlier child contributes no labels
Expected: ["Front iv","Body 8","App-Z"]
Actual: ["1","2","3"]
1 test files, 1 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNonContributingKidsBoundaryCurrentBaseTest.php
PASS keeps later PageLabels Kids key after earlier child contributes no labels
1 test files, 14 assertions, 0 failures
```

Adjacent PageLabels and preview regression run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
24 test files, 634 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-noncontributing-kids-currentbase.php
```

The smoke reports `later_kids_key_preserved=true`, `noncontributing_child_skipped=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2692 -> 2693`
- Focused PageLabels assertions: new test adds `14` assertions
- Mapped manifest behavior rows: `mappedPdfPageLabelsNonContributingKidsCurrentBaseBehaviors: 0 -> 1`
- WordPress scenarios: `2268 -> 2269`
- Root harness: not run - isolated micro-slice

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, disjoint/overlapping kid sorting, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Kids` keys whose first array has no usable child dictionaries, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only duplicate `/Kids` keys whose earlier child node resolves but contributes zero usable label sections.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
