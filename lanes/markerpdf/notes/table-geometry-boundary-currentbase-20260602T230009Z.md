# Table Geometry Boundary Current Base

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker/tables/table.py::get_table_boxes` crops each merged table box from the high-resolution rendered page image before table recognition.
- Locked `tabled-pdf==0.1.4` `tabled/schema.py::SpanTableCell` keeps assigned `row_ids` and `col_ids`, and `tabled/assignment.py::assign_rows_columns()` uses model row/column `Bbox` geometry to preserve span occupancy. WordPress review metadata therefore needs table-crop-local grid geometry, not full-page or unbounded supplied rows/columns.

## Implementation

- `TableRecognizer` now accepts an optional table crop image size for merged-cell, spanning-grid, and OCR grid-border review paths.
- Review geometry clips supplied row/column bands to the table crop boundary, excludes non-positive or fully out-of-crop bands, and emits `geometry_boundary_review` with active/clipped/excluded band counts and per-band status.
- `SuppliedDocumentConverter` now passes crop-plan `crop_width`/`crop_height` into table review metadata while keeping upstream-style assignment and Markdown formatting on the existing full-page image-size path.
- Added `wordpress-table-geometry-boundary-currentbase.php` to prove the WordPress import path replaces stale pdftext table lines while retaining clipped table-grid metadata without Python models or external PDF tools.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed with `2 test files, 802 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-table-geometry-boundary-currentbase.php` passed and reported crop boundary `300 x 80`, `3` clipped bands, `2` excluded bands, and stale pdftext table-line exclusion.
- `php -l` passed for changed source, test, and example PHP files.

## Dependency Closure

No new support component is needed. This slice reuses existing native table crop planning, tabled-style assignment metadata, and supplied-document conversion hooks; live Surya/tabled/Python execution remains outside this isolated current-base boundary.

## Non-Overlap

This does not repeat accepted forced-OCR routing, OCR prediction unwrapping, merged-cell geometry, grid-border conflict assignment, header-grid accessibility IDs, rowspanned caption binding, rotated header axes, or Markdown table image artifact accounting. The new behavior is specifically table-crop-local clipping and exclusion of supplied row/column model bands before WordPress grid review metadata is generated.
