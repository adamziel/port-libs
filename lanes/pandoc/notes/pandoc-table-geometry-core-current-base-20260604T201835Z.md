# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260604T201835Z`

Accepted base: `43d89c982a73be25b1aa06602ec736f2cd9890c1`

## Behavior

- Added bounded `TableGeometry::diagnostics()` coverage for cells whose visual
  column span exceeds the table's declared Pandoc columns from `alignments` or
  `widths`.
- New diagnostics use `cell-exceeds-declared-columns` with section, row, visual
  column, raw colspan, declared column count, and exclusive end column.
- Writers remain loss-preserving: `TableGeometry::columnCount()` still expands
  to the row geometry, and native WordPress/Markdown output keeps overflow
  cells visible for review instead of dropping malformed imported content.
- Updated the WordPress table geometry smoke to cover a rowspanned row-header
  table that needs a third visual column even though the source colspec declares
  two columns.

## Source Truth

- Uses the existing static Pandoc table inventory as source truth: Pandoc table
  ASTs carry column specs plus table head/body/foot row groups, and prior
  accepted slices already map visual spans, row-head columns, and section-scoped
  rowspans.
- This slice ports a bounded support-library handoff contract only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, office tools, `zip`/`unzip`,
  TeX/PDF engines, external template engines, browser renderers, or online
  services.

## Evidence

- `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no
  syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`:
  pandoc json ok.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`:
  table geometry handoff self-test ok.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`: 1 test
  file, 74 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 11 test files, 3,458
  assertions, 0 failures.

## Status Delta

- `phpPass`: 368 -> 369.
- mapped native checks: 825 -> 826.
- `mappedTableGeometryCoreCases`: 5 -> 6.
- `tableGeometryCoreAssertions`: 52 -> 74.

## Non-Overlap

This does not repeat accepted table span/alignment layout, colspec-width
preservation, row-head-column WordPress output, section-boundary rowspan
clamping, DOCX `w:gridSpan`/`w:vMerge` parsing, DocBook span parsing, HTML
table reader row-header handling, or Markdown pipe-table parsing. The new
behavior is diagnostics for declared-column overflow after a Pandoc-like AST
table already exists.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc table AST,
`TableGeometry` layout helper, native Markdown writer, and native WordPress
writer. Remaining table follow-up work is richer overlapping grid conflict
reports with explicit source-cell coordinates beyond declared-column overflow.

Root harness: not run - isolated micro-slice.
