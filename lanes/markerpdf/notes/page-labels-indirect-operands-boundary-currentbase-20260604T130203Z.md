# markerPDF PageLabels indirect operands boundary

## Source Truth

- Upstream markerPDF obtains searchable PDF page text by iterating page boundaries through pdftext/PDFium; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to page-local text extraction.
- PDF PageLabels are number-tree entries whose label dictionaries and dictionary values can be indirect PDF objects. Preview metadata must resolve indirect `/S`, `/P`, and `/St` operands the same way native text extraction does.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium, PDFium, Poppler, Ghostscript, Python, or external PDF tooling was run.

## Implementation

- `MarkerAppPreview` now resolves indirect PageLabels `/S`, `/P`, and `/St` operands before formatting preview/page-inventory labels.
- Empty formatted preview labels now fall back to one-based page labels, matching `PdfTextExtractor` fallback behavior instead of emitting blank page labels.
- Added a focused current-base fixture with indirect label dictionaries and indirect style, prefix, and start operands for roman and alphabetic sections.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Front iv`, `Front v`, and `App-Z`, proving preview labels match text-extraction labels.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL resolves indirect PageLabels style prefix and start operands for preview metadata
Expected: ["Front iv","Front v","App-Z"]
Actual: ["","",""]
1 test files, 16 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
PASS keeps parent PageLabels Limits across indirect kid number-tree boundaries
PASS keeps PDF alphabetic PageLabels repeated-letter style aligned with preview metadata
PASS resolves indirect PageLabels style prefix and start operands for preview metadata
1 test files, 19 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-indirect-operands-currentbase.php
```

The smoke emitted `page_labels=["Front iv","Front v","App-Z"]`, `preview_page_labels=["Front iv","Front v","App-Z"]`, `selected_preview_page_label="App-Z"`, `empty_preview_labels_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted PageLabels basic number-tree parsing, direct `/Nums`, indirect `/Kids`, local or inherited `/Limits`, alphabetic repeated-letter formatting, viewer preferences, outline page-label propagation, page transition/action review, or runtime preflight work. The bounded behavior is MarkerAppPreview resolving indirect label-dictionary operands and preserving fallback labels when an indirect/malformed label formats empty.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, page-tree traversal, PageLabels number-tree parser, marker-app preview summary, selected page-image plan metadata, and WordPress block smoke path.
