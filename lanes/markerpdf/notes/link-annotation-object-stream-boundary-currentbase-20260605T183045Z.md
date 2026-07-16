# markerPDF link annotation object-stream boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T183045Z`
Session: `port-dev-markerpdf-annotations-links-20260605T183045Z`
Base accepted HEAD: `8a209745d849ff74146dd38c58413945e1e6a43c`

## Source Truth

- Upstream markerPDF relies on PDF parser/pdftext/PDFium boundaries for searchable PDFs before model/OCR fallback. This slice stays inside native searchable-PDF link annotation parsing and does not run Python, PDFium, OCR, Surya, Texify, Torch, Streamlit/FastAPI workers, or external PDF tools.
- PDF 1.5 xref-stream type-`2` rows select compressed object-stream members. For page `/Annots`, the xref-selected compressed Link annotation body must override a stale direct object with the same number before WordPress span promotion.
- Annotation payload text, stale direct annotation URIs, and object-stream review data remain review-only and must not leak into visible Gutenberg paragraphs.

## Implementation

`PdfLinkAnnotationExtractor` now builds a bounded selected-object overlay from the latest xref stream. Direct generation-zero rows and type-`2` object-stream members selected by the xref stream are preferred before the raw regex object table, so a compressed current Link annotation can drive span promotion while stale direct same-number annotations are excluded.

The implementation is intentionally local to link extraction: it supports the existing no-GPU native parser path with FlateDecode/ASCIIHexDecode stream decoding for xref/object streams and does not broaden into live OCR/model execution.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses xref-stream object-stream Link annotation bodies before stale direct annotation bodies
Values are not identical
Expected: 'https://example.com/current-compressed-link'
Actual: 'https://stale.example.com/direct-link'

1 test files, 4 assertions, 1 failures
```

## Focused Verification

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationObjectStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses xref-stream object-stream Link annotation bodies before stale direct annotation bodies

1 test files, 18 assertions, 0 failures
```

Adjacent link/annotation family:

```text
php tools/run-tests.php $(printf '%s\n' lanes/markerpdf/tests/PdfLinkAnnotation*Test.php lanes/markerpdf/tests/PdfAnnotationLink*Test.php lanes/markerpdf/tests/PdfPageAnnots*Link*Test.php lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php | while IFS= read -r f; do [ -e "$f" ] && printf '%s ' "$f"; done)
Focused test run: 35 selected test files (root lock skipped)
35 test files, 1187 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-object-stream-boundary-currentbase.php
```

The smoke emits `current_compressed_link_promoted=true`, `stale_direct_link_excluded=true`, `stale_span_unlinked=true`, `visible_text_excludes_annotation_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted named-destination alias boundary, Link URI base, primary-action, previous-URI, QuadPoints, widget-parent, generation, xref-free annotation, or general text-parser object-stream repair slices. The new boundary is specifically xref-stream type-`2` object-stream Link annotation ownership before stale direct same-number Link annotations are considered for WordPress span promotion.

## Dependency Closure

No new support component is needed. The slice reuses native PHP PDF token parsing, FlateDecode/ASCIIHexDecode stream decoding, existing Link annotation span promotion, and the current WordPress smoke path. Remaining GPU/model/OCR parity is intentionally out of scope under the markerPDF no-GPU directive.
