# Table Crop Polygon Stale Bbox Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T201042Z`
Base: `28a3318b8df99d6bd1d9002362d2936df58d9351`

## Source truth

- Upstream markerPDF crops rendered page images for table recognition from layout table geometry before tabled assignment.
- The native no-GPU boundary receives supplied layout and table-recognition records instead of running Surya, OCR, tabled models, Python, or external PDF tools.
- Previous markerPDF table geometry slices established that serialized polygon geometry must survive supplied PHP array boundaries before crop-local row/column/cell assignment.

## Implemented behavior

- `LayoutAnnotator` now treats `Table` layout polygons as the table region when a supplied layout record also carries a stale `bbox`.
- `TableFormatter` now uses the table polygon for table crop planning before falling back to the generic bbox reader.
- `TableRecognizer` now uses a supplied table crop polygon before stale top-level `bbox` values when localizing page-image rows, columns, and cells to table-crop coordinates.
- Generic row/cell bbox precedence remains unchanged; the precedence change is scoped to table crop-region selection.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCropPolygonStaleBboxBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses crop polygon instead of stale layout bbox before table crop planning
FAIL uses saved table crop polygon instead of stale sidecar bbox before localization
FAIL surfaces crop polygon stale-bbox boundary through supplied WordPress conversion

1 test files, 6 assertions, 3 failures
```

Green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCropPolygonStaleBboxBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses crop polygon instead of stale layout bbox before table crop planning
PASS uses saved table crop polygon instead of stale sidecar bbox before localization
PASS surfaces crop polygon stale-bbox boundary through supplied WordPress conversion

1 test files, 33 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-table-crop-polygon-stale-bbox-boundary-currentbase.php
```

The smoke emitted `table_plan_bboxes=[[72,150,312,230]]`, `coordinate_status=translated_to_table_crop`, `assigned_table_texts=[Feature,Status,Images,Ready]`, `inserted_tables=1`, and `executes_python_or_models=false`.

## Dependency closure

No new support component is needed. The slice reuses existing native layout, table crop planning, and supplied recognition helpers.

## Non-overlap

This patch does not repeat accepted OCR polygon stale-bbox handling, polygon alias normalization, serialized polygon support, mixed coordinate-space localization, image-bbox-relative localization, crop/band clipping, table span-grid, or row/column/cell bbox precedence work. It covers only stale top-level table crop bboxes losing to valid table crop polygons at the layout, crop-plan, and recognition-localization boundaries.
