# Table Header Grid Rowspan Current Base

Slice: `table-header-grid-rowspan-currentbase`

Session: `port-dev-markerpdf-table44-20260602T2001Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes recognized table output through `marker/tables/table.py::format_tables()`.
- Locked `tabled-pdf==0.1.4` `/tmp/markerpdf-tabled-src/tabled/schema.py::SpanTableCell` preserves `row_ids` and `col_ids`.
- Locked `tabled-pdf==0.1.4` `/tmp/markerpdf-tabled-src/tabled/assignment.py::handle_rowcol_spans()` expands those IDs when cell geometry crosses open row/column bands.
- Locked `tabled-pdf==0.1.4` `/tmp/markerpdf-tabled-src/tabled/formats/markdown.py::markdown_format()` and `html.py::html_format()` consume each cell's first row/column anchor with `tabulate(..., headers="firstrow")`, so native WordPress import needs review metadata before Markdown drops covered header-grid occupancy.

Current-base red probe:

- An inline current-base probe with a top-left header spanning rows `0,1`, an `Assets` group header spanning columns `1,2`, and row-1 `Images` / `State` subheaders returned only `h-r0-c0` and `h-r0-c1` header ids.
- Before the patch, `Images` and `State` were data cells, so body cells `12` and `Ready` referenced only the broad `Assets` group and missed their row-1 subheaders.

Implementation:

- `TableRecognizer::spanningGridReview()` now derives `column_header_rows` from top-row rowspans.
- Cells anchored in those derived header rows are classified as column headers and receive stable `header_id` values.
- Data-cell header references now include both the top group header and the rowspanned header-row subheader, for example `12` maps to `h-r0-c1 h-r1-c1`.
- `examples/wordpress-table-header-grid-rowspan-currentbase.php` renders the review grid into WordPress-style table HTML with the rowspanned corner header, group header, subheader ids, and body-cell `headers` attributes.

Focused behavior:

- Direct recognizer coverage proves `column_header_rows=[0,1]`, `Images` and `State` are `th scope="col"` subheaders, and body data cells reference the correct group/subheader pairs.
- Supplied-document coverage proves the same metadata survives forced OCR table routing, stale pdftext table-line exclusion, and final conversion metadata.
- The WordPress smoke reports `has_rowspanned_corner_header=true`, `has_assets_group_header=true`, `has_images_subheader=true`, `has_state_subheader=true`, `maps_count_to_group_and_subheader=true`, `maps_ready_to_group_and_subheader=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-header-grid-rowspan-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed with 1 file, 244 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 1 file, 345 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 3 files, 620 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-header-grid-rowspan-currentbase.php` emitted the expected rowspanned-header, subheader, data-cell header-reference, stale-table-exclusion, and native-only flags.

Dependency closure:

No new support component is needed. This slice reuses the native supplied-document converter, table formatter, table recognizer, forced-OCR cell routing, tabled-style row/column assignment, spanning-grid metadata, and WordPress smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`, Surya/Torch models, `tabled-pdf`, Texify, OCR tooling, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers.

Non-overlap:

This does not repeat accepted forced-OCR routing, OCR prediction unwrapping, multiline OCR header folding, merged-cell geometry, horizontal or rotated spanning-header review, OCR continuation folding, header-axis classification, merged header IDs, or grid-border assignment review. The new behavior is specifically rowspanned header rows promoting second-row subheaders into stable WordPress `headers` references.
