# Table Serialized Polygon Geometry Boundary

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T102904Z`

Base accepted HEAD: `e1dba0fa765ac4299f486957faf26c471e838377`

## Source Truth

Upstream markerPDF table flow crops table images, then `tabled`/Surya-style geometry flows through layout table boxes, detector cells, and OCR `TextLine` polygons before Markdown table formatting. The no-GPU PHP boundary cannot run live Surya/tabled models, but supplied geometry must preserve equivalent serialized coordinates before WordPress table import.

This slice maps a serialization edge for the native supplied-boundary handoff:

- four point dictionaries with `x`/`y` numeric fields are accepted as polygons;
- eight-value flattened coordinate arrays are accepted as polygons;
- polygon geometry still wins over stale `bbox` values during OCR text assignment;
- layout table polygons in the same serialized shapes are accepted before crop planning.

## Changes

- `TableRecognizer::polygonBbox()` now accepts serialized polygon point dictionaries and flat coordinate arrays before falling back to stale bbox values.
- `TableFormatter::polygonBbox()` now accepts the same serialized polygon shapes for layout crop planning.
- `TableGeometrySerializedPolygonBoundaryCurrentBaseTest.php` locks direct OCR assignment, layout crop planning, and supplied WordPress conversion.
- `wordpress-table-serialized-polygon-boundary-currentbase.php` emits a WordPress import smoke showing the table block, assigned text order, table plan bbox, and no Python/model/external tool execution.

## Verification

Red probe before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySerializedPolygonBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes serialized polygon point dictionaries and flat coordinate arrays before OCR assignment
FAIL normalizes serialized table layout polygons before crop planning
FAIL surfaces serialized polygon table geometry through supplied WordPress conversion
1 test files, 4 assertions, 3 failures
```

Focused run after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySerializedPolygonBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes serialized polygon point dictionaries and flat coordinate arrays before OCR assignment
PASS normalizes serialized table layout polygons before crop planning
PASS surfaces serialized polygon table geometry through supplied WordPress conversion
1 test files, 20 assertions, 0 failures
```

Additional verification is recorded in the worker final response.

Table geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*.php lanes/markerpdf/tests/TableRecognizerTest.php
Focused test run: 16 selected test files (root lock skipped)
16 test files, 1003 assertions, 0 failures
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/src/TableFormatter.php
No syntax errors detected in lanes/markerpdf/src/TableFormatter.php

php -l lanes/markerpdf/tests/TableGeometrySerializedPolygonBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometrySerializedPolygonBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-serialized-polygon-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-serialized-polygon-boundary-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-serialized-polygon-boundary-currentbase.php
polygon_assignment_preserved=true
stale_bbox_assignment_excluded=true
excluded_stale_pdftext_table_line=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Other checks:

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json")); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied-document converter, table layout crop planner, table recognizer, table formatter, and WordPress smoke path. Live OCR, Surya/tabled/Torch model execution, pypdfium/PDFium raster rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted pair-array OCR polygons, stale-bbox polygon precedence for pair arrays, normalized 1000-unit table coordinates, named bbox fields, reversed bbox endpoints, detector crop filtering, assigned-cell crop filtering, band ordering, record coordinate-space translation, or page-image table bbox extent derivation. The bounded new behavior is only serialized polygon dictionaries and flattened coordinate arrays before table crop planning and OCR assignment.
