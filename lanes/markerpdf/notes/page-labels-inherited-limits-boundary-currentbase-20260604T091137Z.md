# markerPDF PageLabels inherited Limits boundary

## Source Truth

- Upstream markerPDF obtains page text by iterating PDF pages through pdftext/PDFium page boundaries; native PHP page labels stay review/page-break metadata and must not change visible text extraction.
- PDF catalog `/PageLabels` is a number tree. A node `/Limits` pair bounds the keys represented by that node and its children, so indirect kid `/Nums` entries outside an inherited parent limit are stale for the current page-label map.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium, PDFium, Poppler, Ghostscript, Python, or external PDF tooling was run.

## Implementation

- `PdfTextExtractor` now carries effective PageLabels `/Limits` down into indirect `/Kids` nodes and intersects child limits with inherited parent limits before accepting `/Nums` entries.
- `MarkerAppPreview` applies the same inherited-limit boundary so `openPdfSummary()`, `pageLabels()`, and `getPageImagePlan()` stay aligned with extracted page text.
- Added a focused current-base test fixture where root `/Limits [1 2]` rejects stale child keys `0` and `3`, preserving fallback label `1`, accepted labels `Body 5` and `Chapter 9`, and continuing the valid chapter label as `Chapter 10`.
- Added a WordPress smoke that emits page-break metadata only for the bounded labels while rendering page-local Gutenberg paragraphs.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL keeps parent PageLabels Limits across indirect kid number-tree boundaries
Expected: ["1","Body 5","Chapter 9","Chapter 10"]
Actual: ["stale-front-vi","Body 5","Chapter 9","stale-back-99"]
1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
PASS keeps parent PageLabels Limits across indirect kid number-tree boundaries
1 test files, 7 assertions, 0 failures
```

Additional focused verification for this handoff is recorded in the final worker report.

## Non-Overlap

This does not repeat accepted PageLabels basic number-tree parsing, direct/indirect `/Nums`, kid array traversal, local node `/Limits`, viewer preferences, outline page-label propagation, page transition/action review, xref repair, inline-image tokenizer, or runtime preflight work. The bounded new behavior is inherited parent `/Limits` propagation across indirect PageLabels kid nodes.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, page-tree traversal, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path.
