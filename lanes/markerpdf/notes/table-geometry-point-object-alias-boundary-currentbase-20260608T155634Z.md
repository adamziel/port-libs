# Table Geometry Point-Object Alias Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T155634Z`

Accepted base: `88918a69038ea1f5dab678b0be595fb89790e664`

## Source Truth

- Upstream markerPDF table handoff consumes supplied layout/table-recognition geometry as deterministic bbox/cell metadata before Markdown table replacement.
- This no-GPU markerPDF lane keeps live Surya/tabled/OCR execution out of scope; supplied boundary JSON remains the native PHP contract for table crop, row, column, cell, and WordPress replacement review.

## Behavior

- Named point-coordinate objects are now interpreted by key names before falling back to array insertion order.
- Covered aliases: `x/y`, `x0/y0`, `x1/y1`, `xmin/ymin`, `xmax/ymax`, `x_min/y_min`, `x_max/y_max`, `x_start/y_start`, `x_end/y_end`, `start_x/start_y`, `end_x/end_y`, `left/top`, `right/bottom`, `right/top`, `left/bottom`, and center-style point names.
- The same key-driven point parsing is applied in `LayoutAnnotator`, `TableFormatter`, and `TableRecognizer`, so supplied layout crops and recognized table rows/columns/cells share the same boundary semantics.

## Evidence

- Red-first:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPointObjectAliasBoundaryCurrentBaseTest.php`
  - Failed before the fix with table bbox parsed as `[150, 72, 230, 312]` instead of `[72, 150, 312, 230]`, and WordPress conversion reported mismatched recognized table/image-size counts.
- Focused after fix:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPointObjectAliasBoundaryCurrentBaseTest.php` => `1 test files, 52 assertions, 0 failures`
- Adjacent alias gate after fix:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCornerPointBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPointPairAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryEndpointAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPointObjectAliasBoundaryCurrentBaseTest.php` => `4 test files, 193 assertions, 0 failures`
- Focused table geometry family after fix:
  - `php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php` => `69 test files, 2689 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-table-point-object-alias-boundary-currentbase.php` exits 0 with `coordinate_review_status=translated_to_table_crop`, `first_cell_source_bbox=[82,155,162,170]`, `active_cell_count=4`, `excluded_cell_count=2`, `offcrop_cells_filtered_from_assignment=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP supplied-boundary table pipeline and does not run Python, CUDA, OCR, models, raster rendering, multiprocessing, or external PDF tools.

## Next

Continue with a non-overlapping native markerPDF table/equation boundary or searchable-PDF parser slice: e.g. remaining table coordinate-space edge cases, CMaps/fonts, stream filters, xref repair, annotations/forms, image/filter metadata, or equation supplied-boundary handoff review.
