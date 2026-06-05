# Table Assigned Cell Geometry Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T024026Z`

Accepted base: `e6f1e5608047d4cad7cbaa5023f70e18fa90d5e2`

## Source Truth

- Upstream `tabled` saved table cells are `SpanTableCell` records with `text`, `row_ids`, `col_ids`, and optional `order`.
- Upstream table formatters sort table cells by assigned row/column anchors when emitting Markdown/HTML. A supplied saved recognition bundle that already includes `row_ids` and `col_ids` should therefore preserve those assignments rather than rerunning geometry overlap assignment.
- This remains inside the no-GPU markerPDF scope: the patch consumes supplied table-boundary records and does not invoke Surya, Tabled, OCR, Torch, or model workers.

## Behavior Added

`TableRecognizer::formatRecognizedTables()` now detects recognized table cells where every cell already has non-null first `row_ids` and `col_ids`. In that complete-assignment case it normalizes and trusts the supplied assigned cells, preserving `row_ids`, `col_ids`, and `order` metadata for WordPress table review and Markdown formatting.

Raw detector cells without complete assignments continue through the existing `assignRowsColumns()` geometry path.

## Evidence

Red-first focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php
```

Before the source change this failed with `Feature` reassigned from supplied `col_ids=[0]` to geometry-derived `col_ids=[1]`, and the supplied conversion metadata showed the same reassignment.

Post-change expected focused verification:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNormalizedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php
php lanes/markerpdf/examples/wordpress-table-assigned-cell-geometry-boundary-currentbase.php
```

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP table recognizer, bbox geometry, Markdown formatter, and supplied-boundary conversion metadata.

## Next Task

Continue non-overlapping markerPDF supplied-boundary work for table/equation handoffs, page geometry, searchable text extraction, annotations/forms, fonts/CMaps, stream filters, image/filter metadata, xref repair, and security preflight.
