# markerPDF PageLabels duplicate Kids/local Nums boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260608T074249Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before layout/model work; native PHP `/PageLabels` remains page-break and preview metadata aligned to physical pages under the current no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page index. Existing native duplicate-key policy keeps the first usable duplicate value and skips only unusable earlier values.
- This boundary covers a malformed node with local direct `/Nums` and duplicate `/Kids` keys: the first `/Kids` array is structurally usable but only reasserts pages already owned by local `/Nums`, so the later duplicate `/Kids` array must not inject stale labels.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now treats a duplicate `/Kids` group as usable once one of its child sections survives range/claim checks, even if that section is already owned by local `/Nums`.
- `MarkerAppPreview::pageLabelSections()` mirrors the same usable-group guard so preview inventory, `pageLabels()`, and `getPageImagePlan()` stay aligned with native extraction.
- Added a three-page fixture where local `/Nums` labels pages 0 and 1, the first duplicate `/Kids` group repeats those pages, and a later duplicate `/Kids` group tries to label page 2 as `stale-late-kids-99`.

## Evidence

Red-first focused run before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDuplicateKidsLocalNumsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps first usable duplicate PageLabels Kids group when local Nums already own its pages
Expected: ["Cover-","Body 8","Body 9"]
Actual:   ["Cover-","Body 8","stale-late-kids-99"]
1 test files, 1 assertions, 1 failures
```

Focused pass after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDuplicateKidsLocalNumsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps first usable duplicate PageLabels Kids group when local Nums already own its pages
1 test files, 15 assertions, 0 failures
```

Adjacent PageLabels and preview run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 38 selected test files (root lock skipped)
38 test files, 819 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-kids-local-nums-currentbase.php
```

The smoke exits 0 and emits `page_labels=["Cover-","Body 8","Body 9"]`, `preview_page_labels=["Cover-","Body 8","Body 9"]`, `selected_preview_page_label="Body 9"`, `local_nums_preserved=true`, `late_duplicate_kids_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP PASS cases: `2973 -> 2974`.
- Focused assertions: new test adds `15` assertions.
- WordPress scenarios: `2468 -> 2469`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, disjoint/overlapping/touching bounded kid range guards, duplicate `/Kids` keys whose first array has no usable child dictionaries, duplicate `/Nums`, duplicate `/Limits`, duplicate catalog `/PageLabels`, null resets, descending/out-of-range `/Nums`, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, UTF-16 prefix handling, trailer `/Root`, encrypted preview fallback, viewer preferences, outline page-label propagation, page transition/action review, annotations, forms, security, image/filter, font/CMap, xref repair, or supplied table/equation behavior. The bounded behavior is only duplicate `/Kids` groups where the first structurally usable group reasserts page indexes already supplied by local `/Nums`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary/array tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Live OCR, Surya/Texify/Torch, pypdfium/PIL rendering, Python models, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
