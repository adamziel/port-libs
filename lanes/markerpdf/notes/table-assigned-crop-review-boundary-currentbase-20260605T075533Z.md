# markerpdf-table-geometry-boundary-current-base-20260605T075533Z

## Behavior

Already assigned supplied `SpanTableCell` data now emits a crop-boundary review
before active row/column band filtering and Markdown formatting. Fully off-crop
saved assignments remain excluded from table output, partially crossing cells
keep their clipped bboxes, and WordPress metadata can explain which saved
assigned cells were dropped before Markdown.

## Source Truth

- Reuses the local upstream-backed tabled source truth recorded for the current
  assigned crop-boundary slice: table recognition and assignment operate on
  table-crop-local cells before `SpanTableCell` data reaches Markdown.
- `tabled.assignment.SpanTableCell` bboxes are already assignment output, so the
  markerPDF port must review their crop-local boundary before trusting their
  saved row/column anchors.
- No Surya, OCR, tabled model execution, Torch, Python runtime, pypdfium/PIL,
  network, or external PDF tools were invoked.

## Implementation

- `TableRecognizer::formatRecognizedTables()` now records
  `assigned_crop_boundary_reviews` for complete saved assigned cells and filters
  inactive off-crop assignments through that review before the existing active
  band boundary pass.
- `SuppliedDocumentConverter` forwards non-null assigned crop reviews into
  `table_assigned_crop_boundary_reviews` metadata.
- The WordPress smoke now reports the assigned crop review target, total saved
  assigned cell count, excluded count, off-crop statuses, and whether off-crop
  assignments were excluded before Markdown.

## Verification

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/src/SuppliedDocumentConverter.php
No syntax errors detected in lanes/markerpdf/src/SuppliedDocumentConverter.php

php -l lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-assigned-crop-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-assigned-crop-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBandBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 94 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 452 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 1187 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-table-assigned-crop-boundary-currentbase.php
exits 0 and reports assigned_crop_review_target=table_assigned_cell_crop_boundary,
assigned_crop_cell_count=6, assigned_crop_excluded_cell_count=2,
offcrop_assignment_excluded_before_markdown=true, executes_python_or_models=false,
and executes_external_pdf_tools=false.

php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
exits 0 with no output.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the existing native
`TableRecognizer` crop geometry helpers, supplied table assignment path, and
WordPress conversion metadata pipeline.

## Non-Overlap

This does not repeat the earlier crop filter that removed off-crop assigned
cells from Markdown, nor the assigned active-band, spanning-grid, raw detector
cell, coordinate normalization, OCR/grid-border, or model/OCR routing slices.
It only adds the missing review surface for already assigned saved cells before
the accepted output filter.
