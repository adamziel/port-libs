# Table Polygon Alias Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T150948Z`
Base: `3eb8ddf07c4374f978284008c7825054bcd6029c`

## Source truth

- Upstream markerPDF crops rendered page images for tables before handing rows, columns, cells, and OCR text to `tabled.assignment.assign_rows_columns`.
- Locked `tabled-pdf==0.1.4` represents row/column/cell geometry through `Bbox` and `SpanTableCell` with `row_ids` and `col_ids`.
- This native PHP slice does not run Surya, OCR, tabled models, Python, or external PDF tools. It keeps the supplied-boundary contract by normalizing serialized four-corner table geometry aliases into the same bbox shape before assignment.

## Implemented behavior

- `TableRecognizer` now accepts `points`, `vertices`, `quad`, `quadrilateral`, and `quadrilateral_points` as polygon aliases for supplied table crop, row, column, cell, OCR grid conflict, and candidate-cell geometry.
- Explicit `bbox` remains authoritative when present.
- Source review metadata records alias origins such as `polygon_points`, `polygon_vertices`, `polygon_quad`, and `polygon_quadrilateral`.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPolygonAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes supplied table polygon aliases before crop localization and cell assignment
Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon.
FAIL surfaces supplied polygon alias table geometry through WordPress conversion
Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon.

1 test files, 0 assertions, 2 failures
```

Green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPolygonAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes supplied table polygon aliases before crop localization and cell assignment
PASS surfaces supplied polygon alias table geometry through WordPress conversion

1 test files, 29 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-table-polygon-alias-boundary-currentbase.php
```

The smoke emitted `coordinate_status=translated_to_table_crop`, `assigned_table_texts=[Feature,Status,Images,Ready]`, alias source labels for points/vertices/quad/quadrilateral, `active_cell_count=4`, `excluded_cell_count=2`, and no Python/model/external PDF tool execution.

## Dependency closure

No new support component is needed. The existing native `TableRecognizer` polygon-to-bbox helper is reused and extended at the supplied-record boundary.

## Non-overlap

This patch does not repeat accepted table crop clipping, normalized page-image conversion, image-bbox-relative geometry, serialized `polygon` handling, mixed coordinate-space localization, detector crop/cell boundary, or row/column span behavior. It covers only equivalent four-corner alias keys on supplied table records.
