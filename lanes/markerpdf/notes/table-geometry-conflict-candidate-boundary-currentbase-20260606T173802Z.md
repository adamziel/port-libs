# Table Geometry Conflict Candidate Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260606T173802Z`

Accepted base: `9e3c99a8e5c01950dfc5cf7a611b50350af53219`

## Source Truth

- Upstream markerPDF crops rendered page images before table recognition, then
  tabled row/column assignment and OCR grid-border review work in table-crop
  coordinates.
- Native no-GPU scope uses supplied table recognition rows/cells/conflicts.
  This slice does not run Surya, OCR, tabled models, Python, PDFium, or
  external PDF tools.
- Supplied boundary adapters can preserve OCR grid-border conflict
  `candidate_cell_bboxes` as per-candidate records with their own
  `coordinate_space`. Those candidate records must be localized before
  WordPress grid-border review.

## Behavior

- `TableRecognizer` now includes per-candidate OCR conflict coordinate spaces in
  `source_record_coordinate_spaces['conflicts']`.
- Mixed `candidate_cell_bboxes` records are localized independently from
  `page_image`, `normalized_page_image`, `normalized_table`, and `table_crop`
  into table-crop bboxes.
- Candidate source provenance is preserved as
  `source_candidate_coordinate_spaces`,
  `source_candidate_coordinate_sources`, and
  `source_candidate_endpoint_order_normalized`.
- WordPress supplied-table conversion surfaces the localized candidates through
  `table_ocr_grid_border_conflicts` while replacing stale pdftext table lines.

## Evidence

Red-first focused run after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryConflictCandidateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 8 assertions, 2 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryConflictCandidateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS localizes per-candidate OCR conflict geometry before table grid review
PASS surfaces per-candidate OCR conflict geometry through supplied WordPress conversion
1 test files, 24 assertions, 0 failures
```

Table-geometry family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/TableGeometry.*Test\.php$' | sort)
Focused test run: 41 selected test files (root lock skipped)
41 test files, 1660 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-conflict-candidate-boundary-currentbase.php
```

The smoke reports `coordinate_status` as
`translated_and_normalized_to_table_crop`, candidate source spaces
`page_image`, `table_crop`, `normalized_page_image`, and `normalized_table`,
stale pdftext table-line exclusion, and both
`executes_python_or_models=false` and `executes_external_pdf_tools=false`.

Syntax/status checks:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryConflictCandidateBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-conflict-candidate-boundary-currentbase.php
php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'
```

All returned clean.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Returned clean.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
supplied-boundary table recognizer/converter path and remains inside the
current no-GPU markerPDF scope.

## Non-Overlap

This does not change live OCR, Surya/Texify/Torch execution, page-pixel visual
table recognition, PDFium, external PDF tools, or exact upstream model
benchmark parity. It is limited to supplied-boundary OCR conflict candidate
geometry already present in table recognition sidecars.
