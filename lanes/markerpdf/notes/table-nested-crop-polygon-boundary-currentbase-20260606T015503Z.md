# Table Nested Crop Polygon Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260606T015503Z`
Base accepted HEAD: `a5143ad2fb20bad3e1fc096fc06844256ce0edb3`

## Source Truth

- Upstream `sddai/markerPDF` pinned in the lane manifest routes table extraction through `marker/tables/table.py::get_table_boxes()` and tabled recognition: page-image table regions are cropped before table assignment and Markdown formatting.
- Saved table-recognition sidecars may wrap crop metadata in nested `table_image` or `crop` records. Existing current-base behavior already treats top-level table polygons as a safer crop source than stale generic top-level `bbox` values.
- This slice applies that same boundary to nested crop wrappers: explicit crop keys such as `highres_bbox` remain authoritative, while generic nested `bbox`/`box` fallback fields must not outrank a valid nested polygon alias.

## Change

- `TableRecognizer::nestedTableCropBboxCandidate()` now checks explicit nested crop bbox keys first, then nested polygon aliases, then generic fallback `source_bbox`, `bbox`, and `box` keys.
- Added a focused current-base test where `crop.bbox` is stale page-image geometry but `crop.polygon` is the real table crop. The recognizer now selects `crop.polygon`, translates rows/cells to table-crop coordinates, and excludes stale off-crop sidecar cells.
- Updated the WordPress nested-crop smoke to emit the polygon-selection and stale-bbox exclusion flags without invoking Python, model workers, or external PDF tools.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS derives page-image crop boundary from nested saved table image metadata
FAIL prefers nested crop polygon over stale generic bbox before table geometry localization (lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 72.0,
  1 => 150.0,
  2 => 312.0,
  3 => 230.0,
)
Actual: array (
  0 => 400.0,
  1 => 300.0,
  2 => 520.0,
  3 => 340.0,
)
PASS surfaces nested crop table geometry through supplied WordPress conversion metadata

1 test files, 47 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS derives page-image crop boundary from nested saved table image metadata
PASS prefers nested crop polygon over stale generic bbox before table geometry localization
PASS surfaces nested crop table geometry through supplied WordPress conversion metadata

1 test files, 56 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCropPolygonStaleBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCropPolygonBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
8 PASS cases
3 test files, 128 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-table-geometry-nested-crop-boundary-currentbase.php
direct_nested_crop_source=crop.polygon
direct_nested_polygon_selected=true
direct_stale_nested_bbox_excluded=true
conversion_stale_nested_bbox_excluded=true
assigned_texts=["Feature","Status","Images","Ready"]
excluded_stale_nested_sidecar_cells=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryNestedCropBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-geometry-nested-crop-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted table-local crop clipping, named/numeric/reversed bbox normalization, top-level table polygon precedence over stale `bbox`, saved `tabled-pdf` top-level `bbox` localization, explicit `table_bbox`/`highres_bbox` crop handling, page-image coordinate localization, normalized geometry, OCR polygon precedence, forced-OCR routing, row/column span review, grid-border conflict review, rotated header axes, or Markdown table image artifact accounting. The bounded behavior is only nested crop-wrapper generic `bbox` fallback precedence when a valid nested polygon alias is present.

## Dependency Closure

No new support component is needed. This reuses the native PHP `TableRecognizer`, supplied recognition formatting, tabled-style assigned cell handling, crop-boundary clipping, polygon alias parsing, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, tabled model inference, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
