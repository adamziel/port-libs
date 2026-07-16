# markerPDF table corner-point boundary current-base slice

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260606T082248Z`

Accepted base: `35aa729dcd980a849ecfe8cf124c4f3b8e9ef712`

## Source Truth

- The locked markerPDF table handoff remains a no-GPU supplied-boundary path: upstream tabled saves table results with table `bbox`, `image_bbox`, row/column/cell bboxes, and assigned row/column ids, while this lane consumes equivalent supplied PHP arrays without running Surya, tabled, OCR, Torch, or Python model workers.
- Previous table geometry slices already covered four-value bboxes, named scalar bboxes, wrapped bbox aliases, four-corner polygons, normalized 1000-unit geometry, page-image coordinates, and page-result image bboxes. This slice owns the remaining sidecar boundary where an adapter serializes a rectangular bbox as two named corner points.
- Implemented point-pair aliases are bounded to rectangle corners: `top_left` + `bottom_right`, `upper_left` + `lower_right`, `top_right` + `bottom_left`, `upper_right` + `lower_left`, `tl` + `br`, and `tr` + `bl`.

## Change

- `LayoutAnnotator` now sanitizes supplied layout `image_bbox` and `Table` bboxes from two-corner point records before table crop planning.
- `TableFormatter` now treats the same two-corner point records as table layout crop bboxes.
- `TableRecognizer` now accepts the same records for table crop bboxes, rows, columns, cells, and OCR grid-border conflict candidates, preserving `source_bbox`, `source_coordinate_source`, and `source_endpoint_order_normalized` metadata through page-image to table-crop localization.
- Added a focused current-base test and a WordPress smoke example proving stale PDF table text and off-crop supplied cells stay excluded.

## Red-First Evidence

Before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCornerPointBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes two-corner point bboxes before crop localization and cell assignment
Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.
FAIL normalizes two-corner point bboxes before table layout crop planning
Expected: array (0 => 2,)
Actual: array (0 => 0,)
FAIL surfaces two-corner point table geometry through supplied WordPress conversion
Recognized table and image size counts must match.

1 test files, 1 assertions, 3 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCornerPointBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes two-corner point bboxes before crop localization and cell assignment
PASS normalizes two-corner point bboxes before table layout crop planning
PASS surfaces two-corner point table geometry through supplied WordPress conversion

1 test files, 44 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 37 selected test files (root lock skipped)
...
37 test files, 1521 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-table-corner-point-boundary-currentbase.php
```

Smoke emitted `coordinate_review_status=translated_to_table_crop`, `first_cell_source_coordinate_source=bbox_top_left_bottom_right_points`, `first_cell_endpoint_order_normalized=true`, `offcrop_cells_filtered_from_assignment=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is required. The patch reuses the existing native PHP supplied-boundary parser/converter path and does not run model/OCR/GPU services, Python tabled/Surya, external PDF tools, or online services.

## Non-Overlap

This does not repeat accepted table geometry bboxes, named-field aliases, wrapped aliases, four-corner polygon aliases, normalized page-image/table coordinates, nested crop metadata, or AcroForm annotation work. It only adds the two named corner-point rectangle serialization shape across the existing table layout, crop planning, recognition, and WordPress conversion handoff.
