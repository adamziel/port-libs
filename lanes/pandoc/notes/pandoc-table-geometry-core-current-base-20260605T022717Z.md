# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T022717Z`

Base accepted HEAD: `3f2e4896500806fc52ab80fde6ec67a27c93f816`

## Behavior Added

- Added `TableGeometry::accessibilityAttributes()` and
  `TableGeometry::accessibilityKey()` for bounded table accessibility handoff.
- The geometry layer now derives stable header cell IDs, `scope` values, and
  data-cell `headers` lists from Pandoc-like visual table layout.
- Header relationships cross visual colspans, body-local `TableBody` head rows,
  and rowspanned row-header cells, so importer review tables can preserve
  column and row associations after span normalization.
- `WordPressBlockWriter` emits computed `id`, `scope`, and `headers`
  attributes only when a table opts in via `accessibilityHeaders` or an
  `accessibilityIdPrefix`, preserving existing default WordPress output.
- Updated the table geometry WordPress smoke with an opt-in accessible review
  grid.

## Source Truth

- Uses the accepted static Pandoc table inventory as source truth. Pandoc table
  ASTs preserve ordered head/body/foot sections, `TableBody` local head rows,
  row-head columns, colspans, rowspans, and cell attributes.
- This slice ports the bounded support-library handoff contract only. It does
  not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, or online services.

## Verification

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 218 assertions, 0 failures`
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 236 assertions, 0 failures`
  - PASS lines: 13
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5755 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax and metadata:
  - `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors.
  - `php -l lanes/pandoc/src/WordPressBlockWriter.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`: `pandoc json ok`
  - `git diff --check -- lanes/pandoc`: passed.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 538 -> 539.
- Manifest mapped native checks: 1016 -> 1017.
- `mappedTableGeometryCoreCases`: 6 -> 13, reconciled to the current focused
  table geometry test file.
- `mappedTableGeometryAccessibilityCases`: 0 -> 1.
- `tableGeometryCoreAssertions`: 74 -> 236, reconciled to the current focused
  table geometry coverage.

## Non-Overlap

This does not repeat accepted visual span layout, colspec-width preservation,
row-head-column WordPress output, section-boundary rowspan clamping,
declared-column overflow detection, source-cell coordinate diagnostics,
section-grid slot reports, normalized column specs, cell coverage metadata,
body-local head-row role metadata, or overlap diagnostics. The new behavior is
the accessibility relationship policy layered on top of the existing visual
grid.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout helper, and native WordPress writer. Remaining
table follow-up work is importer-level attachment of section grid, coverage,
and accessibility reports to DOCX/ODT/HTML review packets, richer overlap
conflict diagnostics, default reader policy for accessibility emission, and
full upstream Pandoc Haskell runner execution after the pinned checkout and
Cabal test executables are hydrated.
