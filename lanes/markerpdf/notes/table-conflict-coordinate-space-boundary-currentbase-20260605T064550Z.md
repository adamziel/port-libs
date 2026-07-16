# Table Conflict Coordinate-Space Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260605T064550Z`

Base accepted HEAD: `648921bad2812fb886ed9ddc4a44b11bdbf63665`

## Source Truth

- Upstream marker table extraction crops page images before tabled recognition; rows, columns, cells, and OCR grid-border conflict reviews are therefore consumed as table-crop-local geometry during assignment/review.
- Supplied serialized handoff bundles can preserve a separate coordinate-space declaration for `ocr_grid_border_conflicts` even when rows, columns, and cells are already table-crop-local.
- Native no-GPU scope: this patch uses supplied recognition rows/cells/conflicts and does not run Surya, tabled models, OCR, Python, Torch, or external PDF tools.

## Implementation

- `TableRecognizer` now recognizes field-specific OCR conflict geometry keys such as `ocr_grid_border_conflicts_coordinate_space` and `grid_border_conflicts_geometry_space`.
- Page-image OCR conflict `bbox` and `candidate_cell_bboxes` are translated into table-crop coordinates without translating already crop-local rows, columns, or cells.
- Localized conflict coordinate-space aliases are stamped back to `table_crop` so a later formatting pass cannot translate the same conflict geometry twice.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS propagates clipped table grid boundary into OCR grid-border conflict rows
FAIL translates field-specific page-image OCR grid-border conflict geometry into table crop (lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'translated_to_table_crop'
Actual: NULL
PASS surfaces OCR grid-border crop boundary metadata through supplied WordPress conversion

1 test files, 40 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryConflictBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS propagates clipped table grid boundary into OCR grid-border conflict rows
PASS translates field-specific page-image OCR grid-border conflict geometry into table crop
PASS surfaces OCR grid-border crop boundary metadata through supplied WordPress conversion

1 test files, 53 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-conflict-coordinate-space-boundary-currentbase.php
```

Emits `field_specific_conflict_geometry_translated=true`, `crop_local_table_cells_preserved=true`, `excluded_stale_pdftext_table_line=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted full-table page-image translation, normalized 1000-unit table geometry, table-local crop clipping, stale OCR polygon precedence, assigned-band exclusion, precomputed blocks, unsorted band order, or table source-bbox metadata. The owned behavior is only the field-specific coordinate-space declaration on OCR grid-border conflicts when the rest of the table geometry is already crop-local.

## Dependency Closure

No new support component is needed. The existing native supplied-boundary table recognizer and WordPress converter metadata path are reused.

## Next Task

Continue no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior or supplied-boundary table/equation handoffs, especially native page geometry, fonts/CMaps, stream filters, xref repair, annotations/forms, metadata, and table/equation review metadata.
