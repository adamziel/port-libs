# markerPDF wrapped Bbox table geometry boundary

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260606T023427Z`

Accepted base: `7e95b10d11f5767b21764022fd15eea3308c3829`

## Source truth

- Upstream `sddai/markerPDF` delegates table formatting to tabled after table crop localization.
- Local upstream tabled source: `/tmp/markerpdf-tabled-src-current/tabled_pdf-0.1.4/tabled/schema.py` defines `SpanTableCell(Bbox)` and `ExtractPageResult.bboxes/image_bboxes: List[Bbox]`.
- Local upstream tabled extract path: `/tmp/markerpdf-upstream-src/tabled-0.1.4/extract.py` serializes cells/rows/cols with `.model_dump()` and per-table bboxes from `Bbox.bbox`.
- The native no-GPU boundary is therefore to accept supplied table geometry that preserves Bbox-shaped wrapper dictionaries, without invoking Surya, tabled models, OCR, Python, or external PDF tools.

## Behavior

`TableRecognizer` now unwraps supplied `bbox` and `box` dictionaries that carry a nested bbox list before:

- resolving table crop and page image bboxes;
- translating page-image rows, columns, cells, OCR conflicts, and candidate-cell boxes into table-crop coordinates;
- preserving source review labels as `bbox.bbox_array` or `box.bbox_array`;
- detecting reversed endpoint order through the same raw coordinate path.

This is additive to existing direct bbox arrays, named bbox fields, source-bbox fallback, polygon aliases, page-result bboxes, and nested crop metadata.

## Red-first evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryWrappedBboxBoundaryCurrentBaseTest.php`

failed with:

- `Table image sizes must include positive width and height.`
- `Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.`

## Verification

- `php -l lanes/markerpdf/src/TableRecognizer.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/TableGeometryWrappedBboxBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-wrapped-bbox-boundary-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryWrappedBboxBoundaryCurrentBaseTest.php` => 1 test files, 46 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryWrappedBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySourceBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php` => 3 test files, 138 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCropPolygonStaleBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPolygonAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySerializedPolygonBoundaryCurrentBaseTest.php` => 3 test files, 82 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-table-wrapped-bbox-boundary-currentbase.php` => reports `wrapped_bbox_geometry_unwrapped=true`, `offcrop_wrapped_bbox_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status delta

- `phpPass`: 2340 -> 2342.
- `wordpressScenarios`: 2006 -> 2007.
- Added focused test file: `TableGeometryWrappedBboxBoundaryCurrentBaseTest.php`.
- Added smoke example: `wordpress-table-wrapped-bbox-boundary-currentbase.php`.

## Non-overlap

This slice does not repeat prior table geometry work for named bbox aliases, numeric strings, reversed endpoint direct arrays, extent/center boxes, polygon aliases, serialized polygons, nested crop records, source-bbox fallback fields, page-result flattening, detector crop/source boundaries, conflict-grid review, or table band ordering.

## Dependency closure

No new support component is needed. The patch reuses the native `TableRecognizer` and `SuppliedDocumentConverter` supplied-boundary path. Live OCR, Surya, tabled/Texify/Torch execution, Python model workers, Streamlit/FastAPI model services, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
