# Table Geometry Source Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T053207Z`

Accepted base: `84ab27111aed7a1f7263c1f4f4ca36b52258db2f`

## Upstream Boundary

`sddai/markerPDF` crops high-resolution page images in `marker/tables/table.py::get_table_boxes` before tabled assignment. Saved table-recognition results may still carry page-image coordinates, while tabled `SpanTableCell` assignment and Markdown formatting operate in the table-crop coordinate space.

This no-GPU slice preserves both sides of that boundary for WordPress review metadata:

- crop-local `bbox` remains authoritative for row/column assignment and Markdown table output;
- page-image `source_bbox` and `source_coordinate_space` now survive assigned-cell normalization;
- span-grid, cell-boundary, and grid-cell review rows expose source bboxes so import overlays can map table cells back to the rendered page.

## Red-First Evidence

After adding the focused assertions, the current base failed:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php`

Result: 2 failures. The direct formatter case returned `NULL` for `Feature.source_bbox`; the first WordPress fixture also used an over-wide stale line that did not meet the existing table replacement threshold and was tightened before final verification.

## Verification

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php`

Result: `1 test files, 67 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php`

Result: `11 test files, 772 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-table-page-image-geometry-boundary-currentbase.php`

Result: emitted `page_image_source_geometry_preserved=true`, crop bbox `[10,5,90,20]`, source bbox `[82,155,162,170]`, and no Python/models/external PDF tools.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP supplied-boundary table recognizer, formatter, and converter path. GPU/model OCR, live table recognition, Python, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat prior table geometry slices for forced-OCR merged cells, rotated rowspan grids, OCR polygon geometry, grid-border assignment review, visible/hidden table constraints, normalized geometry, crop clipping, or PageLabels/xref parser work. It only carries saved page-image source bboxes through assigned table review metadata after crop-local localization.
