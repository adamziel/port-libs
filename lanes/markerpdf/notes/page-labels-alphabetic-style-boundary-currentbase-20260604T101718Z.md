# markerPDF PageLabels alphabetic style boundary

## Source Truth

- Upstream markerPDF obtains page text through per-page pdftext/PDFium iteration; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to page-local text extraction.
- PDF PageLabels alphabetic styles use repeated-letter numbering: `A` through `Z`, then `AA` through `ZZ`, and so on. The native text extractor already used that PDF page-label sequence.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium, PDFium, Poppler, Ghostscript, Python, or external PDF tooling was run.

## Implementation

- `MarkerAppPreview::alphabeticLabel()` now matches the native text extractor and PDF PageLabels behavior for `/S /A` and `/S /a`.
- Added a focused current-base fixture with `/PageLabels << /Nums [0 << /S /A /P (App-) /St 26 >>] >>`, proving text extraction and preview metadata both produce `App-Z`, `App-AA`, and `App-BB`.
- Added a WordPress smoke that emits page-break metadata for the repeated-letter labels and rejects the old spreadsheet-style `App-AB` preview label.
- `UPSTREAM_TEST_MANIFEST.json` increments the existing `markerAppPreviewPageLabelsCurrentBase` mapped behavior count from 3 to 4.

## Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
PASS keeps parent PageLabels Limits across indirect kid number-tree boundaries
PASS keeps PDF alphabetic PageLabels repeated-letter style aligned with preview metadata
1 test files, 13 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-alphabetic-boundary-currentbase.php
emitted page_labels=["App-Z","App-AA","App-BB"], selected_preview_page_label="App-BB", spreadsheet_style_label_rejected=true
```

Additional focused verification for this handoff is recorded in the final worker report.

## Non-Overlap

This does not repeat accepted PageLabels basic number-tree parsing, direct/indirect `/Nums`, kid array traversal, local or inherited `/Limits`, indirect operands, viewer preferences, outline page-label propagation, page transition/action review, or runtime preflight work. The bounded new behavior is MarkerAppPreview alphabetic label formatting for PDF repeated-letter PageLabels.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, page-tree traversal, PageLabels number-tree parser, marker-app preview summary, selected page-image plan metadata, and WordPress block smoke path.
