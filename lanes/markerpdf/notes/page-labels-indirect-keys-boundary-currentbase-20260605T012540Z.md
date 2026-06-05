# markerPDF PageLabels indirect key boundary

## Source Truth

- Upstream markerPDF iterates PDF pages through PDF page boundaries; native PHP PageLabels remain page-break/review metadata and do not alter visible text extraction.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based page indexes. Number-tree keys can be ordinary PDF integer objects, so the native parser must resolve indirect integer key operands without accepting stale generations or nested/private decoys.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium, PDFium, Poppler, Ghostscript, Python, or external PDF tooling was run.

## Implementation

- `PdfTextExtractor` now resolves `/Nums` page-index key operands through generation-exact PageLabels object lookup before applying `/Limits`, page-count bounds, and label dictionaries.
- `MarkerAppPreview` now applies the same indirect-key resolution so `openPdfSummary()`, `pageLabels()`, and `getPageImagePlan()` stay aligned with native text extraction.
- Added a focused fixture where `/Nums` keys `30 0 R`, `31 0 R`, and `32 0 R` resolve to page indexes `1`, `2`, and `3`, while same-object-number generation decoys, nested arrays, and out-of-page keys stay excluded.
- Added a WordPress smoke that emits page-break metadata for `1`, `Front ii`, `Body 8`, and `App-Z` while proving stale nested label text is absent.

## Evidence

Red-first after adding the focused test and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL resolves indirect PageLabels Nums keys by exact generation before WordPress page metadata
Expected: ["1","Front ii","Body 8","App-Z"]
Actual: ["1","2","3","4"]
1 test files, 56 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
PASS resolves indirect PageLabels Nums keys by exact generation before WordPress page metadata
1 test files, 63 assertions, 0 failures
```

Additional focused verification for this handoff is recorded in the final worker report.

## Non-Overlap

This does not repeat accepted PageLabels direct `/Nums`, indirect `/Kids`, inherited/local `/Limits`, indirect `/S` `/P` `/St` operands, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact value dictionaries, viewer preferences, outline page-label propagation, or page transition/action review. The bounded behavior is indirect integer `/Nums` key operands and preview alignment.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, generation-indexed object body table, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path.
