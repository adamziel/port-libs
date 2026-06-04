# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260604T181014Z`

Accepted base: `3444a792da21cbff2a121bbe3129b57a38ba782a`

## Behavior

- Added bounded `TableGeometry::rowHeadColumns()` normalization for Pandoc
  `table_body` metadata.
- Rewired the WordPress table writer so ordinary body rows render cells as
  `<th>` when the cell occupies a row-header visual column, even if the cell
  does not carry a per-cell `header` flag.
- The decision uses `TableGeometry::layoutRows()`, so an active `rowspan` can
  shift a later physical cell into row-header column 1 and still render it as a
  header cell.
- Updated the WordPress table handoff smoke to exercise a row-spanned migration
  queue row header.

## Source Truth

- Uses the existing Pandoc static-inventory table rows as source truth:
  `test/html-reader.html` / `test/html-reader.native` record `RowHeadColumns`
  and body-local `TableBody` table shapes, and prior lane evidence already maps
  the HTML reader row-header cases.
- This slice ports the AST-to-WordPress handoff contract only: body-level
  row-header column metadata affects emitted table cell tags by visual column.
  It does not attempt the upstream Haskell runner.

## Evidence

- `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`:
  1 selected test file, 36 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`:
  table geometry handoff self-test ok.
- `php tools/run-tests.php lanes/pandoc/tests`:
  11 selected test files, 3339 assertions, 0 failures.

## Non-Overlap

This does not repeat the accepted table span/alignment slice, the HTML reader
row-header parser coverage, body-local `headRows` rendering, DOCX table span
parsing, DocBook span parsing, or colspec-width preservation. The new behavior
is the writer-side use of Pandoc `rowHeadColumns` metadata after the AST already
contains a table body with row-header columns.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc table AST,
`TableGeometry` layout, and native WordPress writer. Out of scope remain richer
malformed-overlap diagnostics, wider table normalization reports across DOCX
and ODT, and full upstream Pandoc Haskell runner execution.

Root harness: not run - isolated micro-slice.
