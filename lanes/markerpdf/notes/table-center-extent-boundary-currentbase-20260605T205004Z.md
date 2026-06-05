# Table Center Extent Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T205004Z`
Base: `b2d18f8f737b5da773cc4fa24db4dce3548d79ba`

## Source Truth

- Locked `tabled-pdf==0.1.4` represents table, row, column, and
  `SpanTableCell` geometry as `Bbox` endpoints. The upstream `Bbox` helper also
  exposes `width`, `height`, and `center`, and `tabled.assignment` uses those
  dimensions/centers when assigning cells to row and column bands.
- Native no-GPU markerPDF supplied-boundary records can arrive from saved
  sidecars that serialize equivalent geometry as center-plus-size fields rather
  than endpoint lists. Those records should normalize to canonical tabled-style
  endpoints before table-crop localization, row/column assignment, stale-cell
  filtering, OCR conflict review, and Markdown/WordPress handoff.

## Implementation

- `TableRecognizer` now accepts `cx/cy/width/height`,
  `center_x/center_y/width/height`, and
  `x_center/y_center/width/height` anywhere supplied table geometry already
  accepts endpoint, top-left extent, or polygon bbox aliases.
- Source-review metadata now labels those inputs as
  `bbox_cx_cy_width_height_fields`,
  `bbox_center_x_center_y_width_height_fields`, and
  `bbox_x_center_y_center_width_height_fields`.
- The focused test covers table crop bboxes, row/column bands, active cells,
  off-band stale cells, OCR grid-border conflict boxes, candidate-cell boxes,
  spanning-grid source metadata, and supplied WordPress conversion.
- The WordPress smoke proves center-size source labels survive into row/cell
  review metadata while stale pdftext table lines and off-band supplied cells
  are excluded.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCenterExtentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL localizes center-size table geometry aliases before assigned-cell filtering (lanes/markerpdf/tests/TableGeometryCenterExtentBoundaryCurrentBaseTest.php)
Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.
FAIL surfaces center-size table geometry through supplied WordPress conversion (lanes/markerpdf/tests/TableGeometryCenterExtentBoundaryCurrentBaseTest.php)
Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.

1 test files, 0 assertions, 2 failures
```

Focused green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryCenterExtentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS localizes center-size table geometry aliases before assigned-cell filtering
PASS surfaces center-size table geometry through supplied WordPress conversion

1 test files, 52 assertions, 0 failures
```

Adjacent table-geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 28 selected test files (root lock skipped)
28 test files, 2258 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-center-extent-boundary-currentbase.php
```

The smoke emitted `direct_table_bbox_source=bbox_cx_cy_width_height_fields`,
`row_source_coordinate_source=bbox_cx_cy_width_height_fields`,
`center_extent_aliases_translated_to_crop=true`,
`stale_center_extent_cells_filtered=true`,
`excluded_stale_pdftext_table_line=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Other checks:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryCenterExtentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-center-extent-boundary-currentbase.php
php -r '$json=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

All completed successfully. Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2206 -> 2208`.
- `wordpressScenarios`: `1901 -> 1902`.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`TableRecognizer`, `SuppliedDocumentConverter`, table-crop localization,
OCR grid-border conflict review, spanning-grid review, Markdown formatter, and
WordPress supplied-boundary smoke path. Live OCR, Surya/Texify/Torch model
execution, PDFium rendering, Streamlit/FastAPI workers, external PDF tools, and
exact upstream model benchmark parity remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted endpoint aliases, top-left extent parsing,
named-bbox geometry, numeric-string coercion, reversed endpoint
canonicalization, normalized table/page-image geometry, image-bbox-relative
geometry, nested crop metadata, mixed coordinate-space conflict counts,
source-shape review propagation, polygon aliases, serialized polygons, scalar
spans, assigned-band/crop filtering, OCR polygon stale-bbox precedence, or
layout table geometry. The new behavior is only center-plus-size alias
normalization on supplied table crop, row, column, cell, OCR conflict, and
candidate-cell records.
