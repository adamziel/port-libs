# Table Detector Source Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T224309Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` crops rendered page images for table recognition before assigning table rows, columns, and cells.
- Locked `tabled-pdf==0.1.4` detector cells carry bbox-shaped row/cell records that downstream assignment and review consume in the table-image coordinate space.
- The native no-GPU PHP path uses supplied detector cells and supplied OCR text. When detector cells arrive in page-image coordinates and are localized into the table crop, WordPress review metadata must retain the original bbox field shape and whether endpoint order was normalized.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryDetectorSourceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves detector source field-shape metadata after page-image crop localization (lanes/markerpdf/tests/TableGeometryDetectorSourceBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'bbox_left_top_right_bottom_fields'
Actual: NULL
FAIL surfaces detector source metadata through supplied WordPress conversion (lanes/markerpdf/tests/TableGeometryDetectorSourceBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'bbox_xmin_ymin_xmax_ymax_fields'
Actual: NULL

1 test files, 21 assertions, 2 failures
```

## Implementation

`TableRecognizer::getCells()` now preserves source geometry review fields only while normalizing supplied detector cells. The page-image-to-table-crop localization keeps the original `source_bbox`, `source_coordinate_space`, `source_coordinate_source`, and `source_endpoint_order_normalized` fields, and `table_detector_cell_boundary_reviews` now carries those fields for both active and excluded detector cells.

This is intentionally narrower than the earlier detector-crop slice: it does not change crop filtering or OCR source-order assignment. It only exposes source-coordinate provenance that was lost before the detector crop-boundary review was emitted.

## Verification

Focused post-change command:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryDetectorSourceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves detector source field-shape metadata after page-image crop localization
PASS surfaces detector source metadata through supplied WordPress conversion

1 test files, 42 assertions, 0 failures
```

Adjacent table geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryDetectorSourceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryDetectorCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySourceReviewBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
PASS filters off crop detector cells before source order OCR text assignment
PASS surfaces detector crop boundary through supplied WordPress conversion
PASS preserves detector source field-shape metadata after page-image crop localization
PASS surfaces detector source metadata through supplied WordPress conversion
PASS preserves source field-shape metadata after table geometry localization
PASS surfaces source field-shape metadata through supplied WordPress table review

3 test files, 109 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-detector-source-boundary-currentbase.php
emits source_shape_preserved_after_crop_localization=true, source_bbox_localized_to_table_crop=true, ocr_source_order_preserved_after_crop_filter=true, offcrop_detector_cell_excluded_before_ocr=true, excluded_stale_pdftext_table_line=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Status Delta

- `phpPass`: `2253 -> 2255`.
- `wordpressScenarios`: `1941 -> 1942`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP bbox normalization, source geometry review metadata, table-crop localization, supplied detector-cell routing, OCR text assignment, table formatting, and WordPress conversion metadata. It does not run live OCR, Surya, tabled/Texify models, Python, external PDF tools, GPU/model execution, or upstream benchmark runners.

## Non-Overlap

This does not repeat detector crop filtering, text-cell overlap filtering, assigned/source table geometry review, named/reversed/normalized bbox localization, OCR grid-border conflict review, or saved table bbox extent fallback. The bounded behavior is detector-cell source-coordinate provenance after page-image detector cells are localized to the table crop.
