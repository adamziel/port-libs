# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260604T194715Z`

Accepted base: `c0b29c2f4df595f6436ef6c1ce550949bc31f263`

## Behavior

- Reworked `TableGeometry::columnCount()` so rowspans are scoped to Pandoc
  table row groups instead of flattening table head, body, and foot rows into
  one stream.
- Added bounded `TableGeometry::diagnostics()` for dangling rowspans that
  cross a table section boundary, reported as
  `rowspan-crosses-section-boundary` with section, row, column, original
  rowspan, and available row count.
- Clamped layout rowspans to the local row group, and made the WordPress table
  writer emit the layout span values rather than the raw malformed cell attrs.
- Reworked Markdown pipe-table fallback so table-head rowspans cannot reserve
  placeholder columns in body rows; body-local `headRows` still lay out with
  their own body rows.
- Updated the WordPress table geometry smoke to exercise the section-boundary
  diagnostic and clamped WordPress output.

## Source Truth

- Uses the existing static Pandoc table inventory as source truth: Pandoc
  tables carry distinct table head, body, and foot sections, and rowspans are
  resolved inside the row group being rendered.
- This ports the format contract only. It does not invoke Pandoc, Cabal,
  Haskell test binaries, office tools, `zip`/`unzip`, TeX/PDF engines,
  external template engines, browser renderers, or online services.

## Evidence

- `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors.
- `php -l lanes/pandoc/src/MarkdownWriter.php`: no syntax errors.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no
  syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`: 1 test
  file, 52 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`:
  table geometry handoff self-test ok.
- `php tools/run-tests.php lanes/pandoc/tests`: 11 test files, 3,414
  assertions, 0 failures.

## Status Delta

- `phpPass`: 366 -> 367.
- mapped native checks: 823 -> 824.
- `mappedTableGeometryCoreCases`: 4 -> 5.
- `tableGeometryCoreAssertions`: 36 -> 52.

## Non-Overlap

This does not repeat the accepted visual span layout, colspec-width
preservation, row-head-column WordPress output, DOCX `w:gridSpan`/`w:vMerge`
parsing, DocBook span parsing, HTML table reader row-header handling, or
Markdown pipe-table parsing. The new behavior is section-local table geometry
and dangling-rowspan diagnostics after an AST table already exists.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc table AST,
`TableGeometry` layout helper, native Markdown writer, and native WordPress
writer. Remaining table follow-up work is explicit malformed cell-overlap
diagnostics beyond dangling section rowspans and wider DOCX/ODT table
normalization reports.

Root harness: not run - isolated micro-slice.
