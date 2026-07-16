# markerPDF marker app preview xref-stream Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T122826Z`

## Source Truth

Upstream markerPDF routes marker app preview boundaries through `marker_app.py` page counting and page-image planning, backed by PDF parser state from pdftext/PDFium. The native no-GPU PHP lane owns the searchable-PDF parser boundary that decides which catalog/page tree is current before WordPress preview metadata is emitted.

Incremental PDFs may end with a sparse latest xref stream that has `/Prev` but omits `/Root`; the current catalog can live in the previous xref-stream trailer, while an older classic base trailer still has a stale `/Root`. Preview inventory must walk the xref-section `/Prev` chain before falling back to broad trailer scanning, otherwise WordPress preview geometry can use stale base pages while text extraction uses current pages.

## Behavior

`MarkerAppPreview` now resolves the startxref section as either an xref stream or a classic xref table, reads `/Root`, and follows integer `/Prev` pointers when `/Root` is absent. Classic xref sections also check `/XRefStm` before `/Prev`, and the walk guards cycles, object-body ranges, and composite-token starts.

The focused fixture builds a stale base catalog/page tree in a classic trailer, appends the current catalog/page tree in a middle xref stream with `/Root 12 0 R`, then appends a sparse latest xref stream with only `/Prev` and one unrelated `/Index` row. `MarkerAppPreview::openPdfSummary()` and `getPageImagePlan()` now inherit the middle xref-stream `/Root`, selecting page object `14`, the current crop/media boxes, rotation `90`, and UserUnit `2.0` while excluding stale base text.

## Evidence

Red baseline before source repair:

```text
php -l lanes/markerpdf/tests/MarkerAppPreviewXrefPrevChainCurrentBaseTest.php && php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewXrefPrevChainCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/MarkerAppPreviewXrefPrevChainCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL inherits marker app preview root through sparse xref-stream Prev chain
Expected: 14
Actual: 3

1 test files, 3 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerAppPreviewXrefPrevChainCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 736 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-marker-app-preview-xref-stream-prev-currentbase.php
current_text_selected=true
stale_base_root_excluded=true
preview_page_object=14
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted metadata-side latest trailer `/Root` generation repair, xref-stream `/Prev` generation rows, classic sparse current-row repair, xref-stream `/Index` malformed row repair, compressed `/Prev` helper repair, PageLabels preview work, CropBox/UserUnit preview geometry, or stream/filter/CMap/image/form/model boundaries.

The bounded behavior here is specifically marker-app preview catalog selection when the latest sparse xref stream omits `/Root` and must inherit it through the `/Prev` chain before stale classic trailer fallback.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, balanced dictionary reader, classic xref trailer parsing, xref-stream dictionary parsing, startxref reader, xref `/Prev` repair boundary, page-tree inventory, and WordPress preview smoke renderer. Upstream PDFium/raster preview, OCR, Surya/Torch/Texify, Streamlit/FastAPI workers, and model benchmark parity remain intentionally out of scope under the no-GPU/no-live-model markerPDF direction.
