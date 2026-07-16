# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260604T232630Z`

Base accepted HEAD: `4e5b254a36b80b692f93413b376a79f6d854dcc7`

## Behavior Added

- Added physical source-cell metadata to `TableGeometry::layoutRows()` entries:
  each placed cell now carries the row-local `sourceCell` index and
  `sourceColumn` offset before active rowspans are skipped.
- Added the same source coordinates to table diagnostics for
  `rowspan-crosses-section-boundary` and `cell-exceeds-declared-columns`.
- Covered a rowspanned malformed import grid where cells are shifted beyond the
  declared Pandoc colspec. WordPress and Markdown output keep those cells
  visible, while diagnostics now identify the physical source cell that caused
  the overflow warning.
- Updated the WordPress table-geometry smoke so reviewer handoff diagnostics
  prove source-cell/source-column coordinates are available.

## Source Truth

- Uses the existing static Pandoc table inventory as source truth: Pandoc table
  ASTs carry ordered rows, cells, row spans, column spans, table sections, and
  colspec metadata. Prior accepted slices already map visual spans,
  row-head-column rendering, section-scoped rowspans, and declared-column
  overflow.
- This slice ports a bounded support-library handoff contract only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, office tools, `zip`/`unzip`,
  TeX/PDF engines, external template engines, browser renderers, Typst, roff,
  or online services.

## Verification

- Red-first check before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: failed on missing `sourceCell` metadata in the new source-coordinate
    test, after 6 existing PASS lines and 75 assertions.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 92 assertions, 0 failures`
  - PASS lines: 7
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `14 test files, 3,751 assertions, 0 failures`
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

- `phpPass`: 393 -> 394.
- mapped native checks: 850 -> 851.
- `mappedTableGeometryCoreCases`: 6 -> 7.
- `mappedTableGeometrySourceCoordinateCases`: 0 -> 1.
- `tableGeometryCoreAssertions`: 74 -> 92.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM support, visual table span layout,
colspec-width preservation, row-head-column WordPress output, section-boundary
rowspan clamping, declared-column overflow detection, DOCX `w:gridSpan` /
`w:vMerge` parsing, DocBook span parsing, HTML-reader row-header handling, or
Markdown pipe-table parsing. The new behavior is diagnostic source-coordinate
metadata after an AST table already exists.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout helper, native Markdown writer, and native
WordPress writer. Remaining table follow-up work is broader normalization
reports across DOCX/ODT/HTML importers and, separately, full upstream Pandoc
Haskell runner execution after the pinned checkout and Cabal test executables
are hydrated.
