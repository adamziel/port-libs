# markerPDF table normalized geometry boundary

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T021106Z`
Base accepted HEAD: `9ba7946319f5b9e185a6d4dfe01a2aa2d62b772b`

## Source Truth

- Upstream `sddai/markerPDF` pinned in the lane manifest routes tables through `marker/tables/table.py::get_table_boxes()` and tabled assignment after page-image table crops are created.
- Upstream bbox helpers use a 0-1000 normalized box convention before mapping to image width/height (`marker.schema.bbox::unnormalize_box`). Native supplied table-recognition fixtures can therefore legitimately arrive as normalized table-crop geometry.
- The native no-GPU boundary keeps model output supplied by the caller and must normalize geometry before tabled-style row/column assignment, crop clipping, span-grid metadata, and WordPress table formatting.

## Implementation

- `TableRecognizer::formatRecognizedTables()` now recognizes explicit normalized table/crop coordinate spaces: `normalized`, `normalized_table`, `table_normalized`, `normalized_table_crop`, `table_crop_normalized`, `normalized_crop`, and `crop_normalized`.
- Rows, columns, cells, OCR conflict bboxes, and conflict candidate bboxes are scaled from 0-1000 into the current table crop image size, then marked as `table_crop`.
- The recognizer emits `table_recognition_coordinate_space_boundary` review metadata with `status=normalized_to_table_crop`, crop size, normalization scale, normalized row/column/cell/conflict counts, and unchanged default behavior for table-local geometry.

## Focused Evidence

Red-first before source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNormalizedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes supplied table recognition geometry from 1000-unit crop space before assignment
Expected: 'table_recognition_coordinate_space_boundary'
Actual: NULL
FAIL surfaces normalized supplied table geometry through WordPress conversion metadata
String does not contain '| Feature | Status |'
1 test files, 3 assertions, 2 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNormalizedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes supplied table recognition geometry from 1000-unit crop space before assignment
PASS surfaces normalized supplied table geometry through WordPress conversion metadata
1 test files, 41 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-normalized-geometry-boundary-currentbase.php
normalized_geometry_unnormalized=true
offcrop_normalized_cells_filtered_from_assignment=true
excluded_stale_pdftext_table_line=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Regression coverage for the existing table geometry/page-image coordinate paths:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNormalizedBoundaryCurrentBaseTest.php
6 test files, 170 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/TableGeometryNormalizedBoundaryCurrentBaseTest.php
3 test files, 1112 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. This reuses the native supplied-document converter, table crop planner, table recognizer, tabled-style assignment, span-grid review, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, tabled model inference, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted table-local crop clipping, reversed/named layout bbox normalization, arbitrary row/column id ordering, OCR grid-border conflict review, cell-boundary clipping metadata, page-image recognition geometry translation, JSON runtime/server boundaries, or native PDF parser xref/font/image/security work. The new behavior is specifically explicit 0-1000 normalized supplied table-recognition geometry being unnormalized to the current table crop before assignment and WordPress table formatting.
