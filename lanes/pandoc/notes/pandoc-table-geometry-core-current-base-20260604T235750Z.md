# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260604T235750Z`

Base accepted HEAD: `cb214968d9bd3ff69bbd7647088f7e06c337b6a2`

## Behavior Added

- Added `TableGeometry::sectionGrids()` for whole-table section reports and
  `TableGeometry::sectionGrid()` for one row group.
- The grid report marks every visual slot as an anchor `cell`, a `covered`
  colspan/rowspan/rowspan-colspan slot, or a true `missing` slot. Covered slots
  carry the anchor row/column, source cell/source column, colspan, rowspan, and
  cell node.
- Covered the WordPress review path without changing rendered table HTML: the
  existing table-geometry handoff smoke now proves normalized head/body grids
  expose colspan-covered, rowspan-covered, and missing trailing slots.

## Source Truth

- Uses the existing static Pandoc table inventory as source truth: Pandoc table
  ASTs carry ordered table sections, colspec metadata, row spans, and column
  spans. Prior accepted slices already map visual spans, row-head-column output,
  section-scoped rowspans, declared-column overflow, and source-cell
  coordinates.
- This slice ports a bounded support-library handoff contract only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, office tools, `zip`/`unzip`,
  TeX/PDF engines, external template engines, browser renderers, roff, Typst,
  MathJax, KaTeX, or online services.

## Verification

- Red-first check before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: failed on missing `TableGeometry::sectionGrids()` after 7 PASS
    lines and 92 assertions.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 116 assertions, 0 failures`
  - PASS lines: 8
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `17 test files, 4,200 assertions, 0 failures`
- `php -l lanes/pandoc/src/TableGeometry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: clean.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 430 -> 431.
- Manifest mapped native checks: 898 -> 899.
- `mappedTableGeometryCoreCases`: 6 -> 8. The current accepted test file
  already had the source-coordinate case, so this also reconciles the lagging
  manifest counter while this slice adds the new section-grid case.
- `mappedTableGeometrySectionGridCases`: 0 -> 1.
- `tableGeometryCoreAssertions`: 74 -> 116 in the manifest; the new focused
  test adds 24 assertions over the current accepted `TableGeometryTest.php`
  baseline of 92 assertions.

## Non-Overlap

This does not repeat accepted visual span layout, colspec-width preservation,
row-head-column WordPress output, section-boundary rowspan clamping,
declared-column overflow detection, source-cell coordinate diagnostics, DOCX
`w:gridSpan` / `w:vMerge` parsing, DocBook span parsing, HTML-reader row-header
handling, or Markdown pipe-table parsing. The new behavior is a normalized
visual-slot audit report after a Pandoc-like AST table already exists.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout helper, native Markdown writer, and native
WordPress writer. Remaining table follow-up work is importer-level wiring that
attaches these section-grid reports to DOCX/ODT/HTML review packets, plus full
upstream Pandoc Haskell runner execution after the pinned checkout and Cabal
test executables are hydrated.
