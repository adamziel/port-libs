# Table OCR Border Conflict Grid Current Base

Slice: `table-ocr-border-conflict-grid-currentbase`

Session: `port-dev-markerpdf-table43-20260602T1949Z`

Base: `897b69532c5e798e5593546ffafd7329358413f2`

Source truth:

- Upstream `sddai/markerPDF` manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes tables through `marker/tables/table.py::format_tables()`.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::recognize_tables()` applies OCR prediction text back onto detector cells by zipping `ocr_pred.text_lines` with `table_cells[orig_idx]`.
- Locked `tabled-pdf==0.1.4` `tabled/assignment.py::assign_rows_columns()` preserves merged occupancy on `SpanTableCell.row_ids` and `SpanTableCell.col_ids`.
- Locked `tabled-pdf==0.1.4` `tabled/formats/markdown.py::markdown_format()` consumes only anchor row/column IDs through `tabulate(..., headers="firstrow")`, so WordPress import needs review metadata before Markdown drops covered cells.

Red-first probe:

- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` failed after adding the probes because OCR grid-border conflict rows had no `assigned_grid_render_cell` metadata.
- The probe uses OCR bboxes that cross detector-cell borders while the final assigned grid contains a merged corner header, a covered continuation cell, and a data cell with `headers` references.

Implementation:

- `TableRecognizer::gridBorderConflictReview()` now derives the same spanning-grid review used by `table_spanning_grid_review`.
- Each OCR border-conflict row can include `candidate_grid_render_cells` and `assigned_grid_render_cell`.
- The render-cell summaries preserve header IDs, covered-cell state, merged-header text, continuation routing, and data-cell `headers` / `header_texts` references without changing existing source-order OCR assignment or existing grid/bbox conflict keys.
- `examples/wordpress-table-ocr-border-conflict-grid-currentbase.php` demonstrates the WordPress path with native-only flags.

Focused behavior:

- Direct recognizer coverage proves a header conflict maps to `h-r0-c0`, a covered continuation conflict maps back to the merged `Inventory axis` render cell, and a data conflict maps to `h-r0-c0 h-r2-c0`.
- Supplied-document coverage proves the same metadata survives forced OCR table routing, table assignment, Markdown formatting, stale pdftext table-line exclusion, and final WordPress-oriented metadata.
- The WordPress smoke reports `grid_border_conflict_count=3`, `maps_header_border_conflict=true`, `maps_continuation_border_conflict_to_merged_header=true`, `maps_data_border_conflict_to_headers=true`, `excluded_stale_pdftext_table_line=true`, and native-only execution flags.

Verification:

- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` red-first failed with missing `assigned_grid_render_cell` metadata after the probes were added.
- `php -l lanes/markerpdf/src/TableRecognizer.php` passed.
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` passed.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-border-conflict-grid-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with `2 test files, 573 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-ocr-border-conflict-grid-currentbase.php | jq '{scenario, grid_border_conflict_count, maps_header_border_conflict, maps_continuation_border_conflict_to_merged_header, maps_data_border_conflict_to_headers, excluded_stale_pdftext_table_line, executes_python_or_models, executes_external_pdf_tools}'` passed with all expected true/native-only flags.
- `jq empty lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

Counters:

- Behavior tests move `742 -> 744 pass / 0 fail` from two new focused named tests.
- WordPress scenario count moves `742 -> 744` in `lane-status.json` to match the focused behavior count and new smoke path.

Dependency closure:

No new support component is needed. This slice reuses the native supplied-document converter, table recognizer, forced-OCR detector-cell routing, tabled-style row/column assignment, existing spanning-grid review metadata, and WordPress smoke path. Full upstream runner parity remains dependency-gated on Poetry plus `pdftext`, `pypdfium2`/PDFium, Surya/Torch models, `tabled-pdf`, Texify, OCR tooling, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers.

Non-overlap:

This does not repeat accepted source-order OCR conflict assignment, assigned grid-border row/column labeling, OCR polygon geometry assignment, merged-cell geometry, spanning header grid rendering, rotated header axes, row/column continuation folding, or merged OCR header `headers` references. The new behavior is specifically the bridge from OCR border-conflict rows to the final spanning-grid render cells used by WordPress accessible table output.
