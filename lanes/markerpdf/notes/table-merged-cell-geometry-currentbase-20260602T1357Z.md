# Table Merged-Cell Geometry Current Base

Source truth: upstream `sddai/markerPDF` delegates recognized table structure to the locked `tabled-pdf==0.1.4` dependency. In `/tmp/markerpdf-tabled-src/tabled/schema.py`, `SpanTableCell` carries `row_ids` and `col_ids`; in `/tmp/markerpdf-tabled-src/tabled/assignment.py`, `handle_rowcol_spans()` expands those ID lists for cells whose geometry covers row or column bands; in `/tmp/markerpdf-tabled-src/tabled/formats/markdown.py`, Markdown formatting reads only the first row and column while preserving the span IDs on the assigned cell objects.

This slice adds a native PHP review boundary for that gap. `TableRecognizer::mergedCellGeometry()` now summarizes only multi-row or multi-column assigned cells, preserving text, `row_ids`, `col_ids`, `rowspan`, `colspan`, anchor row/column, occupied grid cells, original cell bbox, and the row/column-band `grid_bbox` when model row/column geometry is supplied. This lets WordPress import code emit stable `rowspan` and `colspan` attributes without inferring spans from cleaned display text or reparsing Markdown tables.

Focused behavior:

- The new test fixture assigns a table with a full-width header and a row-spanning side label through `assignRowsColumns()`.
- `mergedCellGeometry()` reports the header as `rowspan=1`, `colspan=3`, anchor `0:0`, occupied cells `(0,0),(0,1),(0,2)`, `cell_bbox=[5,5,295,20]`, and `grid_bbox=[0,0,300,25]`.
- It reports the side label as `rowspan=2`, `colspan=1`, anchor `1:0`, occupied cells `(1,0),(2,0)`, `cell_bbox=[5,36,92,109]`, and `grid_bbox=[0,35,95,110]`.
- `examples/wordpress-table-merged-cell-geometry-currentbase.php` renders a core table block with `colspan="3"` for the merged header and `rowspan="2"` for the side label, while emitting the geometry as review metadata and not running Python, Surya, tabled, pdftext, pypdfium, PIL, or model workers.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-merged-cell-geometry-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed with 1 file, 57 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 3 files, 245 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-merged-cell-geometry-currentbase.php` emitted the expected `colspan="3"`, `rowspan="2"`, geometry metadata, and `executes_python_or_models false`.

Dependency closure: no new support component is needed. This reuses the existing native supplied table-recognition boundary, row/column assignment, model row/column band geometry, and WordPress table rendering smoke. Full upstream runner parity remains dependency-gated on Poetry plus `pdftext`, `pypdfium2`, Surya, `tabled-pdf`, Texify, Torch/model downloads, OCR tooling, Streamlit/FastAPI runtime paths, and live benchmark workflows.
