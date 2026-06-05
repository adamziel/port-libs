# markerPDF PageLabels indirect Limits operand boundary

## Source Truth

- Upstream markerPDF obtains page text through PDF page iteration boundaries before model execution; native PHP PageLabels remain page-break/review metadata and must not alter visible text extraction.
- PDF catalog `/PageLabels` is a number tree. `/Limits` arrays bound the integer keys represented by a node and may contain ordinary PDF objects, including indirect numeric operands. Those operands must be resolved before inherited kid `/Nums` filtering.
- This stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium, Poppler, Ghostscript, Python, live models, or external PDF tooling was run.

## Implementation

- `PdfTextExtractor` now resolves direct or indirect numeric operands inside PageLabels `/Limits` arrays with a generation-aware cycle guard before accepting kid `/Nums` entries.
- `MarkerAppPreview` applies the same indirect `/Limits` operand resolution so `openPdfSummary()`, `pageLabels()`, and `getPageImagePlan()` match text extraction.
- Added a current-base fixture where root `/Limits [30 0 R 31 0 R]` resolves to `[1 2]`, excluding stale labels at keys `0` and `3` while preserving current labels `1`, `Body 4`, `App-Z`, and `App-AA`.
- Added a WordPress smoke that emits the bounded PageLabels as Gutenberg page-break metadata while confirming stale labels are excluded.

## Evidence

Red-first after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL resolves indirect PageLabels Limits operands before kid boundary filtering
Expected: ["1","Body 4","App-Z","App-AA"]
Actual: ["stale-front-90","Body 4","App-Z","stale-back-99"]
1 test files, 27 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 33 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-indirect-limits-currentbase.php
page_labels=[1,Body 4,App-Z,App-AA]
stale_front_label_excluded=true
stale_back_label_excluded=true
preview_labels_match_text_extraction=true
```

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree parsing, direct `/Limits`, inherited parent `/Limits`, indirect `/Kids`, indirect `/S`/`/P`/`/St` label dictionary operands, escaped `/PageLabels` names, viewer preferences, outline page-label propagation, page transition/action review, xref repair, or runtime preflight work. The bounded new behavior is resolving indirect numeric operands inside PageLabels `/Limits` arrays before kid number-tree filtering.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page-tree traversal, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path.
