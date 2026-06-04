# Table Cell Geometry Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260604T225410Z`

Base accepted HEAD: `524dc40526b2fcb46fefc7d28613d818c4db4c08`

## Source Truth

Upstream tabled/marker keeps recognized table cell bboxes in table-crop-local coordinates through assignment and formatting. Existing markerPDF PHP behavior already clipped row and column bands for WordPress review metadata; this slice adds the same crop-boundary handoff for the assigned cell bboxes themselves without changing assignment or Markdown output.

## Implementation

- `TableRecognizer::spanningGridReview()` now emits `cell_geometry_boundary_review` when a table crop size is supplied.
- Each review row keeps `original_bbox`, `clipped_bbox`, optional active `bounded_bbox`, `status`, row/column ids, and `upstream_cell_bbox_retained=true`.
- Render/grid review cells expose `cell_boundary_status`, `bounded_cell_bbox`, and boundary counts so WordPress overlays can draw crop-safe table cell regions.
- Assignment and Markdown continue to use the original upstream cell bbox; the bounded bbox is review-only metadata.

## Red/Green Evidence

Red-first before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php`

Result: `1 test files, 3 assertions, 2 failures`; missing `table_cell_geometry_boundary` review and supplied conversion still lacked the expected table replacement for the new fixture.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php`

Result: `1 test files, 37 assertions, 0 failures`.

Focused table recognizer family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php`

Result: `2 test files, 422 assertions, 0 failures`.

Focused supplied-converter family:

`php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php`

Result: `2 test files, 570 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-table-cell-geometry-boundary-currentbase.php`

Result: emitted `wordpress-table-cell-geometry-boundary-currentbase` with `table_crop_size={"width":240,"height":80}`, `clipped_cell_count=2`, `excluded_cell_count=0`, stale pdftext table line excluded, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat prior row/column band clipping, named-bbox normalization, numeric-string geometry coercion, table text cell routing, OCR/model execution, or live Surya/tabled model behavior. It is limited to supplied assigned-cell boundary review metadata in the no-GPU native PHP path.

## Dependency Closure

No new support component is needed. The slice reuses `TableRecognizer`, `TableFormatter`, and `SuppliedDocumentConverter`; no GPU/model runner, OCR worker, external PDF tool, or network service is required.

## Next

Continue with non-overlapping native markerPDF parser/converter behavior: searchable-PDF extraction, page geometry, fonts/CMaps, xref repair, annotations/forms/security preflight, image/filter metadata, or supplied-boundary table/equation handoffs.
