# markerpdf source-bbox table geometry boundary current-base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T231931Z`

Base accepted HEAD: `8a33c3f884196ff9be057d69594e60f28ad9c5a1`

## Behavior

Saved supplied table sidecars can carry geometry only as `source_bbox` plus `source_coordinate_space`, especially when an adapter is replaying previously reviewed table geometry. The native no-GPU boundary now treats `source_bbox` and `source_page_image_bbox` as fallback geometry inputs only when primary `bbox`, named bbox fields, and polygon aliases are absent.

This keeps explicit geometry authoritative, localizes source-bbox-only rows/columns/cells/conflict candidates from page-image coordinates into the table crop, filters off-crop assigned cells before Markdown, and preserves `source_coordinate_source=source_bbox` in WordPress review metadata.

## Evidence

Red-first probe before source edit:

`TableRecognizer::formatRecognizedTables()` threw `InvalidArgumentException: Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.` for a table whose rows/cols/cells only carried `source_bbox` plus `source_coordinate_space=page_image`.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySourceBboxBoundaryCurrentBaseTest.php`

Result: `1 test files, 36 assertions, 0 failures`.

Family check:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php`

Result: `29 test files, 1557 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-table-source-bbox-boundary-currentbase.php`

Result: emitted JSON with `coordinate_status=translated_to_table_crop`, `cell_source_coordinate_source=source_bbox`, `source_bbox_geometry_preserved=true`, `offcrop_source_bbox_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/diff checks:

`php -l lanes/markerpdf/src/TableRecognizer.php`

`php -l lanes/markerpdf/tests/TableGeometrySourceBboxBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-table-source-bbox-boundary-currentbase.php`

All reported no syntax errors.

## Dependency Closure

No new support component is needed. This reuses the existing native supplied table-recognition/formatting path and does not run Surya, tabled models, OCR, Python, GPU/model code, or external PDF tools.

## Non-Overlap

This does not repeat polygon aliases, stale bbox-vs-polygon precedence, normalized/page-image coordinate spaces, image-bbox-relative geometry, assigned-band filtering, or encrypted PageLabels preview behavior. It only adds the missing serialized source-bbox fallback shape at the table geometry adapter boundary.
