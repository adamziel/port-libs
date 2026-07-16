# markerPDF PageLabels malformed same-lower kid boundary

## Source Truth

- Upstream markerPDF extracts page text by physical PDF page before model execution; native PHP `/PageLabels` stays review/page-break metadata aligned to those pages under the current no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based page index. Kid nodes are bounded by `/Limits`, and malformed nodes with no usable `/Nums` entries should not relabel pages or suppress later usable page-label sections.
- This slice extends the accepted same-lower `/Limits` guard: a same-lower sibling range is only reserved after that child contributes at least one valid label section.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now tracks whether a same-lower kid actually contributed a merged label before recording its claimed range.
- `MarkerAppPreview::pageLabelSections()` applies the same contribution guard so native extraction and preview inventory remain aligned when fallback parsing is used.
- Added a focused fixture where the first `[0,1]` kid has a malformed indirect `/Nums` array object and the next `[0,1]` kid contains the valid labels.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedSameLowerKidBoundaryCurrentBaseTest.php
FAIL keeps valid PageLabels kid after malformed same-lower sibling has no usable entries
Expected: ["Front iii","Body 8","App-Z"]
Actual: ["1","2","App-Z"]
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedSameLowerKidBoundaryCurrentBaseTest.php
1 test files, 14 assertions, 0 failures
```

Focused PageLabels regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
7 test files, 422 assertions, 0 failures
```

Broader non-slice check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 626 assertions, 2 failures
```

The two failures are unrelated ToUnicode/usecmap cases already outside this PageLabels boundary. The PageLabels tests inside that file passed, and this slice does not alter CMap/usecmap code.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-malformed-same-lower-kid-currentbase.php
```

The smoke reports `malformed_same_lower_kid_unclaimed=true`, `stale_malformed_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2198 -> 2199`
- Focused PageLabels assertions: new test adds `14` assertions
- WordPress scenarios: `1894 -> 1895`
- `mappedPdfPageLabelsMalformedSameLowerKidCurrentBaseBehaviors`: `0 -> 1`

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, inherited/local/indirect/malformed `/Limits`, same-lower source-order preservation for valid siblings, duplicate `/Nums` keys, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only malformed same-lower kid nodes not reserving their `/Limits` range before later valid same-lower siblings.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
