# Table bbox order boundary current-base

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260608T093616Z`
- Base accepted HEAD: `fc9cc5ac780ad879f0d013a4c9808a06a29c2d50`
- Scope: native no-GPU supplied table geometry handoff.

## Source truth and non-overlap

markerPDF table-recognition handoffs crop each detected table image before table formatting. Supplied sidecars may therefore provide a table crop rectangle in page-image coordinates while rows, columns, and cells are later translated into table-crop coordinates. Existing accepted slices covered generic flat `bbox_order`, wrapped `bbox_order`, page-result bbox order propagation, saved image bbox handoffs, and normalized page-image crop bbox handoffs. This slice covers the distinct field-specific crop rectangle case where the crop boundary is supplied as `table_bbox` with `table_bbox_order: x1_x2_y1_y2`.

The previous behavior treated `table_bbox` as ordinary `xyxy`, so `[72, 312, 150, 230]` became `[72, 230, 150, 312]`. That shifted the crop translation to `[-72, -230]`, pushed valid cells above the table crop, and removed the WordPress table output.

## Red-first evidence

Before the implementation patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryTableBboxOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL honors field specific table bbox coordinate order before crop localization
Values are not identical
Expected: [72,150,312,230]
Actual: [72,230,150,312]
FAIL surfaces field specific table bbox order through supplied WordPress conversion
String does not contain '| Feature | Status |'
Haystack: # Table Bbox Order Boundary

After table bbox order.

1 test files, 5 assertions, 2 failures
```

## Implementation

`TableRecognizer::tableCropBboxCandidateFromValue()` now checks for coordinate-order labels tied to the crop field before falling back to normal geometry parsing. The new path canonicalizes list and wrapped-list crop rectangles using field-specific labels such as `table_bbox_order`, `table_crop_bbox_order`, `crop_bbox_order`, `highres_bbox_order`, and `page_table_bbox_order`, then lets the existing coordinate-space conversion localize rows/cols/cells into the table crop.

The WordPress smoke fixture confirms the native supplied-boundary path renders:

```text
| Feature | Status |
|---------|--------|
| Images  | Ready  |
```

and excludes the off-crop stale row/column cells.

## Verification

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/tests/TableGeometryTableBboxOrderBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryTableBboxOrderBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-bbox-order-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-bbox-order-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryTableBboxOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 PASS, 31 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableGeometryTableBboxOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryCoordinateOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryWrappedCoordinateOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultCoordinateOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPageResultBboxEntryBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
8 PASS, 173 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 59 selected test files (root lock skipped)
118 PASS, 2322 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-table-bbox-order-boundary-currentbase.php
exits 0 with table_bbox_order_localized=true, coordinate_review_status=translated_to_table_crop, assigned_crop_active_cell_count=4, assigned_crop_excluded_cell_count=2, executes_python_or_models=false, executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Dependency closure

No new dependency or support component is needed. The behavior reuses the existing native PHP supplied layout/table-recognition handoff and table geometry parser. Live OCR, Surya/Texify/Torch, Python model workers, raster table detection, and exact upstream model parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Next

Continue with non-overlapping markerPDF native parser/converter gaps: searchable-PDF text extraction, fonts/CMaps/widths, stream filters, xref repair, metadata/outlines/annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
