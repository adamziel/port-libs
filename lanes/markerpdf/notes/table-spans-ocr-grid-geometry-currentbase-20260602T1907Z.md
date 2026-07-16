# Table Spans OCR Grid Geometry Current Base

Slice: `table-spans-ocr-grid-geometry-currentbase`
Session: `port-dev-markerpdf-table40pdf-20260602T1907Z`
Base accepted HEAD: `78dacbd21ee6b9a83b42fbcf69facc371244266b`

## Source Truth

- Upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes table formatting through `marker/tables/table.py::format_tables()`: get cells, recognize table structure, assign rows/columns, then format Markdown.
- Locked `tabled-pdf` 0.1.4 `tabled/schema.py::SpanTableCell` carries `row_ids` and `col_ids`.
- Locked `tabled-pdf` 0.1.4 `tabled/assignment.py::handle_rowcol_spans()` expands spans from row/column band intersections.
- Locked `tabled-pdf` 0.1.4 `tabled/formats/markdown.py` and `tabled/formats/html.py` render only the anchor row/column for spans, so native review metadata must preserve covered grid geometry before formatting drops those cells.

## Implementation

- `TableRecognizer::spanningGridReview()` now emits row/column-band `grid_bbox` values for anchor, covered, and empty grid slots and per-render-cell `grid_cell_bboxes` for spans.
- `TableRecognizer::gridBorderConflictReview()` accepts optional recognized row/column bands and enriches candidate and assigned grid cells with `grid_bbox` plus per-span `grid_cell_bboxes`.
- `SuppliedDocumentConverter` passes recognized table `rows` and `cols` into OCR grid-border conflict review so supplied WordPress imports carry that geometry.
- Added `wordpress-table-spans-ocr-grid-geometry-currentbase.php` as a local WordPress table smoke with forced OCR cells, colgroup/rowgroup spans, covered-cell skipping, stale pdftext line exclusion, and no Python/model/external-tool execution.

## Focused Evidence

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-spans-ocr-grid-geometry-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with `2 test files, 511 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-spans-ocr-grid-geometry-currentbase.php` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Counters

- Behavior tests move `679 -> 681 pass / 0 fail`.
- WordPress scenarios move `679 -> 680`.
- Mapped semantics move `493 -> 494 / 78`.

## Dependency Closure

No new support component is needed. This reuses the native supplied-document converter, table recognizer, and tabled-style row/column assignment metadata already present in the lane. Full live upstream parity remains blocked on the Python/PDF/model stack: `pdftext`, `pypdfium2`, Surya/Torch, `tabled-pdf` model execution, Texify, OCRMyPDF/Tesseract setup, Streamlit/FastAPI runtime, and external PDF/raster tooling.

## Non-Overlap

This does not repeat accepted OCR source-order grid-border assignment, OCR polygon table routing, merged-cell geometry, multiline header folding, rotated rowspan/header-axis review, or grid-border assigned-row labeling. The new behavior is row/column-band bbox geometry for existing span, covered-slot, candidate-conflict, and assigned-conflict review rows before Markdown/HTML formatting loses covered span occupancy.
