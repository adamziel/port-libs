# Table OCR Header Grid Caption Cellspan Currentbase

Session: `port-dev-markerpdf-table66-20260602T214613Z`
Base accepted HEAD: `1455ea5c79ad53083218904a11ffee800f2a4b8c`

## Source Truth

- Upstream `sddai/markerPDF` behavior at the lane manifest commit routes table blocks through `marker/tables/table.py::format_tables()`.
- The pinned `tabled-pdf==0.1.4` path applies OCR text back onto detector cells before row/column assignment.
- `tabled.assignment.assign_rows_columns()` keeps merged occupancy on `SpanTableCell.row_ids` and `SpanTableCell.col_ids`.
- `tabled.formats.markdown.markdown_format()` renders only anchor cells through `tabulate(..., headers="firstrow")`, so caption-bound WordPress import metadata has to be recorded before Markdown drops covered cells.

## Implementation

- Added `accessibility.cellspan_header_grid` review metadata in `SuppliedDocumentConverter`.
- The review binds `caption_id` and `section_id` to the explicit OCR span grid, compact render cells, covered grid cells, header ids, and data-cell headers.
- Added focused supplied-converter coverage for a captioned OCR table with a merged corner header, covered grid cell, rowgroup header, and two data cells.
- Added the `wordpress-table-ocr-header-grid-caption-cellspan-currentbase.php` smoke for WordPress import invariants.

## Verification

- `php -l lanes/markerpdf/src/SuppliedDocumentConverter.php` - no syntax errors.
- `php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` - no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-table-ocr-header-grid-caption-cellspan-currentbase.php` - no syntax errors.
- `jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` - passed.
- `php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` - `1 test files, 439 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/TableFormatterTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` - `3 test files, 771 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-ocr-header-grid-caption-cellspan-currentbase.php | jq ...` - caption binding, merged header cellspan, covered-cell, data-header, stale-line exclusion, and no-external-execution booleans were all true.
- `git diff --check -- lanes/markerpdf` - passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `866 -> 867`.
- `wordpressScenarios`: `866 -> 867`.
- Mapped current-base behavior: `+1` via `tableOcrHeaderGridCaptionCellspanCurrentBase`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP supplied-document, forced-OCR table recognition, span-grid review, and WordPress example boundaries. It does not execute Python models, OCR engines, external PDF tools, or live services.

## Non-Overlap

This slice does not repeat rotated header accessibility grids, rowspanned caption accessibility, border-conflict grid metadata, runtime artifact preview boundaries, or parser/image/metadata review handoffs. It adds the missing caption-bound cellspan occupancy review for forced-OCR header-grid tables before Markdown formatting discards covered cells.
