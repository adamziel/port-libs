# markerPDF PageLabels scalar-comment boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T082350Z`

## Source Truth

- Upstream markerPDF gets searchable PDF page text through page-scoped pdftext/PDFium extraction before model execution; native PHP `/PageLabels` remain page-break and preview metadata aligned with page-local text, not visible body text.
- PDF comments are whitespace. That applies after indirect scalar objects used by a catalog `/PageLabels` label dictionary for `/P`, `/S`, and `/St`, so `4 % comment` remains the same start number as `4`.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now parses PageLabels integer scalar operands with trailing PDF comments before resolving references, covering page indexes, `/Limits`, and `/St`.
- `MarkerAppPreview` now tokenizes resolved PageLabels scalar values before decoding `/P`, `/S`, and `/St`, so comment-bounded indirect literal strings, hex strings, names, and integers stay aligned with text extraction.
- Added a focused fixture where indirect PageLabels scalar objects contain trailing comments after a literal prefix, name style, integer start, hex prefix, decimal style, and decimal start.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Front iv` and `Body 7` while proving fallback labels are excluded.

## Evidence

Red-first focused test before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php
FAIL keeps PageLabels indirect scalar comments as whitespace before WordPress page metadata
Expected: ["Front iv","Body 7"]
Actual: ["Front i","Body 1"]
1 test files, 1 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php
PASS keeps PageLabels indirect scalar comments as whitespace before WordPress page metadata
1 test files, 14 assertions, 0 failures
```

Additional verification for this handoff is recorded in the final worker report.

## Non-Overlap

This does not repeat accepted PageLabels direct `/Nums`, indirect `/Kids`, inherited/local `/Limits`, indirect `/S` `/P` `/St` references without scalar comments, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact indirect value dictionaries, object-stream PageLabels, top-level `/Kids` and `/Nums` token boundaries, comment-delimited indirect references, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only trailing PDF comments after resolved indirect PageLabels scalar operands.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, comment-aware whitespace handling, PageLabels formatter, marker-app preview summary, and WordPress smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
