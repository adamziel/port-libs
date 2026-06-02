# Table OCR Merged-Cell Geometry Review Current Base

Slice: `table-ocr-merged-cell-geometry-review-currentbase-20260602T1548Z`

Source truth:

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes tables through `marker/tables/table.py::format_tables()`: `get_table_boxes()`, `tabled.inference.recognition::get_cells()`, `recognize_tables()`, `tabled.assignment::assign_rows_columns()`, then `formatter("markdown", cells)`.
- Locked `tabled-pdf==0.1.4` keeps merged-cell occupancy on `SpanTableCell.row_ids` and `SpanTableCell.col_ids` in `/tmp/markerpdf-tabled-src/tabled/schema.py`.
- `/tmp/markerpdf-tabled-src/tabled/assignment.py::handle_rowcol_spans()` expands those ID lists when a cell geometry crosses open row/column bands.
- `/tmp/markerpdf-tabled-src/tabled/formats/markdown.py::markdown_format()` reads only the first row and column for Markdown placement, so downstream WordPress imports need a separate review metadata path to emit stable `rowspan`/`colspan` attributes.

Implementation:

- `SuppliedDocumentConverter` now emits `metadata.table_merged_cell_geometry` beside `metadata.table_assigned_cells` for recognized tables.
- Forced-OCR table cells that received supplied OCR text now preserve merged-cell review rows after tabled-style assignment, including text, `row_ids`, `col_ids`, `rowspan`, `colspan`, anchor cell, occupied grid cells, detector `cell_bbox`, and row/column-band `grid_bbox`.
- `examples/wordpress-table-ocr-merged-cell-geometry-currentbase.php` renders the review metadata into a core table preview with `colspan="3"` for an OCR-derived full-width header and `rowspan="2"` for an OCR-derived side label while excluding stale pdftext table text.

Focused behavior:

- The new supplied-document test runs forced OCR with six detector cells. OCR text supplies `Inventory OCR summary`, `Media group`, `Image count`, `12`, `Review state`, and `Needs review`.
- The full-width header is exposed as `row_ids=[0]`, `col_ids=[0,1,2]`, `rowspan=1`, `colspan=3`, anchor `0:0`, and `grid_bbox=[0,0,358,25]`.
- The side label is exposed as `row_ids=[1,2]`, `col_ids=[0]`, `rowspan=2`, `colspan=1`, anchor `1:0`, and `grid_bbox=[0,35,110,110]`.
- The WordPress smoke reports `has_colspan_3=true`, `has_rowspan_2=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Verification:

- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-merged-cell-geometry-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 1 test file, 177 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 3 test files, 265 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-ocr-merged-cell-geometry-currentbase.php` emitted the expected `colspan="3"`, `rowspan="2"`, OCR geometry metadata, stale-table exclusion, and native-only flags.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

Dependency closure:

No new support component is needed. This slice reuses the existing native supplied-document converter, table formatter, table recognizer, forced-OCR cell routing, row/column assignment, and WordPress table smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`, Surya, `tabled-pdf`, Texify, Torch/model downloads, OCR tooling, Streamlit/FastAPI runtime paths, and live benchmark workflows.

Non-overlap:

This does not repeat the accepted direct `TableRecognizer::mergedCellGeometry()` slice, supplied adjacent-table merge/equation/image arbitration, forced OCR text-line unwrap, JSON/PDF parser, object-stream, font, image, annotation, metadata, or AcroForm slices. The new behavior is the supplied-document forced-OCR import boundary where OCR-applied detector cells must expose merged-cell geometry metadata before Markdown formatting drops non-anchor span occupancy.
