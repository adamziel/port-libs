# markerpdf table geometry wrapped coordinate-order boundary current-base

Slice: `markerpdf-table-geometry-boundary-current-base-20260607T135621Z`

Accepted base: `bcde00c99f7f103f12aeb62e041494db8ca298a6`

## Scope

This patch keeps markerPDF in the no-GPU native/supplied-boundary scope. It covers tabled-style `Bbox.model_dump` sidecars where the geometry is wrapped as `{"bbox": [...]}` and the raw list carries nested `bbox_order: x1_x2_y1_y2`. The port already handled plain wrapped `Bbox` arrays and direct ordered arrays, but ignored the nested order when both appeared together.

## Source Truth

Upstream markerPDF delegates supplied table crops/cells to tabled. Tabled-side handoffs are geometry sidecars, and `Bbox` objects can be serialized as wrapper dictionaries around raw coordinate arrays. For no-GPU markerPDF conversion, the native PHP importer must normalize those supplied boundaries before table-crop localization and WordPress Markdown output.

## Evidence

Red-first focused run before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryWrappedCoordinateOrderBoundaryCurrentBaseTest.php`

Result: `1 test files, 6 assertions, 2 failures`; the direct recognizer reported `bbox.bbox_array` instead of `bbox.bbox_array_x1_x2_y1_y2_order`, and WordPress conversion emitted a one-column stale table.

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryWrappedCoordinateOrderBoundaryCurrentBaseTest.php`

Result: `1 test files, 45 assertions, 0 failures`.

Regression family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Table.*(Geometry|Coordinate|Bbox|Boundary|Assigned|RowsCols|Wrapped|Mixed|PageResult|Crop|Conflict).*CurrentBaseTest\.php$' | sort)`

Result: `48 test files, 1944 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-wrapped-coordinate-order-boundary-currentbase.php`

Result: exits `0` with `direct_table_bbox_source=bbox.bbox_array_x1_x2_y1_y2_order`, `coordinate_status=translated_to_table_crop`, `assigned_table_texts=[Feature,Status,Images,Ready]`, `wrapped_coordinate_order_normalized=true`, `offcrop_wrapped_coordinate_order_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This avoids the accepted table rows/cols, mixed assigned-cell, assigned-order, direct coordinate-order, and plain wrapped-Bbox slices. It only adds nested coordinate-order handling for wrapper dictionaries before table-crop localization.

## Dependency Closure

No new dependency or support component is needed. The behavior reuses the existing native supplied-boundary table recognizer, `SuppliedDocumentConverter`, and focused PHP fixtures. It does not execute Python, OCR, Surya, tabled models, Torch/CUDA, image raster decoding, or external PDF tools.

## Next Task

Continue markerPDF table-boundary work with another non-overlapping supplied-boundary handoff, such as nested table/equation artifact provenance or parser-level page geometry interactions, while preserving no-GPU constraints.
