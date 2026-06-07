# markerPDF PageLabels noncontributing no-Limits kid sort boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260607T125750Z`

## Source truth

- Upstream markerPDF extracts searchable text by physical PDF page before model execution; native PHP `/PageLabels` remains page-break and preview metadata aligned to those physical pages under the no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page indexes. Bounded child nodes use `/Limits` to order and constrain label ranges; malformed child nodes that contribute no usable `/Nums` entries must not reserve a range or let stale siblings relabel pages.
- This slice preserves the already accepted behavior where contributing no-`/Limits` kids keep source-order precedence. The new boundary is only an earlier no-`/Limits` kid whose `/Nums` values are malformed and therefore contribute no label sections.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now resolves child PageLabels sections before deciding sortability, drops empty no-`/Limits` children from the limited-sibling sort decision, and reuses those resolved sections during merge.
- `MarkerAppPreview::pageLabelSections()` applies the same traversal rule for preview fallback alignment.
- Added a focused fixture where a malformed no-`/Limits` child appears before a stale `[1,1]` bounded child and a valid `[0,2]` bounded child. The valid bounded child must sort first and label page 1 as `Body 8`, not `stale-unsorted-77`.
- Added a WordPress smoke that emits page-break metadata for `Front iv`, `Body 8`, and `App-Z` while proving stale and malformed labels stay excluded.

## Red/green evidence

Red-first focused run before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNoncontributingNoLimitsKidSortBoundaryCurrentBaseTest.php
FAIL sorts bounded PageLabels kids after noncontributing no-Limits siblings
Expected: ["Front iv", "Body 8", "App-Z"]
Actual: ["Front iv", "stale-unsorted-77", "App-Z"]
1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNoncontributingNoLimitsKidSortBoundaryCurrentBaseTest.php
PASS sorts bounded PageLabels kids after noncontributing no-Limits siblings
1 test files, 14 assertions, 0 failures
```

Related PageLabels regression family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php
26 test files, 562 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-noncontributing-no-limits-kid-currentbase.php
```

The smoke exits 0 and emits `bounded_kid_sort_preserved=true`, `stale_unsorted_label_excluded=true`, `malformed_no_limits_child_unclaimed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PageLabels assertions: new test adds 14 assertions.
- `phpPass`: `2868 -> 2869`
- `wordpressScenarios`: `2401 -> 2402`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`

## Non-overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-`/Limits` child source order when that child contributes labels, same-lower source-order preservation, malformed same-lower contribution guards, disjoint/overlapping/touching bounded kid range guards, duplicate `/Nums`, duplicate `/Kids`, duplicate `/Limits`, duplicate catalog `/PageLabels`, null resets, descending/out-of-range `/Nums`, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root`, encrypted preview fallback, viewer preferences, outline page-label propagation, page transition/action review, annotations, forms, security, image/filter, font/CMap, xref repair, or supplied table/equation behavior. The bounded behavior is only empty no-`/Limits` child nodes not disabling bounded sibling sorting.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/OCR/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
