# Table Center-Point Geometry Boundary Current Base

## Source Truth

- Local upstream tabled source: `/tmp/markerpdf-tabled-src-current/tabled_pdf-0.1.4/tabled/schema.py` defines `SpanTableCell(Bbox)` and exposes assignment geometry through Surya `Bbox` helpers.
- Local upstream tabled source: `/tmp/markerpdf-upstream-src/tabled-0.1.4/tabled/assignment.py` uses `Bbox.center`, `width`, and `height` helper behavior when assigning unassigned cells and detecting rotated row/column axes.
- This no-GPU slice keeps that supplied-boundary contract native: serialized review/sidecar rows with `center` plus `width`/`height`, `extent`, or `size` are converted to endpoint bboxes before table-crop localization, assignment filtering, and WordPress review metadata.

## Implementation

- `TableRecognizer` now accepts center-point bbox aliases:
  - `center: [x, y]` or `center: {x, y}` with `width` and `height`.
  - `center` with `extent: [width, height]`, `extent: {width, height}`, or `extent: {w, h}`.
  - `center` with `size` in the same list or dictionary forms.
- The new source-shape review strings are `bbox_center_width_height_fields`, `bbox_center_extent_fields`, and `bbox_center_size_fields`.
- Crop-size derivation also works when a saved table result omits explicit rendered-table image size and its top-level `bbox` is stored in center/extent form.

## Focused Evidence

- Red-first probe before the change:
  - `TableRecognizer::formatRecognizedTables()` with center/extent `bbox`, rows, columns, and cells failed with `InvalidArgumentException: Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.`
  - The no-size saved sidecar path failed earlier with `InvalidArgumentException: Table image sizes must include positive width and height.`
- `php -l lanes/markerpdf/src/TableRecognizer.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/TableGeometryCenterPointBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-center-point-boundary-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCenterPointBoundaryCurrentBaseTest.php` => `1 test files, 55 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCenterPointBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCenterExtentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryWrappedBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryExtentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySourceReviewBoundaryCurrentBaseTest.php` => `5 test files, 234 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php` => `31 test files, 1264 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-center-point-boundary-currentbase.php` reports active `Feature`, `Status`, `Images`, and `Ready` table cells; center/extent/width/height/size source-shape metadata; stale pdftext line exclusion; stale off-crop supplied-cell exclusion; `executes_python_or_models=false`; and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `TableRecognizer`, `TableFormatter`, `SuppliedDocumentConverter`, table-crop localization, assigned-cell crop/band filtering, span-grid review metadata, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, tabled model inference, PDFium/PIL rendering, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted endpoint bbox arrays, named endpoint aliases, `x/y/width/height`, `cx/cy/width/height`, `center_x/center_y/width/height`, wrapped `Bbox.model_dump()` bboxes, polygon aliases, source-bbox fallback, normalized page/table coordinates, image-bbox-relative localization, nested crop polygons, tabled page-result flattening, assigned crop/band filtering, or detector-cell crop filtering. The new behavior is specifically center point objects/lists with extent or size dimensions before existing crop-local table geometry boundaries.
