# Table Nested Source Crop Coordinate Space Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T133818Z`

Accepted base: `c09710161ff2cdca8a8469de31dd5d314260fa0c`

## Source Truth

- Upstream `sddai/markerPDF` table conversion renders a high-resolution page, crops each table image, sends the crop through tabled assignment, then formats assigned rows/columns/cells into Markdown.
- `tabled` `ExtractPageResult` carries table crop metadata (`table_imgs`/`table_bboxes`) plus `cells` and `rows_cols`; saved review sidecars can preserve the original crop rectangle as `source_bbox` with `source_coordinate_space`.
- This slice stays in markerPDF's no-GPU scope: it uses supplied table-recognition geometry only and does not run Surya, tabled model inference, OCR, Python, PDFium, PIL, Torch/CUDA, or external PDF tools.

## Implementation

- `TableRecognizer::tableCropBboxCoordinateSpace()` now treats nested crop `source_bbox` and `original_bbox` aliases like row/cell source-bbox fallbacks by honoring `source_coordinate_space` / `original_coordinate_space` metadata on the same wrapper.
- A nested `table_image.source_bbox` declared as `normalized_page_image` is now unnormalized against `image_bbox`, then used as the page-image table crop before rows, columns, cells, and OCR conflict candidates are translated into table-crop coordinates.
- Existing explicit `table_bbox`, generic crop coordinate-space, page-result table image, and record-level source-bbox behavior remain unchanged.

## Evidence

Red-first focused command before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedSourceCropCoordinateSpaceBoundaryCurrentBaseTest.php`

Result: `1 test files / 14 assertions / 2 failures`. The direct recognizer case exposed `Expected: 'normalized_page_image' Actual: NULL` for the nested crop `source_coordinate_space`; the supplied-converter fixture also showed its layout `table_bbox` insertion boundary can mask nested crop metadata in that path, so the converter smoke now asserts preserved normalized source-record metadata while the direct recognizer case owns the strict crop-boundary assertion.

Focused command after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedSourceCropCoordinateSpaceBoundaryCurrentBaseTest.php`

Result: `1 test files / 59 assertions / 0 failures`.

Adjacent table crop/source checks:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedSourceCropCoordinateSpaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySourceBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryGenericCropBboxCoordinateSpaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultTableImageBoundaryCurrentBaseTest.php`

Result: `5 test files / 236 assertions / 0 failures`.

Table geometry family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php`

Result: `62 test files / 2437 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-nested-source-crop-coordinate-space-currentbase.php`

Result: exits 0 with `nested_source_crop_unwrapped=true`, `direct_table_bbox_source=table_image.source_bbox`, `direct_table_bbox_source_coordinate_space=normalized_page_image`, `direct_table_bbox=[72,150,312,230]`, `wordpress_table_rendered=true`, `stale_nested_source_cells_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat top-level generic crop coordinate-space inheritance, explicit field-specific table-bbox coordinate-space, page-result table image flattening, shared image-bbox coordinate order, source-bbox row/cell fallback, duplicate span IDs, crop polygon precedence, or normalized page-image record geometry. The bounded behavior is the missing coordinate-space alias on nested crop `source_bbox` / `original_bbox` wrappers.

## Dependency Closure

No new support component is needed. The patch reuses native table geometry normalization, supplied table-recognition formatting, and the existing WordPress conversion metadata path. Live OCR, Surya/Texify/Torch, tabled model execution, page-pixel visual recognition, and exact upstream model parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Next Task

Continue with non-overlapping native searchable-PDF parser or supplied-boundary behavior, especially remaining table/equation handoff envelopes where upstream sidecars attach coordinate metadata to wrapper objects rather than each nested geometry record.
