# Table Point-Pair Alias Geometry Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260606T190148Z`
Accepted base: `ced897c8e071b4d0c7639f40b112d9651e0a982c`

## Source Truth

- Upstream `sddai/markerPDF` table conversion routes table crops through `marker/tables/table.py`; each table image is cropped before rows, columns, and cells are assigned and formatted.
- The locked `tabled-pdf` handoff shape stores table geometry as lightweight JSON/Pydantic-like records. Current native markerPDF already accepts numeric arrays, named fields, corners, polygons, wrapped bbox aliases, and explicit coordinate-order records.
- This slice covers the bounded missing geometry carrier: neutral point-pair aliases (`start`/`end`, `from`/`to`, `p1`/`p2`, `point1`/`point2`, and `start_point`/`end_point`) used on supplied table rows, columns, cells, and image boxes before crop localization.

## Implementation

- `TableRecognizer::bboxPointPairFieldSets()` now registers the neutral point-pair aliases.
- The change reuses the existing bbox normalization, source-coordinate labeling, endpoint-order normalization, crop-boundary clipping, assigned-cell filtering, spanning-grid review, and WordPress supplied-conversion paths.
- No OCR/model/Python/PDFium/tabled runtime execution is introduced.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPointPairAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes neutral point-pair aliases before table crop localization
Table geometry entries must include a four-value bbox, named bbox fields, two-corner point fields, or four-corner polygon alias.
FAIL surfaces neutral point-pair table geometry through supplied WordPress conversion
Table geometry entries must include a four-value bbox, named bbox fields, two-corner point fields, or four-corner polygon alias.
1 test files, 0 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryPointPairAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes neutral point-pair aliases before table crop localization
PASS surfaces neutral point-pair table geometry through supplied WordPress conversion
1 test files, 48 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 43 selected test files (root lock skipped)
43 test files, 1763 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-table-point-pair-alias-boundary-currentbase.php
point_pair_aliases_normalized=true
offcrop_cells_filtered_from_assignment=true
excluded_stale_pdftext_table_line=true
source_endpoint_order_normalized=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted table-local crop clipping, saved `tabled` top-level bbox handling, named bbox fields, corner-point aliases, endpoint aliases, flat row/column aliases, explicit coordinate-order handling, normalized 1000-unit geometry, polygon aliases, page-result bbox order propagation, OCR polygon precedence, grid-border conflict review, span-grid accessibility review, or Markdown table image artifact accounting. The bounded behavior is specifically neutral two-point alias names before existing table crop localization and WordPress review.

## Dependency Closure

No new support component is needed. This reuses the native PHP `TableRecognizer`, supplied-document converter, crop-boundary assignment review, spanning-grid metadata, and WordPress example path. Live OCR, Surya/Texify/Torch model execution, `pdftext`, `pypdfium2`/PDFium rendering, tabled model inference, Streamlit/FastAPI workers, benchmark model downloads, and external OCR/rendering tools remain intentionally out of scope under the current no-GPU markerPDF directive.
