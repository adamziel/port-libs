# markerPDF PageLabels duplicate Kids key boundary

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before model execution; native PHP `/PageLabels` stays page-break and preview metadata under the current no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page index. Duplicate dictionary keys appear in malformed real PDFs, so the native boundary follows the existing PageLabels policy used for duplicate `/PageLabels` and duplicate `/Nums`: skip unusable earlier values and keep the first usable duplicate value.
- This slice covers duplicate `/Kids` keys where the first value is a syntactically valid array but produces no usable child node references, while a later `/Kids` value contains the valid PageLabels child number-tree nodes.

## Implementation

- `PdfTextExtractor::pageLabelKidDictionaryNodes()` now evaluates all resolved top-level `/Kids` array values and returns the first array that yields usable child dictionaries instead of stopping at an empty unusable array.
- `MarkerAppPreview::pageLabelSections()` applies the same first-usable duplicate `/Kids` behavior so fallback preview parsing remains aligned with native extraction.
- Added a focused three-page fixture where `/Kids [99 0 R]` is unusable and the second `/Kids [21 0 R 22 0 R]` supplies `Front iv`, `Body 8`, and `App-Z`.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDuplicateKidsKeyBoundaryCurrentBaseTest.php
FAIL keeps first usable duplicate PageLabels Kids key before stale malformed arrays
Actual labels: ["1","2","3"]
1 test files, 1 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDuplicateKidsKeyBoundaryCurrentBaseTest.php
PASS keeps first usable duplicate PageLabels Kids key before stale malformed arrays
1 test files, 14 assertions, 0 failures
```

Adjacent PageLabels/preview family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
21 test files, 591 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-kids-key-currentbase.php
```

The smoke reports `first_usable_duplicate_kids_key_preserved=true`, `unusable_first_kids_array_skipped=true`, `stale_duplicate_kids_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsDuplicateKidsKeyBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-kids-key-currentbase.php
git diff --check -- lanes/markerpdf
```

All returned clean.

## Delta

- Focused PHP PASS cases: `2535 -> 2536`
- Focused PageLabels assertions: new test adds `14` assertions
- WordPress scenarios: `2153 -> 2154`

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, disjoint/overlapping kid sorting, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only duplicate `/Kids` keys whose earlier syntactically valid array contributes no usable child nodes.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
