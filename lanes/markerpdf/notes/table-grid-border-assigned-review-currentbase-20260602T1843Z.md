# Table Grid Border Assigned Review Current Base

Slice: `table-grid-border-currentbase`

Session: `port-dev-markerpdf-table38pdf-20260602T1843Z`

Source truth:

- Upstream `sddai/markerPDF` `master` resolves to `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; markerPDF routes table formatting through `marker/tables/table.py::format_tables()`.
- The locked `tabled-pdf` tag `v0.1.4` resolves to `441936c1173cdda9c19bcd6066c16143192077f4`; `tabled/inference/recognition.py::recognize_tables()` zips OCR `text_lines` back onto `table_cells[orig_idx]`.
- Tabled assignment carries `row_ids` and `col_ids` on assigned cells before Markdown/HTML consume the first row/column anchors. The detector grid remains the authoritative review coordinate source for OCR lines crossing cell borders.

Implementation:

- `TableRecognizer::gridBorderConflictReview()` enriches existing `ocr_grid_border_conflicts` rows with `candidate_grid_cells`, `candidate_grid_anchors`, `candidate_row_ids`, `candidate_col_ids`, `grid_border_axes`, `grid_border_axis`, and `assigned_grid_cell`.
- `SuppliedDocumentConverter` publishes the enriched rows through `metadata.table_ocr_grid_border_conflicts`.
- The WordPress smoke now emits assigned grid review fields while preserving the accepted source-order OCR table text replacement.

Focused behavior:

- The recognizer test covers both-axis, column-axis, and row-axis OCR border conflicts in a 2x2 detector grid.
- The supplied-document test verifies the review metadata through conversion, preserves source-order table text, and excludes stale pdftext table lines.
- The WordPress smoke preserves the `Feature | Status` and `Images | Ready` detector-grid table text and emits column-border grid anchors.

Verification:

- `php -l lanes/markerpdf/src/TableRecognizer.php` - passed
- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` - passed
- `php -l lanes/markerpdf/tests/TableRecognizerTest.php` - passed
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` - passed
- `php -l lanes/markerpdf/examples/wordpress-table-grid-border-ocr-conflict-currentbase.php` - passed
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` - passed, `2 test files, 472 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-table-grid-border-ocr-conflict-currentbase.php` - passed, emitted `first_conflict_grid_border_axis=column`, `preserved_detector_grid_text=true`, and `excluded_stale_pdftext_table_line=true`
- `git diff --check -- lanes/markerpdf` - passed

Counters:

- Behavior tests move `648 -> 650 pass / 0 fail`.
- Focused table gate is `2 test files / 472 assertions / 0 failures`.
- Mapped semantics move `474 -> 475 / 78`.

Dependency closure:

No new support component is needed. This slice reuses supplied conversion, native detector-cell routing, tabled-style assignment, existing OCR grid-border conflict detection, and the existing WordPress smoke. Full upstream parity remains gated on Poetry/Python dependencies and model/runtime setup: `pdftext`, `pypdfium2`/PDFium, Surya/Torch, `tabled-pdf`, Texify, Streamlit/FastAPI, OCR/rendering helpers, benchmark/model downloads, and live Python workers.

Non-overlap:

This does not repeat the accepted source-order OCR conflict assignment. It maps accepted conflict rows onto assigned row/column grid coordinates and border-axis review metadata only.
