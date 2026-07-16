# Table Header Spanning Grid Review Current Base

Slice: `table-header-spanning-grid-review-currentbase-20260602T1608Z`

Source truth:

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes recognized tables through `marker/tables/table.py::format_tables()` and the locked `tabled-pdf==0.1.4` dependency.
- `/tmp/markerpdf-tabled-src/tabled/schema.py::SpanTableCell` retains `row_ids` and `col_ids` for cells that span multiple row/column bands.
- `/tmp/markerpdf-tabled-src/tabled/assignment.py::handle_rowcol_spans()` expands those ID lists when cell geometry crosses open bands.
- `/tmp/markerpdf-tabled-src/tabled/formats/markdown.py::markdown_format()` and `html.py::html_format()` pass `headers="firstrow"` to `tabulate`, but consume only each cell anchor's first row and column, so the native WordPress path needs a review grid to render `<th>`, `scope`, `rowspan`, and `colspan` without reparsing Markdown.

Implementation:

- `TableRecognizer::spanningGridReview()` now returns table rows, columns, renderable anchor cells, and every grid coordinate as `anchor`, `covered`, or `empty`.
- Top-row anchors are marked `tag=th` with `scope=col` or `scope=colgroup`; first-column row-spanning anchors are marked `tag=th`, `scope=rowgroup`; ordinary cells remain `td`.
- `SuppliedDocumentConverter` now emits `metadata.table_spanning_grid_review` beside `table_assigned_cells` and `table_merged_cell_geometry`.
- `examples/wordpress-table-header-spanning-grid-review-currentbase.php` renders that metadata into a WordPress table preview with `<th scope="colgroup" colspan="3">` for the spanning OCR header and `<th scope="rowgroup" rowspan="2">` for the spanning row header, while skipping covered grid cells.

Focused behavior:

- Direct recognizer test proves a full-width first-row header is a `column_header`/`colgroup` anchor and a left row-spanning label is a `row_header`/`rowgroup` anchor.
- Supplied-document test proves the same review grid is emitted from forced-OCR table recognition metadata while stale pdftext table text remains excluded.
- Smoke output reports `has_th_colgroup_colspan_3=true`, `has_th_rowgroup_rowspan_2=true`, `covered_header_cells_skipped=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-header-spanning-grid-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed with 1 file, 77 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 1 file, 189 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 3 files, 297 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-header-spanning-grid-review-currentbase.php` emitted the expected scoped `<th>` colspan/rowspan HTML, covered-cell skipping, stale-table exclusion, and native-only flags.

Dependency closure:

No new support component is needed. This slice reuses the native supplied-document converter, table formatter, table recognizer, row/column assignment, merged-cell metadata, and WordPress smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`, Surya, `tabled-pdf`, Texify, Torch/model downloads, OCR tooling, Streamlit/FastAPI runtime paths, and live benchmark workflows.

Non-overlap:

This does not repeat the accepted direct merged-cell geometry or forced-OCR merged-cell geometry slices. The new behavior is the table header spanning review grid: anchor/covered coordinate metadata plus `th`/`td` render roles and header scopes for WordPress import.
