# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T015557Z`

Base accepted HEAD: `8de98de08373f006264a82593ed3bdce6dc6d28e`

## Behavior Added

- Added `cell-overlaps-rowspan` diagnostics to `TableGeometry::diagnostics()`.
- The diagnostic reports physical source cells that land under an active
  rowspan and exceed declared table columns after the layout shifts them to a
  later visual column.
- Each report includes section, row, visual column/end column, source cell,
  source column/end column, visual shift, overlap columns, and the rowspanned
  anchor cell that covered the source column.
- WordPress and Markdown table output remain loss-preserving: malformed overlap
  cells stay visible after the rowspanned columns rather than being dropped.

## Source Truth

- Uses the existing static Pandoc table inventory as source truth. Pandoc table
  ASTs carry ordered section rows, colspans, rowspans, row-head columns, and
  body-local head rows; prior accepted slices already ported visual layout,
  section-scoped rowspans, declared-column overflow diagnostics, source-cell
  coordinates, section grids, cell coverage, and row-role metadata.
- This slice ports the bounded support-library handoff contract only. It does
  not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, or online services.

## Verification

- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 177 assertions, 2 failures`
  - Failure: current diagnostics lacked `cell-overlaps-rowspan` reports.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 218 assertions, 0 failures`
  - PASS lines: 12
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5444 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax and metadata:
  - `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`: `pandoc json ok`
  - `git diff --check -- lanes/pandoc`: passed.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 515 -> 516.
- Manifest mapped native checks: 990 -> 991.
- `mappedTableGeometryCoreCases`: 11 -> 12 for the current focused table
  geometry test file.
- `mappedTableGeometryOverlapConflictCases`: 0 -> 1.
- `tableGeometryCoreAssertions`: 184 -> 218 for focused
  `TableGeometryTest.php` coverage.

## Non-Overlap

This does not repeat accepted visual span layout, colspec-width preservation,
row-head-column WordPress output, section-boundary rowspan clamping,
declared-column overflow detection, source-cell coordinate diagnostics,
section-grid slot reports, normalized column specs, cell coverage metadata,
body-local head-row role metadata, DOCX `w:gridSpan` / `w:vMerge` parsing,
DocBook span parsing, HTML-reader table parsing, or Markdown pipe-table
parsing. The new behavior is explicit diagnostics for malformed physical cells
that overlap active rowspans and overflow declared columns after a Pandoc-like
AST table already exists.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout helper, native Markdown writer, and native
WordPress writer. Remaining table follow-up work is importer-level attachment
of table geometry reports to DOCX/ODT/HTML review packets, accessibility
scope/id policy, broader normalization reports, and full upstream Pandoc
Haskell runner execution after the pinned checkout and Cabal test executables
are hydrated.
