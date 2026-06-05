# markerPDF table image-bbox-relative boundary current base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260605T121003Z`
Base accepted HEAD: `295120098a86970c9ff6f0c0719d64afe0c9dda9`

## Source Truth

- Upstream markerPDF routes table crops through `marker/tables/table.py` before
  tabled assignment and Markdown formatting.
- The locked tabled-pdf 0.1.4 result contract documents `image_bbox` as the
  containing image bbox and table `bbox` as relative to that image bbox before
  cell, row, and column formatting metadata are consumed.
- This no-GPU lane therefore needs an explicit supplied-boundary coordinate
  space for saved table rows, columns, cells, and OCR conflict bboxes that are
  declared relative to the table result `image_bbox`, not the page origin.

## Implementation

- `TableRecognizer::isPageImageCoordinateSpace()` now recognizes
  `image_bbox_relative`, `relative_image_bbox`, `image_bbox_local`,
  `local_image_bbox`, `saved_image_bbox`, and `saved_image_bbox_relative`.
- Those declared records now take the existing crop-localization path:
  source bboxes are preserved as review metadata, then translated by the saved
  table crop bbox into table-crop coordinates before assigned-cell filtering,
  active-band trimming, Markdown formatting, and WordPress review metadata.
- Existing table-crop, page-image, normalized table, normalized page-image,
  polygon, extent, named-bbox, assigned-cell crop, detector-cell crop, and
  band-boundary behavior is preserved.

## Verification

Red before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryImageBboxRelativeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL translates image-bbox-relative tabled result geometry into the crop image before assignment
Expected: 'table_recognition_coordinate_space_boundary'
Actual: NULL
FAIL surfaces image-bbox-relative table geometry through supplied WordPress conversion metadata
String does not contain '| Feature | Status |'
1 test files, 3 assertions, 2 failures
```

Green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryImageBboxRelativeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS translates image-bbox-relative tabled result geometry into the crop image before assignment
PASS surfaces image-bbox-relative table geometry through supplied WordPress conversion metadata
1 test files, 41 assertions, 0 failures
```

Adjacent table-geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 18 selected test files (root lock skipped)
18 test files, 706 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-image-bbox-relative-boundary-currentbase.php
```

The smoke reports `image_bbox_relative_translated_to_crop=true`,
`stale_relative_cells_filtered=true`, `excluded_stale_pdftext_table_line=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`TableRecognizer`, supplied-document converter, table formatter, bbox
normalization, and WordPress smoke path. Live OCR, Surya/Texify/Torch model
execution, PDFium rendering, Streamlit/FastAPI model workers, and exact
upstream model benchmark parity remain intentionally out of scope under the
no-GPU markerPDF direction.

## Non-Overlap

This does not repeat accepted table crop polygons, tabled-result top-level
`bbox` extent sizing, page-image geometry, normalized page-image geometry,
record-level coordinate-space translation, serialized polygons, detector-cell
crop filtering, assigned-cell crop filtering, active-band trimming, OCR
grid-border conflict review, forced-OCR routing, rowspan/colspan review, or
Markdown table image artifact accounting. The bounded behavior is specifically
declared image-bbox-relative tabled result rows, columns, cells, and conflict
geometry before WordPress table review.
