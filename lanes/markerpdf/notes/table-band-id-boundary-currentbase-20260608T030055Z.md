# markerpdf table band id boundary current base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T030055Z`
Base accepted HEAD: `55ec937c4c82a12943c4829891dcf143a18f7fa2`

## Behavior

Supplied table row/column band identifiers are now parsed with the same
integer-token boundary used by assigned cell anchors. Malformed serialized
PDF-object-reference-looking tokens such as `0 0 R` and `1 0 R` are excluded
from the active table geometry before duplicate row/column band handling.

This prevents a malformed upstream band from being cast to `0` or `1`,
shadowing the valid band with the same integer id, and causing valid assigned
cells to be excluded as duplicate-band cells.

Review metadata now records:

- `excluded_invalid_row_id` for malformed row bands.
- `excluded_invalid_column_id` for malformed column bands.
- `invalid_id=true` and `raw_id` preserving the original malformed token.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBandIdBoundaryCurrentBaseTest.php`

failed on the accepted base with valid `Feature`, `Status`, and `Ready` cells
dropped from the supplied table, and the WordPress conversion rendered only the
remaining `Images` cell.

After fix:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBandIdBoundaryCurrentBaseTest.php`

passed: `1 test files, 27 assertions, 0 failures`.

Adjacent focused family:

`php tools/run-tests.php lanes/markerpdf/tests/TableGeometryBandIdBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedIdBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryAssignedBandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/TableGeometryDuplicateBandBoundaryCurrentBaseTest.php`

passed: `4 test files, 146 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-table-band-id-boundary-currentbase.php`

exits 0 with `malformed_row_band_rejected=true`,
`malformed_column_band_rejected=true`, `valid_cells_retained=true`,
`stale_pdf_table_line_removed=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native PHP
supplied-boundary table recognizer, Markdown formatter, and converter metadata
handoff. GPU/OCR/model table recognition and exact upstream model parity remain
out of scope for this no-GPU markerPDF lane.

