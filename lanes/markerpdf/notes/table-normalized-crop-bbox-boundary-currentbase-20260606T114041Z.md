# Table Normalized Crop Bbox Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260606T114041Z`

Accepted base: `fd4302f81958dd876e69577b59b33a8b2822f137`

## Source Truth

- Upstream `marker/tables/table.py` derives table boxes on the rendered page image and crops that table image before `tabled.assignment.assign_rows_columns`.
- Upstream `tabled.schema.ExtractPageResult` carries table `bboxes` and page `image_bboxes`, while assignment rows, columns, and cells are local to the cropped table image after the crop handoff.
- This markerPDF slice stays in the no-GPU scope: it consumes supplied table-recognition geometry and does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, or external PDF tools.

## Implementation

- `TableRecognizer` now recognizes explicitly declared normalized page-image table crop bboxes such as `table_bbox_coordinate_space=normalized_page_image`.
- The crop bbox is unnormalized against `image_bbox`/`page_image_bbox`/`rendered_image_bbox` before row, column, cell, and OCR conflict geometry are translated into table-crop coordinates.
- Coordinate review metadata now records the original normalized crop bbox, crop bbox coordinate space, and page-image normalization size so WordPress review can distinguish this from already-local table geometry.

## Evidence

Red-first focused command before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNormalizedCropBboxBoundaryCurrentBaseTest.php`

Result: `1 test files / 5 assertions / 2 failures`; the table crop bbox stayed normalized (`117.6,189.4,509.8,290.4`) instead of resolving to page bbox `72,150,312,230`, and the supplied WordPress table output did not emit the expected `Feature | Status` table.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNormalizedCropBboxBoundaryCurrentBaseTest.php`

Result: `1 test files / 45 assertions / 0 failures`.

Adjacent coordinate-boundary command:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNormalizedCropBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNormalizedPageImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryImageBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryMixedCoordinateBoundaryCurrentBaseTest.php`

Result: `4 test files / 179 assertions / 0 failures`.

Full current-base table-geometry family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php`

Result: `40 test files / 1636 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-normalized-crop-bbox-boundary-currentbase.php`

Result: exits 0 with `normalized_crop_bbox_localized=true`, `stale_normalized_cells_filtered=true`, `normalized_cell_count=6`, `normalized_conflict_count=1`, and `executes_python_or_models=false`.

Syntax and diff hygiene:

- `php -l lanes/markerpdf/src/TableRecognizer.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/TableGeometryNormalizedCropBboxBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-normalized-crop-bbox-boundary-currentbase.php` => no syntax errors.
- `git diff --check -- lanes/markerpdf` => clean.

## Non-Overlap

This does not repeat the existing table page-result coordinate-order slice. That prior coverage handled bbox order propagation from page-result records; this slice covers an explicit normalized page-image crop bbox boundary before table-crop localization and WordPress table assignment.

## Dependency Closure

No new support component is needed. The slice reuses the existing supplied-boundary table recognition and WordPress conversion paths. Live OCR, Surya/Texify/Torch, tabled model inference, and exact upstream model parity remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next Task

Continue with non-overlapping native markerPDF parser or supplied-boundary table behavior, especially table/equation handoffs where upstream emits review geometry in a different declared coordinate space than the WordPress conversion path consumes.
