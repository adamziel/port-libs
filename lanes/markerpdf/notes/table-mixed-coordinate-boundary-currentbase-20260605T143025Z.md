# markerpdf-table-geometry-boundary-current-base-20260605T143025Z

## Scope

Mixed per-record table OCR grid-border conflict coordinate spaces now preserve
honest review counts after localization to the table crop. The source change is
limited to `TableRecognizer` conflict localization counters; row/column/cell
geometry transforms, Markdown output, and stale off-crop filtering behavior are
unchanged.

## Source Truth

- Upstream `sddai/markerPDF` at the pinned lane manifest commit routes table
  extraction through `marker/tables/table.py`: rendered page images are cropped
  to table bboxes before the table recognizer runs.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::get_cells()`
  and `recognize_tables()` pass crop-local detector cells/OCR text into
  `tabled.assignment.assign_rows_columns()`.
- Locked `tabled-pdf==0.1.4` `tabled/assignment.py::assign_rows_columns()`
  consumes rows, columns, and cells in the same cropped table image coordinate
  space, while `tabled/formats/markdown.py::markdown_format()` formats the
  resulting `SpanTableCell` anchors.
- Therefore supplied no-GPU handoff records that mix `page_image`,
  `normalized_page_image`, `normalized_table`, and default `table_crop`
  geometry must all localize to the crop before WordPress review counts and
  table rendering are emitted.

## Implementation

- `TableRecognizer::localizeRecognizedTableGeometry()` now accumulates
  normalized/page-image OCR conflict localization counts instead of overwriting
  counts from an earlier mixed-space pass.
- Added `TableGeometryMixedCoordinateBoundaryCurrentBaseTest.php` covering a
  single supplied recognized table with mixed row, column, cell, and OCR
  conflict coordinate spaces.
- Added `wordpress-table-mixed-coordinate-boundary-currentbase.php` proving the
  same boundary through a WordPress supplied-document conversion smoke without
  Python, OCR models, tabled execution, pypdfium, or external PDF tools.

## Evidence

Red-first before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryMixedCoordinateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL accumulates mixed table conflict coordinate-space counters while localizing records
Expected translated/normalized conflict counts of 2, actual 1.
1 test files, 14 assertions, 2 failures
```

Focused test after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryMixedCoordinateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS accumulates mixed table conflict coordinate-space counters while localizing records
PASS surfaces mixed table coordinate-space review through supplied WordPress conversion
1 test files, 54 assertions, 0 failures
```

Adjacent table-geometry family after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 23 selected test files (root lock skipped)
23 test files, 2050 assertions, 0 failures
```

Smoke:

```text
php lanes/markerpdf/examples/wordpress-table-mixed-coordinate-boundary-currentbase.php
```

Emits `mixed_conflict_counts_preserved=true`,
`offcrop_mixed_coordinate_cells_filtered=true`,
`excluded_stale_pdftext_table_line=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Other checks:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryMixedCoordinateBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-mixed-coordinate-boundary-currentbase.php
php -r '$json=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

All completed successfully. Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`TableRecognizer`, `SuppliedDocumentConverter`, table crop planning, and
supplied table-recognition handoff paths. Live OCR, Surya/Texify/Torch model
execution, PDFium rendering, Streamlit/FastAPI workers, and exact upstream
model benchmark parity remain intentionally out of scope for this no-GPU
markerPDF lane.

## Non-Overlap

This does not repeat accepted table crop clipping, scalar spans, serialized
polygons, named/numeric bbox normalization, page-image localization,
normalized-page localization, image-bbox-relative localization, source-shape
review metadata, OCR polygon stale-bbox precedence, or assigned band/crop
filtering. The new behavior is specifically mixed OCR conflict count
accumulation when multiple source coordinate-space classes are present in one
supplied table.
