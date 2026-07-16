# Table OCR Grid Conflict Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260604T235850Z`

Base accepted HEAD: `43d6c6085912b0a2e7f68f49d9869c535f444985`

## Source Truth

- Upstream markerPDF routes table replacement through `marker.tables.table::format_tables()`: table crops, cell routing, recognition, row/column assignment, Markdown formatting, then final document conversion.
- Locked `tabled-pdf` 0.1.4 `tabled.assignment.assign_rows_columns()` carries model row/column bands through assignment and `SpanTableCell` carries `bbox`, `row_ids`, and `col_ids`.
- Locked `tabled-pdf` 0.1.4 `tabled.assignment.handle_rowcol_spans()` preserves grid occupancy before Markdown/HTML rendering consumes only anchor coordinates.
- This PHP slice keeps the no-GPU supplied-boundary contract: OCR/table model outputs are supplied fixtures, and the native review path exposes geometry metadata without executing Surya, tabled models, Python, or external PDF tools.

## Implementation

- `TableRecognizer::gridBorderConflictReview()` now attaches the already-computed `table_grid_geometry_boundary` review to each OCR grid-border conflict row when a table crop size is supplied.
- Existing assignment, Markdown, span-grid, and OCR source-order behavior are unchanged.
- WordPress import metadata under `table_ocr_grid_border_conflicts` now carries the clipped row/column band counts and per-band statuses alongside candidate and assigned grid-cell review data.

## Red/Green Evidence

Red-first before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php`

Result: `1 test files / 8 assertions / 2 failures`; both failures were missing `geometry_boundary_review` on OCR grid-border conflict rows.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php`

Result: `1 test files / 39 assertions / 0 failures`.

Adjacent table family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`

Result: `4 test files / 1025 assertions / 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-table-ocr-grid-conflict-boundary-currentbase.php`

Result: emitted `wordpress-table-ocr-grid-conflict-boundary-currentbase` with `table_crop_boundary={"width":200,"height":80}`, `conflict_count=2`, `clipped_band_count=4`, `excluded_band_count=2`, stale pdftext table line excluded, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat accepted table row/column band clipping, assigned-cell bbox clipping, named-bbox normalization, numeric-string geometry coercion, OCR polygon routing, forced-OCR merged cell geometry, header-axis accessibility grids, or Markdown table formatting. The new behavior is specifically propagation of the existing crop-boundary grid review into OCR grid-border conflict rows consumed by WordPress review overlays.

## Dependency Closure

No new support component is needed. The slice reuses `TableRecognizer`, `SuppliedDocumentConverter`, supplied OCR/table recognition fixtures, and existing table crop-size planning. Full upstream live table/OCR parity remains intentionally out of scope under the current no-GPU direction because it requires Surya/Torch/tabled model execution.

## Next

Continue with non-overlapping native markerPDF behavior: searchable-PDF parser fidelity, page geometry, fonts/CMaps, xref repair, annotations/forms/security preflight, image/filter metadata, or supplied-boundary table/equation handoffs.
