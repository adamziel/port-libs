# Table Layout Geometry Boundary Current Base

- Slice: `markerpdf-table-geometry-boundary-current-base-20260605T002907Z`
- Accepted base: `810d0706bf9e20b666c6562cd776779e2c68b0d5`
- Source truth: upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/tables/table.py::get_table_boxes`, plus the locked tabled/Surya `Bbox` contract where endpoints may arrive through serialized bbox, named fields, numeric strings, or polygon corners before Marker's crop/format handoff.

## Implementation

- `TableFormatter` now canonicalizes supplied layout Table geometry before crop planning. It accepts nested `bbox`, top-level `x1/y1/x2/y2`, `x_start/y_start/x_end/y_end`, `left/top/right/bottom`, numeric strings, and four-point polygons, then normalizes endpoints to `[minX, minY, maxX, maxY]`.
- `LayoutAnnotator` uses the same canonical geometry parser for supplied layout boxes, so a non-standard serialized Table bbox can label the pdftext table block before `formatTables()` replaces stale text with recognized Markdown.
- The slice stays in the current no-GPU scope. It consumes supplied layout/table-recognition artifacts only and does not execute Surya, tabled, OCR, pypdfium, PIL, Python model workers, or external PDF tools.

## Red/Green Evidence

- Red-first focused gate before implementation: `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php` failed with `table_counts` `[0]` instead of `[3]`, and the supplied conversion path raised `Recognized table and image size counts must match.`
- After the formatter crop parser but before layout annotation/matching cleanup, the focused gate still failed because the conversion path did not insert the table Markdown.
- Current focused gate: `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php` passed with `1 test files, 16 assertions, 0 failures`.
- Adjacent table/layout supplied-boundary gate: `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with `8 test files, 1159 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-table-layout-geometry-boundary-currentbase.php` passed and emitted canonical `table_plan_bboxes=[[72,150,312,230]]`, `table_crop_size={"width":240,"height":80}`, `inserted_tables=1`, `matched_table_block_indexes=[1]`, and `excluded_stale_pdftext_table_line=true`.
- PHP syntax checks passed for `TableFormatter.php`, `LayoutAnnotator.php`, `TableGeometryLayoutBoundaryCurrentBaseTest.php`, and `wordpress-table-layout-geometry-boundary-currentbase.php`.
- `git diff --check -- lanes/markerpdf` passed. Full root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the existing native supplied-boundary converter, layout annotator, table crop planner, table recognizer handoff, and Markdown table formatter. Full upstream live table detection/recognition, OCR, Surya layout, and benchmark model parity remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat prior table cell/row-column assignment, named bbox row/column/cell geometry, numeric-string row/column/cell geometry, polygon OCR text assignment, or DCT/filter stream-boundary slices. It specifically owns the supplied layout Table crop/annotation boundary before table replacement.
