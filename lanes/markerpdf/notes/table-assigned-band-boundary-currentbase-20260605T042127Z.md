# markerPDF assigned table band boundary current base

## Source Truth

- Upstream `sddai/markerPDF` delegates recognized table structure to locked `tabled-pdf==0.1.4`.
- `tabled/schema.py::SpanTableCell` stores assigned `row_ids` and `col_ids`.
- `marker.tables.table.get_table_boxes()` crops each table image before `tabled.assignment.assign_rows_columns()` runs, so saved assigned cell IDs are table-crop-local and must be bounded to row/column bands that still exist inside that crop.

## Behavior

This slice adds an active-band boundary for already assigned supplied table cells. `TableRecognizer::formatRecognizedTables()` still trusts complete upstream `row_ids`/`col_ids`, but now removes IDs whose model row/column bands are outside the cropped table image, drops cells whose assigned row or column has no active band, and attaches geometry-order metadata to kept assigned cells.

The WordPress metadata now exposes `table_assigned_band_boundary_reviews` with active row/column IDs, per-cell `within_active_bands`, `trimmed_to_active_bands`, and `excluded_inactive_*_band` statuses. This prevents stale serialized tabled results from creating ghost rows or columns in Markdown while keeping review evidence for the excluded cells.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBandBoundaryCurrentBaseTest.php
FAIL bounds already assigned table cells to active crop-local row and column bands
FAIL surfaces assigned band boundary metadata through supplied WordPress conversion
1 test files, 3 assertions, 2 failures
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBandBoundaryCurrentBaseTest.php
PASS bounds already assigned table cells to active crop-local row and column bands
PASS surfaces assigned band boundary metadata through supplied WordPress conversion
1 test files, 42 assertions, 0 failures
```

Adjacent table/supplied-document family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNormalizedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
11 test files, 1393 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-assigned-band-boundary-currentbase.php
exit 0; ghost_cells_filtered_from_assignment=true; trimmed_cell_count=1; excluded_cell_count=2
```

## Non-Overlap

This does not repeat accepted table crop positive-area filtering for assigned cells, page-image or normalized coordinate-space localization, named/numeric/reversed bbox normalization, table-cell crop-boundary review, grid-border conflict review, arbitrary band-id ordering for newly assigned cells, merged-cell span-grid review, OCR polygon routing, or forced-OCR table routing. The bounded behavior is specifically saved assigned `SpanTableCell` IDs that point to row/column bands excluded by the current table crop.

## Dependency Closure

No new support component is needed. This reuses the native supplied table recognition handoff, table-crop geometry boundary, assigned-cell Markdown formatter, span-grid review metadata, and WordPress smoke path. Live Surya/tabled model execution, OCR, Python workers, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
