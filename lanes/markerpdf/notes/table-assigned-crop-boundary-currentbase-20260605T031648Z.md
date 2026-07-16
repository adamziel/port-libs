# markerpdf-table-geometry-boundary-current-base-20260605T031648Z

## Behavior

Already assigned supplied table cells now pass through the same positive-area
table-crop boundary used by raw detector assignment before Markdown formatting.
This prevents stale `SpanTableCell` rows/columns outside the cropped table image
from creating extra WordPress table columns/rows while preserving partially
crossing cells for crop-boundary review metadata.

## Source Truth

- Static upstream source read from local tabled wheel
  `/tmp/markerpdf-tabled-src/tabled_pdf-0.1.4-py3-none-any.whl`.
- `tabled/inference/recognition.py::get_cells()` operates on per-table crop
  images and keeps detector cells only when the table-bbox area is positive.
- `tabled/assignment.py::assign_rows_columns()` consumes table-crop-local cells,
  rows, and columns, then produces `SpanTableCell` `row_ids`/`col_ids`.
- `tabled/formats/markdown.py::markdown_format()` sorts assigned cells and
  consumes their first row and column anchors. Therefore supplied already
  assigned cells must be bounded before Markdown emission, not only before raw
  assignment.

No Surya, OCR, tabled model execution, Torch, Python runtime, pypdfium/PIL, or
external PDF tools were invoked.

## Red-First Evidence

Before the patch, an ad hoc PHP fixture with already assigned cells leaked
fully off-crop text into the Markdown table:

```text
| Header | Status |                  |
|--------|--------|------------------|
| Images | Ready  | Offcrop assigned |
["Header","Status","Images","Ready","Offcrop assigned"]
```

## Verification

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-assigned-crop-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-assigned-crop-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS filters already assigned supplied cells with no positive crop area before Markdown formatting
PASS supplied WordPress conversion excludes off crop already assigned table cells
1 test files, 32 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNormalizedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 1296 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-table-assigned-crop-boundary-currentbase.php
exits 0 and reports offcrop_assigned_cells_filtered_from_assignment=true, excluded_stale_pdftext_table_line=true, executes_python_or_models=false, executes_external_pdf_tools=false.

php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
exits 0 with no output.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native
`TableRecognizer` supplied-boundary pipeline, table-crop image size metadata,
and WordPress table formatting path.

## Non-Overlap

This slice does not repeat prior normalized-coordinate, layout-bbox,
assigned-cell ordering, raw assignment crop-boundary, cell-boundary review,
OCR grid-border, or model/OCR routing coverage. It only closes the fast path
for already assigned supplied `SpanTableCell` data before Markdown formatting.
