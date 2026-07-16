# markerPDF PageLabels malformed kid Limits operand boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260606T100647Z`

## Source Truth

- Upstream markerPDF gets searchable page text and page-local metadata through PDF page iteration before OCR/model conversion; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical pages, not visible body text.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based page index. Child node `/Limits` operands must resolve to a trustworthy two-integer key range before that child can claim or suppress sibling page-label ranges.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now rejects child PageLabels number-tree nodes whose explicit `/Limits` array resolves to malformed scalar operands, such as `30 0 R` pointing to `0 /Private`.
- `MarkerAppPreview` mirrors that boundary in fallback parsing and resolves indirect kid relay dictionaries before validating local `/Limits`, keeping `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` aligned.
- Added a focused fixture where the first child has `/Limits [30 0 R 31 0 R]` with a malformed lower scalar and stale labels. A later valid child must still supply `Cover-`, `Body 4`, and `App-Z`.
- Added a WordPress smoke that emits Gutenberg page-break metadata for the valid labels while proving the stale malformed child labels stay excluded.

## Evidence

Red-first focused run before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedKidLimitsOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed PageLabels kid Limits scalar operands before stale range claims
Expected: ["Cover-","Body 4","App-Z"]
Actual: ["Stale-77","StaleBody-88","App-Z"]
1 test files, 1 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedKidLimitsOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed PageLabels kid Limits scalar operands before stale range claims
1 test files, 12 assertions, 0 failures
```

Adjacent PageLabels/preview family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 20 selected test files (root lock skipped)
20 test files, 577 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-malformed-kid-limits-operand-currentbase.php
```

The smoke emits `page_labels=["Cover-","Body 4","App-Z"]`, `preview_page_labels=["Cover-","Body 4","App-Z"]`, `selected_preview_page_label="App-Z"`, `malformed_kid_limits_rejected=true`, `later_valid_kid_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PASS cases: `+1`.
- New focused test assertions: `12`.
- `phpPass`: `2504 -> 2505`.
- `wordpressScenarios`: `2128 -> 2129`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct or indirect `/Kids`, direct kid dictionaries, kid reference relays with valid limits, inherited/local/indirect valid `/Limits`, malformed root `/Limits`, extra `/Limits` array operands, reversed/negative child `/Limits`, no-limits kid source order, same-lower and overlapping child range guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, null resets, descending or out-of-range `/Nums` keys, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, page transition/action review, annotations, forms, security, image/filter, font/CMap, or supplied table/equation behavior. The bounded behavior is only child PageLabels number-tree nodes whose explicit `/Limits` array has malformed resolved scalar operands.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
