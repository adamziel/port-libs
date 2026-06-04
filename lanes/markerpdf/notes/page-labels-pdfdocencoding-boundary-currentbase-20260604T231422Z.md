# markerPDF PageLabels PDFDocEncoding boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260604T231422Z`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts searchable PDF text page-by-page through pdftext/PDFium (`dictionary_output(... page_range=...)` and `PdfDocument.get_page()`), so native `/PageLabels` stay page metadata aligned to page-local text extraction rather than model/OCR output: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium's PageLabel coverage treats `/PageLabels` as a catalog number tree whose leaf keys are page indices and whose page-label dictionaries have optional `/S`, `/P`, and `/St`; `/P` is a string prefix: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- PDF text strings without UTF-16 BOM use PDFDocEncoding. Page-label prefixes are PDF text strings, so literal, hex, and indirect `/P` operands must be decoded before WordPress page-break metadata or marker-app previews expose labels.
- This slice stays inside native no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pageLabelPrefix()` now decodes `/P` through a PageLabels-specific PDF text-string path, preserving existing UTF-16 BOM handling while adding PDFDocEncoding for non-BOM strings.
- The new text-string path resolves indirect `/P` operands and supports literal, hexadecimal, and previously tolerated name tokens without changing the generic content-stream text decoder.
- `MarkerAppPreview` now applies the same PDFDocEncoding fallback for PageLabels prefixes, keeping `openPdfSummary()`, `pageLabels()`, and `getPageImagePlan()` aligned with native text extraction.
- Added a focused fixture covering literal PDFDocEncoding bytes, hex-string PDFDocEncoding bytes, and indirect `/P` operands for page-label prefixes.
- Added a WordPress smoke rendering decoded labels into Gutenberg page-break metadata while proving raw encoded bytes are excluded.

## Evidence

Red-first focused check before the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL decodes PDFDocEncoding PageLabels prefixes before WordPress page metadata
Expected: ["WP\u2022-Import\u2020 3","Review \u201cPDF\u201d","Appendix\ufb01\ufb02 Z"]
Actual: ["WP\ufffd-Import\ufffd 3","Review \ufffdPDF\ufffd","Appendix\ufffd\ufffd Z"]
1 test files, 27 assertions, 1 failures
```

Focused PageLabels check after the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 32 assertions, 0 failures
```

Focused family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
3 test files, 770 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-pdfdocencoding-boundary-currentbase.php
```

The smoke emitted `literal_prefix_decoded=true`, `hex_prefix_decoded=true`, `indirect_prefix_decoded=true`, `raw_pdfdoc_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted PageLabels direct/indirect `/Nums`, indirect `/Kids`, local or inherited `/Limits`, repeated-letter alphabetic labels, indirect `/S` `/P` `/St` operand resolution, escaped catalog names, nested private `/PageLabels` decoy exclusion, viewer preferences, outline page-label propagation, page transition/action review, or PDFDocEncoding trailer `/Info` metadata decoding. The bounded behavior is PDFDocEncoding decoding for catalog PageLabels prefix text strings in text extraction and marker-app preview metadata.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object parser, indirect object resolver, PageLabels number-tree parser, marker-app preview inventory, and WordPress smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by no-GPU/no-live-model scope.
