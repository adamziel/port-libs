# Table OCR Merged Cell Header Axis Current Base

Slice: `table-ocr-merged-cell-header-axis-currentbase-20260602T173924Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes recognized table formatting through `marker/tables/table.py::format_tables()`.
- Locked `tabled-pdf==0.1.4` keeps merged-cell occupancy on `tabled/schema.py::SpanTableCell.row_ids` and `col_ids`.
- Locked `tabled-pdf==0.1.4` `tabled/assignment.py::handle_rowcol_spans()` expands row/column IDs when cell geometry crosses open bands.
- Locked `tabled-pdf==0.1.4` `tabled/formats/markdown.py::markdown_format()` and `html.py::html_format()` feed only first-row/column anchor cells to `tabulate(..., headers="firstrow")`, so the native WordPress review grid must preserve header axis metadata before Markdown drops covered cells.

Implementation:

- `TableRecognizer::spanningGridReview()` now emits `header_axis` and `header_axes` on render cells and anchor grid cells.
- First-row headers report `column`; rowgroup headers report `row`; a top-left merged header spanning both the first row and first column reports `both` plus `['column', 'row']`.
- The existing `scope` and `header_role` fields remain unchanged, so accepted `colgroup`, `col`, and `rowgroup` consumers keep the same behavior.
- `examples/wordpress-table-ocr-merged-cell-header-axis-currentbase.php` renders the review grid to WordPress table HTML with `data-markerpdf-header-axis` on corner, column, and row headers.

Focused behavior:

- Direct recognizer coverage proves an OCR continuation anchored inside a top-left merged header is folded into one `Inventory axis` render cell with `rowspan=2`, `colspan=2`, `scope=colgroup`, `header_axis=both`, and `header_axes=['column','row']`.
- Supplied-document coverage proves the same metadata survives forced OCR table routing, row/column assignment, stale pdftext table-line exclusion, and final Markdown conversion.
- The WordPress smoke reports `corner_header_axis_both=true`, `has_corner_header_axis_attr=true`, `has_column_header_axis_attr=true`, `has_row_header_axis_attr=true`, `covered_axis_cells_skipped=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-merged-cell-header-axis-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed with 1 file, 161 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 1 file, 255 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 3 files, 447 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-ocr-merged-cell-header-axis-currentbase.php | jq '{scenario, corner_header_axis_both, corner_header_axes, has_corner_header_axis_attr, has_column_header_axis_attr, has_row_header_axis_attr, covered_axis_cells_skipped, excluded_stale_pdftext_table_line, executes_python_or_models, executes_external_pdf_tools}'` emitted the expected true/native-only flags.

Dependency closure:

No new support component is needed. This slice reuses the native supplied-document converter, table recognizer, forced-OCR cell routing, tabled-style row/column assignment, spanning-grid review metadata, and WordPress smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`/PDFium, Surya/Torch models, `tabled-pdf`, Texify, OCR tooling, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers.

Non-overlap:

This does not repeat accepted forced-OCR merged-cell geometry, horizontal spanning-header grid review, rotated header axis metadata, multiline OCR bbox-fragment folding, or OCR rowspan/colspan continuation folding. The new behavior is specifically header-axis classification for merged OCR header review cells, especially top-left corner headers that govern both row and column axes.
