# Rotated Header Accessibility Grid Current Base

Session: `port-dev-markerpdf-table58-20260602T212108Z`
Base accepted HEAD: `7a7220f52fd6cdbbaea942c909b4d8b982da4bfa`
Micro-slice: `table-rotated-header-accessibility-grid-currentbase`

## Source Truth

- Upstream markerPDF `marker/tables/table.py` formats assigned table cells after `tabled.assignment.assign_rows_columns()` and replaces intersecting table blocks with the formatted table output.
- Locked `tabled-pdf` source `tabled/assignment.py` swaps the row/column axes for rotated tables through `is_rotated()` before assigning unassigned cells and applying row/column spans.
- Locked `tabled-pdf` Markdown/HTML formatters still render from first row/column anchors (`headers="firstrow"` style behavior), so covered span cells disappear from the rendered table unless the native port carries a separate review grid.

## Behavior Added

The native `TableRecognizer::spanningGridReview()` now emits `accessibility_grid` metadata for normal and rotated tables. For rotated rowspan headers, the review records:

- stable header ids for retained `th` anchors,
- `headers`, `column_header_ids`, and `row_header_ids` for data cells,
- `column_header_grid` and `row_header_grid` coverage,
- physical axis metadata showing column headers follow the rotated `y` axis and row headers follow the rotated `x` axis.

The WordPress rotated-table example now renders `id` and `headers` attributes plus `data-markerpdf-column-header-axis` and `data-markerpdf-row-header-axis` attributes for data cells.

## Focused Evidence

- Baseline focused table gate before the slice: `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed `1 test files, 259 assertions, 0 failures`.
- After the slice: `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php` passed `1 test files, 284 assertions, 0 failures`.
- New PASS case: `builds rotated header accessibility grid for WordPress headers attributes`.
- Status delta: behavior tests `843 -> 844 pass / 0 fail`; mapped markerPDF semantics `591 -> 592 / 78`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native table recognizer, supplied-document converter, and locked local `tabled-pdf` source-truth behavior. Python model execution, Surya OCR, tabled runtime execution, pdftext, pypdfium, and external PDF tools remain excluded.

## Non-overlap

This does not repeat the accepted rotated-rowspan grid-axis slice, rowspanned header-grid slice, OCR caption accessibility slice, or grid-border conflict review. Those slices preserve span geometry, header roles, section/caption context, or conflict metadata; this slice adds the separate accessibility-grid map needed to keep header relationships after covered cells are removed from the rendered table.
