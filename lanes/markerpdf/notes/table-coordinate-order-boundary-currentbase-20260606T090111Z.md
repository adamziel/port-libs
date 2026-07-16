# markerpdf table coordinate-order boundary current-base

Slice: `markerpdf-table-geometry-boundary-current-base-20260606T090111Z`
Base: `99cf2de666d876a3801263f5952cbba286757315`

## Source truth

- Upstream markerPDF crops high-resolution page images per table before tabled assignment and Markdown table formatting.
- The no-GPU markerPDF scope for this lane accepts supplied table-recognition sidecars instead of running Surya, OCR, tabled/Torch models, Python workers, or external PDF tools.
- Some saved sidecars can carry explicit bbox coordinate-order metadata such as `bbox_order: x1_x2_y1_y2`; those four numbers must be reordered before table-crop localization and assignment.

## Implementation

- `TableRecognizer` now honors explicit bbox coordinate order metadata on table, row, column, and cell records before canonicalizing endpoints.
- Supported order labels include xyxy, xxyy, yxyx, yyxx and common named aliases such as `x1_x2_y1_y2`, `left_right_top_bottom`, and `top_bottom_left_right`.
- Field-level table order metadata can be inherited by row/column/cell records that omit their own order declaration.
- After a page-image record is translated into table-crop space, its bbox order metadata is reset to `xyxy` so later crop filtering does not reinterpret already-localized rectangles.
- Invalid top-level `bbox` values no longer block the existing wrapped/source bbox fallbacks.

## Evidence

Red pre-edit probe:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCoordinateOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL honors explicit x1 x2 y1 y2 coordinate order before table crop localization
Expected 'bbox_array_x1_x2_y1_y2_order', Actual 'bbox_array'
FAIL surfaces explicit coordinate order table geometry through supplied WordPress conversion
1 test files, 5 assertions, 2 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCoordinateOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS honors explicit x1 x2 y1 y2 coordinate order before table crop localization
PASS surfaces explicit coordinate order table geometry through supplied WordPress conversion
1 test files, 42 assertions, 0 failures
```

Table-geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 38 selected test files (root lock skipped)
38 test files, 1563 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-coordinate-order-boundary-currentbase.php
scenario=wordpress-table-coordinate-order-boundary-currentbase
coordinate_review_status=translated_to_table_crop
render_source_coordinate_source=bbox_array_x1_x2_y1_y2_order
offcrop_cells_filtered_from_assignment=true
excluded_stale_pdftext_table_line=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Hygiene:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/tests/TableGeometryCoordinateOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryCoordinateOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-coordinate-order-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-coordinate-order-boundary-currentbase.php

php -r '$json = file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status JSON OK\n";'
lane-status JSON OK

git diff --check -- lanes/markerpdf
no output
```

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is required. This reuses the existing native supplied-boundary table recognizer/converter path and keeps all model/OCR work outside the current no-GPU scope.

## Non-overlap

This patch does not repeat accepted table crop polygon, assigned crop/band filtering, endpoint alias, normalized page-image, source-bbox fallback, wrapped-alias, image-bbox-relative, or detector-source geometry slices. It only adds explicit bbox coordinate-order handling before current table-crop localization.
