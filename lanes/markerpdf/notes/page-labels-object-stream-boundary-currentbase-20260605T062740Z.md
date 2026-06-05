# markerPDF PageLabels object-stream boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T062740Z`

## Source Truth

- Upstream markerPDF gets page-local text and preview page metadata through the loaded PDF document before OCR/model conversion; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to page-local text extraction.
- PDF 1.5 object streams can store ordinary indirect objects, including a catalog `/PageLabels` number-tree dictionary. The native text extractor already expands safe object-stream members, so preview labels should not fall back to physical page numbers when text extraction has a same-count resolved label list.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `MarkerAppPreview` now asks `PdfTextExtractor::extractPageLabels()` for the authoritative native label list when the resolved label count matches preview page inventory.
- The existing preview PageLabels parser remains the fallback for mismatched or unavailable labels.
- Added a focused fixture where catalog `/PageLabels 20 0 R` points to a label dictionary stored as an unfiltered `/Type /ObjStm` member. Native text extraction already produced `Obj-4` and `Tail-`; preview previously returned numeric fallback labels.
- Added a WordPress smoke that emits page-break metadata for `Obj-4` and `Tail-` while proving numeric preview fallback is rejected.

## Evidence

Red probe before the source edit:

```text
PdfTextExtractor::extractPageLabels(...) => ["Obj-4","Tail-"]
MarkerAppPreview::pageLabels(...) => ["1","2"]
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
1 test files, 136 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-object-stream-currentbase.php
```

The smoke emitted `page_labels=["Obj-4","Tail-"]`, `preview_page_labels=["Obj-4","Tail-"]`, `selected_preview_page_label="Tail-"`, `numeric_preview_fallback_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, `/Limits`, indirect `/Kids`, indirect `/S` `/P` `/St` operands, transitive operands, signed integers, PDFDocEncoding prefixes, escaped names, generation-exact direct dictionaries, indirect `/Nums` arrays, top-level token boundaries, malformed limits, trailer `/Root` catalog selection, or alphabetic repeated-letter formatting. The bounded behavior is preview alignment when the PageLabels number-tree dictionary itself is recovered from a PDF object stream by the native text extractor.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, object-stream expansion in `PdfTextExtractor`, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke path. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.
