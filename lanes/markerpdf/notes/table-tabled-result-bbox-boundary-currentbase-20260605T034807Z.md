# Table Tabled Result Bbox Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T034807Z`

## Source Truth

- Upstream `sddai/markerPDF` pinned in the lane manifest routes tables through `marker/tables/table.py::get_table_boxes()` and `format_tables()`: high-resolution page images are cropped to each table bbox before `tabled.assignment.assign_rows_columns()` and Markdown formatting.
- Locked `tabled-pdf==0.1.4` `extract.py` saves each table result with `cells`, `rows`, `cols`, top-level table `bbox`, `image_bbox`, `pnum`, and `tnum`.
- Locked `tabled-pdf==0.1.4` `tabled/schema.py::SpanTableCell` carries cell `bbox`, `text`, `row_ids`, `col_ids`, and optional `order`.

## Change

- `TableRecognizer::tableCropBbox()` now accepts a saved `tabled-pdf` result's top-level `bbox` as the table crop boundary when a supplied recognition bundle explicitly declares page-image coordinate geometry.
- This preserves existing explicit `table_bbox`, `table_crop_bbox`, `crop_bbox`, `highres_bbox`, `page_table_bbox`, and table-local defaults.
- Added a focused current-base test proving saved `tabled` page-image rows/cols/cells are translated by top-level `bbox` before assigned SpanTableCell geometry reaches Markdown.
- Added a WordPress smoke example that emits a Gutenberg table from saved `tabled` result geometry while filtering off-crop stale page-image cells.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS supplied assigned table cells keep upstream row column ids when bboxes overlap different bands
FAIL uses saved tabled result bbox as page image crop boundary for assigned geometry
Values are not identical
Expected: 'translated_to_table_crop'
Actual: 'missing_table_crop_bbox'
PASS supplied document conversion preserves assigned table cell geometry boundaries

1 test files, 25 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS supplied assigned table cells keep upstream row column ids when bboxes overlap different bands
PASS uses saved tabled result bbox as page image crop boundary for assigned geometry
PASS supplied document conversion preserves assigned table cell geometry boundaries

1 test files, 35 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
3 test files, 1134 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-table-tabled-result-bbox-boundary-currentbase.php
saved_tabled_bbox_translated=true
offcrop_saved_result_cells_filtered_from_assignment=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted table-local crop clipping, named/numeric/reversed bbox normalization, explicit `table_bbox` page-image localization, normalized 1000-unit table geometry, OCR polygon precedence over stale bbox, forced-OCR routing, OCR prediction unwrapping, span/rowspan/colspan review, grid-border conflict review, rotated header axes, or Markdown image artifact accounting. The bounded behavior is specifically saved `tabled-pdf` result dictionaries whose page-image geometry declares only top-level `bbox`/`image_bbox` as the crop boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP `TableRecognizer`, supplied recognition formatting, tabled-style assigned cell handling, crop-boundary clipping, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, tabled model inference, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
