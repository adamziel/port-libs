# Table Prefixed Source Bbox Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260608T222400Z`

## Source Truth

- Upstream markerPDF routes table regions through rendered-page crop bboxes before tabled row/column assignment and Markdown formatting.
- The locked tabled/supplied-boundary path can preserve reviewed geometry separately from primary `bbox` fields. Existing native coverage accepted `source_bbox` and `original_bbox` wrapper arrays; this slice covers the same review boundary when sidecars serialize the original rectangle as prefixed named fields such as `source_left` / `source_top` / `source_right` / `source_bottom`, `original_x` / `original_y` / `original_w` / `original_h`, or `source_page_image_left` fields.
- This remains in the no-GPU markerPDF scope: supplied table geometry only; no live OCR, Surya, tabled model inference, Torch, PDFium/PIL rendering, Python, or external PDF tools.

## Change

- `TableRecognizer` now treats prefixed `source_*`, `original_*`, `source_page_image_*`, and `original_page_image_*` named geometry as fallback bboxes when a row, column, cell, OCR conflict, or candidate bbox omits primary `bbox` fields.
- Explicit wrapper fields such as `source_bbox`, `source_rect`, and `source_page_image_bbox` keep their existing provenance labels instead of being relabeled as prefixed wrappers.
- Added a focused test and WordPress smoke proving prefixed source geometry localizes from page-image coordinates into the table crop, off-crop saved cells are filtered before Markdown, and stale pdftext table lines are replaced by supplied table Markdown.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPrefixedSourceBboxBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS localizes prefixed source and original named table geometry fields
PASS surfaces prefixed source named geometry through supplied WordPress conversion

1 test files, 47 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPrefixedSourceBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryOcrSourceBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySourceBboxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySourceAliasBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
8 PASS cases
4 test files, 151 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 78 selected test files (root lock skipped)
156 PASS cases
78 test files, 3009 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryPrefixedSourceBboxBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-prefixed-source-bbox-boundary-currentbase.php
```

All report no syntax errors.

```text
php lanes/markerpdf/examples/wordpress-table-prefixed-source-bbox-boundary-currentbase.php
```

The smoke exits 0 and reports `prefixed_source_geometry_localized=true`, `source_page_image_geometry_preserved=true`, `offcrop_prefixed_cells_filtered_from_assignment=true`, `stale_pdftext_table_line_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted `source_bbox`/`original_bbox` wrapper arrays, source alias fields, nested source crop wrappers, nested named crop wrappers, endpoint/point/polygon aliases, normalized crop/page-image geometry, saved tabled bbox-order defaults, row/column band ordering, OCR source-bbox arrays, or live model table recognition. The bounded behavior is specifically prefixed named source/original geometry fields used as fallback bboxes at the supplied table boundary.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP supplied-document converter, table recognizer, bbox normalization, crop localization, assignment filters, WordPress smoke path, and table-geometry focused test family. Full pixel/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.
