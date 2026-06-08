# Table Generic Wrapper Boundary Current Base

Micro-slice: `markerpdf-table-geometry-boundary-current-base-20260608T230530Z`

## Source Truth

- Upstream `sddai/markerPDF` routes detected table regions through `marker.tables.table.get_table_boxes` before tabled row/column assignment and Markdown formatting.
- The native no-GPU handoff receives supplied table recognition artifacts instead of running live Surya/tabled/OCR models. Saved review sidecars can carry Bbox-like table geometry in generic containers such as `geometry` or `coordinates`, while the upstream table crop and tabled `SpanTableCell` assignment boundary still expects a usable bbox.
- This remains in the no-GPU markerPDF scope: supplied table geometry only; no live OCR, Surya, tabled model inference, Torch, PDFium/PIL rendering, Python, or external PDF tools.

## Change

- `TableRecognizer` now treats `geometry` and `coordinates` as wrapped geometry aliases alongside `box`, `rect`, `rectangle`, `bounds`, and `bounding_box`.
- Generic wrappers now feed the existing bbox, named-field, point-list polygon, source-provenance, page-image localization, crop-boundary filtering, and WordPress table review paths.
- Added a focused test and WordPress smoke proving generic wrapper rows, columns, cells, OCR conflict bboxes, and candidate-cell bboxes localize to table-crop coordinates while stale off-crop cells and stale pdftext table lines stay out of output.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryGenericWrapperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL localizes generic geometry and coordinates wrappers before table assignment
Table geometry entries must include a four-value bbox, named bbox fields, two-corner point fields, or four-corner polygon alias.
FAIL surfaces generic wrapper geometry through supplied WordPress conversion
Table geometry entries must include a four-value bbox, named bbox fields, two-corner point fields, or four-corner polygon alias.

1 test files, 0 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryGenericWrapperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS localizes generic geometry and coordinates wrappers before table assignment
PASS surfaces generic wrapper geometry through supplied WordPress conversion

1 test files, 47 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryGenericWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryWrappedAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPolygonAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometrySourceAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryPrefixedSourceBboxBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
10 PASS cases
5 test files, 215 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 79 selected test files (root lock skipped)
158 PASS cases
79 test files, 3056 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryGenericWrapperBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-generic-wrapper-boundary-currentbase.php
```

All report no syntax errors.

```text
php lanes/markerpdf/examples/wordpress-table-generic-wrapper-boundary-currentbase.php
```

The smoke exits 0 and reports `generic_wrapper_geometry_localized=true`, `geometry_point_wrapper_provenance_preserved=true`, `offcrop_wrapper_cells_filtered_from_assignment=true`, `stale_pdftext_table_line_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted `source_bbox`/`original_bbox` arrays, prefixed `source_*`/`original_*` named fields, wrapped `box`/`rect`/`bounding_box` aliases, polygon `points`/`vertices`/`quad` aliases, explicit coordinate-order handling, saved tabled row/column order defaults, page-result table-bbox aliases, nested crop metadata, assigned crop/band filters, OCR source-bbox arrays, or live model table recognition. The bounded behavior is specifically generic `geometry` and `coordinates` wrapper keys used as supplied table geometry containers.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `TableRecognizer`, `SuppliedDocumentConverter`, bbox normalization, crop localization, assignment filters, WordPress smoke coverage, and the table-geometry focused test family. Live OCR, Surya/Texify/Torch model execution, tabled model inference, PDFium rendering, Streamlit/FastAPI model workers, and exact upstream benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
