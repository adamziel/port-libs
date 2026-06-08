# Table Nested Original Crop Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T171154Z`

Base accepted HEAD: `c460ba902e14e68048034928a3c5a02d7764d30d`

## Source Truth

- Upstream markerPDF crops rendered page images before handing each table crop to tabled recognition, then formats assigned rows, columns, and cells as Markdown.
- Tabled/adapter review sidecars can preserve pre-localized geometry as source/original fields rather than rewriting every row, column, cell, or crop wrapper into table-crop coordinates.
- This slice stays inside markerPDF's no-GPU scope: it uses supplied table-recognition geometry only and does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, Torch/CUDA, or external PDF tools.

## Red-First Evidence

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedOriginalCropBoundaryCurrentBaseTest.php`

Initial result before the source change:

`1 test files / 3 assertions / 2 failures`

The direct recognizer path returned no `table_recognition_coordinate_space_boundary` review for `table_image.original_bbox`, and the supplied WordPress conversion dropped the table Markdown because normalized `original_bbox` values were treated as table-crop coordinates.

## Implementation

- `TableRecognizer::nestedTableCropBboxCandidate()` now considers all source/original geometry fallback keys for nested crop containers, so `table_image.original_bbox` can define the crop before generic nested `bbox` wrappers.
- Source fallback coordinate-space resolution now accepts `original_coordinate_space`, `original_geometry_space`, `original_bbox_coordinate_space`, and `original_bbox_geometry_space` alongside existing `source_*` keys.
- Review propagation normalizes original-geometry aliases into the existing `source_coordinate_space` review field while retaining `source_coordinate_source=original_bbox`.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedOriginalCropBoundaryCurrentBaseTest.php`

Result: `1 test files / 52 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-nested-original-crop-boundary-currentbase.php`

Result: exits 0 with `nested_original_crop_unwrapped=true`, `wordpress_table_rendered=true`, `stale_nested_original_cells_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This completes the executable current-base `original_bbox` branch that adjacent source-crop prose documented but did not cover with a passing direct fixture. It does not repeat the already-green nested `source_bbox` crop path, explicit field-specific table-bbox coordinate-space, page-result table image `highres_bbox`, page-result `table_bboxes`, generic crop coordinate-space inheritance, duplicate span IDs, crop polygon precedence, or normalized page-image record geometry. The bounded behavior is specifically nested and record-level `original_bbox` plus `original_coordinate_space` sidecar geometry.

## Dependency Closure

No new support component is needed. The patch reuses native PHP table geometry normalization, supplied table-recognition formatting, and the existing WordPress conversion metadata path. Live OCR, Surya/Texify/Torch, tabled model execution, page-pixel visual recognition, and exact upstream model parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue with non-overlapping native searchable-PDF parser or supplied-boundary behavior, especially remaining table/equation handoff envelopes where upstream sidecars attach coordinate metadata to wrapper objects rather than each nested geometry record.
