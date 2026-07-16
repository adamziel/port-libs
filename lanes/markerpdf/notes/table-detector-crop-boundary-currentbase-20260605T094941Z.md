# Table Detector Crop Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T094941Z`

## Source Truth

Upstream `tabled.inference.recognition.get_cells()` receives the cropped table image and returns table-structure detector cells in that table-image coordinate space. OCR text is then assigned by source order to the detector cell list.

This PHP slice covers the supplied-boundary handoff for forced-OCR table imports: detector cells supplied for a cropped table must be bounded to the table-crop image before source-order OCR text is zipped into cells. A fully off-crop detector decoy must not shift OCR text from `Feature, Status, Images, Ready` into the wrong table cells.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryDetectorCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL filters off crop detector cells before source order OCR text assignment (lanes/markerpdf/tests/TableGeometryDetectorCropBoundaryCurrentBaseTest.php)
Expected 4 detector cells after crop-boundary filtering.
Expected: 4
Actual: 5

FAIL surfaces detector crop boundary through supplied WordPress conversion (lanes/markerpdf/tests/TableGeometryDetectorCropBoundaryCurrentBaseTest.php)
WordPress Markdown shifted OCR text into the wrong cells: `Status | Images` / `Ready`.

1 test files, 11 assertions, 2 failures
```

## Implementation

`TableRecognizer::getCells()` now derives the detector crop image size from the table bbox extent for forced-OCR detector input, normalizes legacy page-image detector fixtures only when no crop-local cells overlap the crop, and filters non-positive or fully off-crop detector cells before OCR source-order assignment.

The recognizer returns `table_detector_cell_boundary_reviews` with per-cell crop-boundary status, original and bounded bboxes, active/excluded flags, and `ocr_source_order_retained_after_crop_boundary`. `SuppliedDocumentConverter` carries those reviews into WordPress import metadata.

## Verification

Focused post-change command:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryDetectorCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS filters off crop detector cells before source order OCR text assignment
PASS surfaces detector crop boundary through supplied WordPress conversion

1 test files, 28 assertions, 0 failures
```

Additional focused family and smoke checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableGeometryDetectorCropBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 462 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryAssignedBandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryBandOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCellBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryDetectorCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryExtentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryImageBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryLayoutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryNormalizedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPrecomputedBlocksBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryRecordCoordinateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 16 selected test files (root lock skipped)
16 test files, 1762 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-table-detector-crop-boundary-currentbase.php
emits offcrop_detector_cell_excluded_before_ocr=true, ocr_source_order_preserved_after_crop_filter=true, table_cell_counts_after_detector_crop=[4], excluded_stale_pdftext_table_line=true, executes_python_or_models=false, executes_external_pdf_tools=false

git diff --check -- lanes/markerpdf
clean
```

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP bbox normalization, table crop-size derivation, detector supplied-boundary routing, and table assignment/formatting. It does not run live OCR, Surya, tabled/Texify models, Python, external PDF tools, or GPU/model execution.

## Non-Overlap

This does not repeat saved table bbox extent fallback, page-image bbox localization, record-coordinate localization, text-cell overlap filtering, assigned band filtering, conflict translation, or normalized/reversed bbox handling. The new behavior is limited to forced-OCR supplied detector cells at the table-crop boundary before source-order OCR text assignment.
