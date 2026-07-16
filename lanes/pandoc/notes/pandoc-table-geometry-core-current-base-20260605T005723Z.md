# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T005723Z`

Base accepted HEAD: `7540826ac519127ba0af4ff2e45becada21283a0`

## Behavior Added

- Added `TableGeometry::cellCoverage()` for per-cell table geometry reports
  after Pandoc-like AST tables have been laid out by visual column.
- Each report includes section name, row index, visual anchor column, covered
  visual columns, exclusive end columns, raw versus clamped row/column spans,
  physical source cell/source column coordinates, resolved cell alignment,
  covered colspec alignments, widths, declared-column flags, and the original
  cell node.
- The report preserves the accepted malformed-table behavior: over-wide cells
  stay visible and can expand into implicit visual columns, while reviewer
  audits can still see which covered columns were declared by Pandoc colspecs.
- Updated the WordPress table-geometry smoke so import-review packets prove the
  coverage audit is available without changing rendered table HTML.

## Source Truth

- Uses the existing static Pandoc table inventory as source truth: Pandoc table
  ASTs carry table sections, colspec alignment/width metadata, row spans,
  column spans, and section/body-local rows. Prior accepted slices already map
  visual span layout, row-head-column WordPress output, section-scoped
  rowspans, declared-column diagnostics, source-cell coordinates, section-grid
  slot reports, and normalized column specs.
- This slice ports a bounded support-library handoff contract only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, office tools, `zip`/`unzip`,
  TeX/PDF engines, external template engines, browser renderers, roff, Typst,
  MathJax, KaTeX, bibliography managers, or online services.

## Verification

- Baseline before editing:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 128 assertions, 0 failures`
- Red-first check after adding the cell-coverage expectation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: failed with `1 test files, 128 assertions, 1 failures` because
    `TableGeometry::cellCoverage()` was missing.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 159 assertions, 0 failures`
  - PASS lines: 10
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4921 assertions, 0 failures`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 482 -> 483.
- Manifest mapped native checks: 955 -> 956.
- `mappedTableGeometryCoreCases`: reconciled to 10 current focused cases.
- `mappedTableGeometryCellCoverageCases`: 0 -> 1.
- `tableGeometryCoreAssertions`: 128 -> 159 for the focused
  `TableGeometryTest.php` baseline.

## Non-Overlap

This does not repeat accepted visual span layout, colspec-width preservation,
row-head-column WordPress output, section-boundary rowspan clamping,
declared-column overflow detection, source-cell coordinate diagnostics,
section-grid slot reports, normalized colspec metadata, DOCX `w:gridSpan` /
`w:vMerge` parsing, DocBook span parsing, HTML-reader row-header handling, or
Markdown pipe-table parsing. The new behavior is a per-cell coverage report
combining those geometry primitives for importer and WordPress review audits.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout helper, native Markdown writer, and native
WordPress writer. Remaining table follow-up work is importer-level attachment
of cell coverage reports to DOCX/ODT/HTML review packets, richer overlap
conflict diagnostics, and a separate accessibility scope policy. Full upstream
Pandoc Haskell runner execution remains blocked until the pinned checkout and
Cabal test executables are hydrated.
