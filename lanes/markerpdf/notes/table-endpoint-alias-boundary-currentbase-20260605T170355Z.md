# Table Endpoint Alias Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T170355Z`
Base: `3e922ffb90f045be92470ed06339fb276388af76`

## Source Truth

- Upstream `sddai/markerPDF` at the pinned lane manifest commit routes table
  extraction through rendered page-image crops before assigning rows/columns
  and formatting Markdown.
- Locked `tabled-pdf==0.1.4` keeps table, row, column, and `SpanTableCell`
  geometry as `Bbox` endpoint data; `tabled.assignment.assign_rows_columns()`
  consumes those boxes before Markdown formatting uses `row_ids`/`col_ids`.
- No-GPU PHP supplied-boundary records can therefore arrive with equivalent
  serialized endpoint names from saved sidecars. The native recognizer should
  normalize common `x0/y0/x1/y1`, `xmin/ymin/xmax/ymax`, and
  `x_min/y_min/x_max/y_max` bbox aliases before table-crop localization,
  assignment, conflict review, and WordPress Markdown output.

## Implementation

- `TableRecognizer` now accepts `x0/y0/x1/y1`, `xmin/ymin/xmax/ymax`, and
  `x_min/y_min/x_max/y_max` named endpoint bboxes anywhere the supplied table
  geometry path already accepted `x1/y1/x2/y2`, start/end, left/right, extent,
  or polygon fields.
- Source-review metadata now labels those aliases as
  `bbox_x0_y0_x1_y1_fields`, `bbox_xmin_ymin_xmax_ymax_fields`, and
  `bbox_x_min_y_min_x_max_y_max_fields`.
- The focused test covers table crop bbox aliases, row/column bands, active
  cells, off-band stale cells, OCR grid-border conflict bboxes, and
  candidate-cell bboxes.
- The WordPress smoke proves supplied table Markdown replaces stale pdftext
  table lines while endpoint-alias source labels survive into row/column/cell
  review metadata.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryEndpointAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL localizes endpoint-alias table geometry before supplied-table assignment (lanes/markerpdf/tests/TableGeometryEndpointAliasBoundaryCurrentBaseTest.php)
Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.
FAIL surfaces endpoint-alias table geometry through supplied WordPress conversion (lanes/markerpdf/tests/TableGeometryEndpointAliasBoundaryCurrentBaseTest.php)
Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.

1 test files, 0 assertions, 2 failures
```

Focused green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryEndpointAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS localizes endpoint-alias table geometry before supplied-table assignment
PASS surfaces endpoint-alias table geometry through supplied WordPress conversion

1 test files, 49 assertions, 0 failures
```

Adjacent table-geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 26 selected test files (root lock skipped)
26 test files, 2173 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-endpoint-alias-boundary-currentbase.php
```

The smoke emitted `endpoint_aliases_translated_to_crop=true`,
`stale_endpoint_alias_cells_filtered=true`,
`excluded_stale_pdftext_table_line=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Other checks:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryEndpointAliasBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-endpoint-alias-boundary-currentbase.php
php -r '$json=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

All completed successfully. Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2094 -> 2096`.
- `wordpressScenarios`: `1808 -> 1809`.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`TableRecognizer`, `SuppliedDocumentConverter`, table-crop localization,
OCR grid-border conflict review, spanning-grid review, Markdown formatter, and
WordPress supplied-boundary smoke path. Live OCR, Surya/Texify/Torch model
execution, PDFium rendering, Streamlit/FastAPI workers, external PDF tools,
and exact upstream model benchmark parity remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted table crop clipping, named-bbox extent parsing,
numeric-string coercion, reversed endpoint canonicalization, normalized
table/page-image geometry, image-bbox-relative geometry, nested crop metadata,
mixed coordinate-space conflict counts, source-shape review propagation,
polygon aliases, serialized polygons, scalar spans, assigned-band/crop
filtering, OCR polygon stale-bbox precedence, or layout table geometry. The new
behavior is only equivalent named endpoint aliases on supplied table crop,
row, column, cell, OCR conflict, and candidate-cell records.
