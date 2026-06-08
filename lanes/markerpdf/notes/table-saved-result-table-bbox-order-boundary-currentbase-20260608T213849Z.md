# Table Saved Result Table Bbox Order Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260608T212940Z`

## Source Truth

- Upstream `sddai/markerPDF` routes detected tables through `marker/tables/table.py`, cropping the high-resolution page image to each table bbox before tabled assignment and Markdown formatting.
- Locked `tabled-pdf` sidecars save each `TableResult` with `pnum`, `tnum`, crop bbox/image metadata, rows, columns, and cells.
- `tabled-pdf` row and column band bbox arrays are serialized as `x1,x2,y1,y2`, while cell/table bboxes are ordinary `xyxy`.

## Change

- `TableRecognizer` now treats saved tabled `TableResult` records as saved results when their crop arrives through `table_bbox`, `table_crop_bbox`, `crop_bbox`, `highres_bbox`, or `page_table_bbox`, not only literal `bbox`.
- Direct saved records and nested `rows_cols` containers that carry those crop aliases now default row and column band bbox order to `x1_x2_y1_y2` before page-image to table-crop localization.
- Added a focused test and WordPress smoke for a direct saved table record with `pnum`, `tnum`, `image_bbox`, rows/cols/cells, and `table_bbox` as the crop alias.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySavedResultTableBboxOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL defaults direct saved tabled row column bands to x1 x2 y1 y2 when crop uses table bbox alias
Expected first localized row bbox: [0.0, 0.0, 240.0, 32.0]
Actual first localized row bbox:   [0.0, 32.0, 78.0, 162.0]
FAIL surfaces direct saved tabled table bbox alias row column order through supplied WordPress conversion
String does not contain '| Feature | Status |'

1 test files, 8 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/tests/TableGeometrySavedResultTableBboxOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometrySavedResultTableBboxOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-saved-result-table-bbox-order-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-saved-result-table-bbox-order-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySavedResultTableBboxOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS defaults direct saved tabled row column bands to x1 x2 y1 y2 when crop uses table bbox alias
PASS surfaces direct saved tabled table bbox alias row column order through supplied WordPress conversion

1 test files, 39 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySavedResultTableBboxOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryRowsColsSavedResultOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryTableBboxOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySavedResultEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
12 PASS cases
5 test files, 196 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-table-saved-result-table-bbox-order-currentbase.php
table_bbox_alias_row_col_order_defaulted=true
offcrop_alias_cells_filtered_from_assignment=true
stale_pdftext_table_line_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

```text
git diff --check -- lanes/markerpdf
```

No output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This patch does not repeat accepted saved tabled literal `bbox` crop localization, saved-result envelope basename selection, nested `rows_cols` saved-result order defaults, explicit `table_bbox_order`, page-result table-bbox aliases, normalized crop geometry, wrapped bbox order metadata, assigned crop/band filters, OCR grid behavior, or live model table recognition. It covers only saved tabled records where the table crop is present under a table-crop alias and no explicit row/column coordinate order is supplied.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `TableRecognizer`, `TableFormatter`, supplied recognition records, crop-boundary filtering, and WordPress conversion smoke coverage. Live OCR, Surya/Texify/Torch model execution, tabled model inference, PDFium rendering, Streamlit/FastAPI model workers, and exact upstream benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
