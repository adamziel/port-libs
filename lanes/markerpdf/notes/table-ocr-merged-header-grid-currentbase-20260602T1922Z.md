# Table OCR Merged Header Grid Current Base

Slice: `table-ocr-merged-header-grid-currentbase`

Session: `port-dev-markerpdf-table41pdf-20260602T1922Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes table formatting through `marker/tables/table.py::format_tables()`.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::recognize_tables()` applies OCR text onto detector cells before row/column assignment.
- Locked `tabled-pdf==0.1.4` `tabled/assignment.py::assign_rows_columns()` stores merged occupancy on `SpanTableCell.row_ids` and `col_ids`.
- Locked `tabled-pdf==0.1.4` `tabled/formats/markdown.py::markdown_format()` and `html.py::html_format()` build rows from each cell's first row/column anchor and call `tabulate(..., headers="firstrow")`, so native WordPress import needs review metadata before Markdown drops merged-header occupancy.

Current-base red probe:

- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed before implementation with missing `header_cells` in the new direct and supplied-document probes.
- The existing grid carried `th`, `scope`, `rowspan`, `colspan`, and axis metadata, but data cells had no stable `headers` list for merged OCR headers.

Implementation:

- `TableRecognizer::spanningGridReview()` now emits `header_cells` with stable per-table header IDs such as `h-r0-c0`, `h-r0-c2`, and `h-r2-c0`.
- Data anchor cells now carry `headers`, `column_header_ids`, `row_header_ids`, `header_texts`, and `header_text`, derived from merged column headers and row headers in the existing span grid.
- Header/data reference fields are copied onto anchor `grid_cells`, while covered cells remain skipped through the existing `covered_by` metadata.
- `examples/wordpress-table-ocr-merged-header-grid-currentbase.php` renders `id` on header cells and `headers` on data cells for a forced-OCR merged header grid.

Focused behavior:

- Direct recognizer coverage proves `Images` maps to `h-r0-c0 h-r2-c0` (`Inventory axis` plus `Media group`) and `Needs review` maps to `h-r0-c2 h-r2-c0` (`Status` plus `Media group`).
- Supplied-document coverage proves the same metadata survives forced OCR table routing, assigned merged headers, stale pdftext table-line exclusion, and final Markdown conversion.
- The WordPress smoke reports header IDs, data-cell header references, stale-table exclusion, and native-only execution flags.

Verification:

- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` red-first failed with 2 failures before implementation.
- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-merged-header-grid-currentbase.php` passed.
- `jq empty lanes/markerpdf/lane-status.json` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 2 files, 516 assertions, and 0 failures after implementation.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with 3 files, 547 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-ocr-merged-header-grid-currentbase.php` emitted `has_merged_header_id=true`, `maps_images_to_merged_and_row_headers=true`, `maps_needs_review_to_status_and_row_headers=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php lanes/markerpdf/examples/wordpress-table-ocr-merged-header-grid-currentbase.php | jq '{scenario, header_ids, data_headers, has_merged_header_id, has_status_header_id, has_row_header_id, maps_images_to_merged_and_row_headers, maps_needs_review_to_status_and_row_headers, covered_header_cells_skipped, excluded_stale_pdftext_table_line, executes_python_or_models, executes_external_pdf_tools}'` passed with the expected true/native-only flags.
- `git diff --check -- lanes/markerpdf` passed.

Dependency closure:

No new support component is needed. This slice reuses the native supplied-document converter, table recognizer, forced-OCR cell routing, tabled-style row/column assignment, spanning-grid review metadata, and WordPress smoke path. Full upstream runner parity remains dependency-gated by Poetry plus `pdftext`, `pypdfium2`/PDFium, Surya/Torch models, `tabled-pdf`, Texify, OCR tooling, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers.

Non-overlap:

This does not repeat accepted forced-OCR routing, OCR prediction unwrapping, bbox multiline OCR folding, merged-cell geometry, spanning header grid rendering, rotated header axes, OCR row/column continuation folding, header-axis classification, grid-border conflict assignment, or assigned grid-border review. The new behavior is specifically the merged OCR header reference grid: stable header IDs plus data-cell `headers` mappings for WordPress-accessible table output.
