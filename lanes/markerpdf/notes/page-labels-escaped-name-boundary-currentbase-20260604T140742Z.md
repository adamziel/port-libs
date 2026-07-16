# markerPDF PageLabels escaped-name boundary

## Source Truth

- Upstream markerPDF extracts page text through page-scoped pdftext/PDFium iteration. Native PHP keeps catalog `/PageLabels` as page-break and review metadata aligned with page-local text extraction, without changing visible text payloads.
- PDF names may use `#xx` byte escapes, and catalog-level `/PageLabels` must be read from the top-level catalog dictionary. Nested private dictionaries such as `/PieceInfo << /PageLabels ... >>` are review-only and must not shadow the real catalog page-label number tree.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium, PDFium, Poppler, Ghostscript, Python, or external PDF tooling was run.

## Implementation

- `PdfTextExtractor` now resolves catalog `/PageLabels` through the existing token-aware top-level PDF value reader instead of a whole-body regex.
- `MarkerAppPreview` now uses a top-level PageLabels value lookup that skips nested values and decodes escaped PDF names, keeping `openPdfSummary()`, `pageLabels()`, and `getPageImagePlan()` aligned with text extraction.
- The focused fixture puts a stale nested `/PieceInfo /PageLabels` before the real escaped catalog `/Page#4Cabels`, and escapes `/Nums`, `/S`, `/P`, and `/St` inside the accepted number tree.

## Evidence

Red-first one-off fixture before the patch:

```text
PdfTextExtractor::extractPageLabels(...) => ["stale-99"]
MarkerAppPreview::openPdfSummary(... page_label) => ["stale-99"]
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps parent PageLabels Limits across indirect kid number-tree boundaries
PASS keeps PDF alphabetic PageLabels repeated-letter style aligned with preview metadata
PASS resolves indirect PageLabels style prefix and start operands for preview metadata
PASS keeps escaped catalog PageLabels names above nested private decoys

1 test files, 26 assertions, 0 failures
```

Adjacent gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineActionNameTreePageReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRemoteGoToETransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php
7 test files, 907 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-escaped-name-boundary-currentbase.php
escaped_catalog_page_labels_resolved=true
escaped_page_label_operands_resolved=true
nested_private_page_labels_ignored=true
page_labels=["Real 7","Named-"]
```

## Non-Overlap

This does not repeat accepted PageLabels direct/indirect `/Nums`, kid traversal, local or inherited `/Limits`, repeated-letter alphabetic labels, indirect `/S` `/P` `/St` operands, viewer preferences, outline page-label propagation, page transition/action review, xref repair, or stream dictionary escape-boundary slices. The bounded new behavior is escaped catalog and PageLabels operand names with nested private `/PageLabels` decoy exclusion.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, top-level dictionary value reader, page-tree traversal, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path.
