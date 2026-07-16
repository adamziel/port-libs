# Table Generic Crop Bbox Coordinate Space Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260606T182025Z`

Accepted base: `3dbd03ad2606ba7aa558ebd5c4e8b990b6b82f2a`

## Source Truth

- Upstream `sddai/markerPDF` pinned `marker/tables/table.py` renders a high-resolution page, rescales layout table boxes into that image, crops each table image, then sends those crops through tabled assignment and Markdown formatting.
- Official `tabled` documentation says saved `results.json` table records carry `cells`, `rows`, `cols`, `image_bbox`, and `bbox`; the table `bbox` is the crop boundary within the image bbox.
- This slice stays in the current no-GPU markerPDF scope: it uses supplied table-recognition geometry and does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, Torch/CUDA, or external PDF tools.

## Implementation

- `TableRecognizer` now lets top-level table crop bbox candidates inherit generic `coordinate_space`/`geometry_space` metadata when no bbox-specific key is present.
- A top-level table `bbox` declared by generic `coordinate_space=normalized_page_image` is now unnormalized against `image_bbox` before rows, columns, cells, and OCR conflict geometry are translated into table-crop coordinates.
- The behavior also applies to table crop aliases handled by the same candidate path, while preserving explicit `table_bbox_coordinate_space` and existing table-plan crop boundaries.

## Evidence

Red-first focused command before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryGenericCropBboxCoordinateSpaceBoundaryCurrentBaseTest.php`

Result: `1 test files / 14 assertions / 2 failures`; the crop bbox source coordinate space was missing, so the generic normalized page-image table `bbox` was not review-visible/localized at the crop-bbox boundary.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryGenericCropBboxCoordinateSpaceBoundaryCurrentBaseTest.php`

Result: `1 test files / 55 assertions / 0 failures`.

Table geometry family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php`

Result: `42 test files / 1715 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-generic-crop-bbox-coordinate-space-currentbase.php`

Result: exits 0 with `generic_crop_bbox_localized=true`, `wordpress_table_rendered=true`, `stale_generic_cells_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat explicit normalized `table_bbox_coordinate_space`, normalized page-image rows/cells, crop polygon, coordinate-order, source-bbox fallback, image-bbox-relative, detector-source, or assigned crop/band filtering coverage. The bounded behavior is generic table-level coordinate-space inheritance for top-level crop bbox candidates.

## Dependency Closure

No new support component is needed. The slice reuses native table geometry normalization, supplied table recognition, table formatting, and WordPress conversion metadata. Live OCR, Surya/Texify/Torch, tabled model inference, and exact upstream model parity remain intentionally out of scope under the markerPDF no-GPU directive.

## Next Task

Continue with non-overlapping native searchable-PDF parser or supplied-boundary table/equation behavior, especially cases where upstream sidecars declare coordinate or review metadata at an envelope level rather than on each nested row/cell record.
