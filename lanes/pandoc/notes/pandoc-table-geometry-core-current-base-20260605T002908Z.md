# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T002908Z`

Base accepted HEAD: `810d0706bf9e20b666c6562cd776779e2c68b0d5`

## Behavior Added

- Added `TableGeometry::columnSpecs()` for normalized Pandoc colspec handoff
  metadata by visual column.
- Each spec reports the visual `column`, normalized `alignment`, optional
  positive numeric `width`, and whether the column was explicitly declared by
  table alignments or widths rather than inferred from row geometry.
- Reused the helper in WordPress colgroup width rendering so existing rendered
  table markup stays stable while geometry audits and writer output share one
  normalization path.
- Updated the WordPress table geometry smoke to prove reviewer handoff packets
  can inspect declared and implicit column specs without invoking external
  converters.

## Source Truth

- Uses the existing static Pandoc table inventory as source truth: Pandoc table
  ASTs carry ordered rows, spans, section groups, and colspec alignment/width
  metadata. Prior accepted slices already map visual spans, row-head-column
  output, section-scoped rowspans, source-cell coordinates, declared-column
  diagnostics, and section grids.
- This slice ports a bounded support-library handoff contract only. It does
  not invoke Pandoc, Cabal, Haskell test binaries, office tools, `zip`/`unzip`,
  TeX/PDF engines, external template engines, browser renderers, roff, Typst,
  MathJax, KaTeX, or online services.

## Verification

- Baseline before editing:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 116 assertions, 0 failures`
- Red-first check after adding the column-spec expectation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: failed on missing `TableGeometry::columnSpecs()` after 108
    assertions and 1 failure.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 128 assertions, 0 failures`
  - PASS lines: 9
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4634 assertions, 0 failures`
- `php -l lanes/pandoc/src/TableGeometry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 463 -> 464.
- Manifest mapped native checks: 934 -> 935.
- `mappedTableGeometryCoreCases`: reconciled to 9 current focused cases.
- `mappedTableGeometryColumnSpecCases`: 0 -> 1.
- `tableGeometryCoreAssertions`: 116 -> 128 for the focused
  `TableGeometryTest.php` baseline.

## Non-Overlap

This does not repeat accepted visual span layout, colspec-width preservation,
row-head-column WordPress output, section-boundary rowspan clamping,
declared-column overflow detection, source-cell coordinate diagnostics,
section-grid slot reports, DOCX `w:gridSpan` / `w:vMerge` parsing, DocBook
span parsing, HTML-reader row-header handling, or Markdown pipe-table parsing.
The new behavior is normalized colspec metadata after a Pandoc-like AST table
already exists.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout helper, native Markdown writer, and native
WordPress writer. Remaining table follow-up work is importer-level wiring that
attaches table geometry and column-spec reports to DOCX/ODT/HTML review
packets, plus full upstream Pandoc Haskell runner execution after the pinned
checkout and Cabal test executables are hydrated.
