# Table Rowspan Rotated Header Grid Review Current Base

Slice: `table-rowspan-rotated-header-grid-review-currentbase-20260602T1640Z`

Source truth:

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes supplied table extraction through `marker/tables/table.py::format_tables()`.
- Locked `tabled-pdf==0.1.4` keeps span occupancy on `SpanTableCell.row_ids` and `SpanTableCell.col_ids`.
- `/tmp/markerpdf-tabled-src/tabled/assignment.py::is_rotated()` detects rotated tables by row/column width-height ratios, and `assign_unassigned()` / `handle_rowcol_spans()` swap row and column intersection axes for rotated tables.
- `/tmp/markerpdf-tabled-src/tabled/formats/markdown.py` and `html.py` still format only first row/column anchors with `headers="firstrow"`, so WordPress needs explicit review metadata before Markdown drops covered span cells.

Implementation:

- `TableRecognizer::spanningGridReview()` now exposes table-level and render-cell-level `rotated`, `orientation`, `row_axis`, and `col_axis` metadata.
- Rotated tables report logical rows on the `x` axis and logical columns on the `y` axis, while preserving the accepted `th`/`td`, `scope`, `rowspan`, `colspan`, anchor, covered-cell, and grid-bbox metadata.
- `examples/wordpress-table-rowspan-rotated-header-grid-review-currentbase.php` runs the supplied-document converter through forced OCR table recognition and renders a WordPress table preview with rotated grid data attributes, a `scope="colgroup" colspan="3"` header, and a `scope="rowgroup" rowspan="2"` header.

Focused behavior:

- The direct recognizer test covers a rotated table whose row bands are vertical and column bands are horizontal.
- The full-height header expands to `col_ids=[0,1,2]`, reports `scope=colgroup`, and keeps `grid_bbox=[0,0,25,240]`.
- The first-column label expands to `row_ids=[1,2]`, reports `scope=rowgroup`, and keeps `grid_bbox=[35,0,110,70]`.
- The review payload reports `rotated=true`, `orientation=rotated`, `row_axis=x`, and `col_axis=y`.
- The WordPress smoke reports `rotated_grid_review=true`, `row_axis_x_col_axis_y=true`, `has_th_colgroup_colspan_3=true`, `has_th_rowgroup_rowspan_2=true`, `covered_rotated_cells_skipped=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-rowspan-rotated-header-grid-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed with 1 file, 100 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 3 files, 320 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-rowspan-rotated-header-grid-review-currentbase.php` emitted the expected rotated-grid, scoped-header, stale-table-exclusion, and native-only flags.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

Dependency closure:

No new support component is needed. This slice reuses the existing native supplied-document converter, table formatter, table recognizer, forced-OCR cell routing, row/column assignment, and WordPress table smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`, Surya, `tabled-pdf`, Texify, Torch/model downloads, OCR tooling, Streamlit/FastAPI runtime paths, and live benchmark workflows.

Non-overlap:

This does not repeat the accepted merged-cell geometry, discontiguous-span suppression, forced-OCR merged-cell geometry, or horizontal spanning-header grid slices. The new behavior is rotated table row/column axis metadata combined with row-spanning header-grid review for WordPress import.
