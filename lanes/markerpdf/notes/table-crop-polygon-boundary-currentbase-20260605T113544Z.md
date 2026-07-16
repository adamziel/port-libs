# markerPDF table crop polygon boundary current base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T113544Z`
Base accepted HEAD: `651615e05fea9d010bb9bbcaa297afe05c6cf991`

## Source Truth

- Upstream markerPDF routes table crops through `marker/tables/table.py` before tabled assignment and Markdown formatting.
- Locked tabled-pdf `tabled/schema.py::SpanTableCell` extends Surya `Bbox`, and `tabled/assignment.py::assign_rows_columns()` consumes Bbox geometry to assign row and column ids.
- Surya serialized outputs commonly expose four-corner `polygon` geometry beside bbox-like records. In the native supplied-boundary path, a saved table-recognition result whose table crop arrives as a polygon must still localize page-image rows, columns, and cells into the table crop before WordPress table output.

## Implementation

- `TableRecognizer::tableCropBbox()` now accepts polygon-shaped crop operands for `table_bbox`, `table_crop_bbox`, `crop_bbox`, `highres_bbox`, `page_table_bbox`, nested `bbox`, and top-level `polygon` records.
- Existing bbox arrays, named endpoint fields, extent-shaped fields, normalized geometry, and page-image coordinate-space behavior are preserved.
- Added focused direct coverage and a supplied WordPress conversion path proving off-crop page-image cells are filtered after polygon-localized table crop translation.

## Verification

Red before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCropPolygonBoundaryCurrentBaseTest.php
FAIL uses saved table crop polygon as page-image boundary before assigned geometry
Expected: 'translated_to_table_crop'
Actual: 'missing_table_crop_bbox'
1 test files, 17 assertions, 1 failures
```

Green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCropPolygonBoundaryCurrentBaseTest.php
1 test files, 39 assertions, 0 failures
```

Adjacent table-geometry run:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*Test.php
17 test files, 665 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-table-crop-polygon-boundary-currentbase.php
```

The smoke emitted `table_crop_polygon_translated=true`, `offcrop_polygon_cells_filtered_from_assignment=true`, `stale_pdftext_table_line_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `TableRecognizer`, supplied-document converter, table formatter, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF direction.

## Non-Overlap

This does not repeat accepted tabled-result `/bbox` crop localization, layout polygon crop planning, OCR TextLine polygon precedence, serialized OCR/layout polygon normalization, named/numeric/extent/reversed bbox parsing, assigned-cell crop filtering, active-band trimming, normalized page-image geometry, forced-OCR routing, OCR prediction unwrapping, row/col span review, grid-border conflict review, rotated header axes, or Markdown table image artifact accounting. The new behavior is specifically saved table-recognition crop polygons before page-image table geometry localization.
