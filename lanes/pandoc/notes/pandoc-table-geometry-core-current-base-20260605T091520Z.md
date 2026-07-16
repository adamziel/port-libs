# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T091520Z`

Base accepted HEAD: `38a8056d43196efeda0675624fee5486cd566d41`

## Behavior Added

- Added bounded HTML column-source provenance for compact table column metadata.
- The native HTML table reader now records a `columnSources` table AST list
  when `<colgroup>` / `<col span>` declarations expand into visual columns.
- `TableGeometry::columnSpecs()` now serializes each visual column's source
  record, and `TableGeometry::cellCoverage()` carries `columnSources` for the
  columns occupied by each cell.
- WordPress table output remains stable while the review packet can audit which
  source `colgroup` and `col` attributes produced each expanded alignment and
  width.

## Source Truth

- Uses the accepted pinned Pandoc static inventory rows for structured HTML
  table parsing and handoff, especially `test/tables/nordics.html5`,
  `test/html-reader.html`, and `test/html-reader.native` table section,
  caption, colgroup, row-header, span, and source-attribute behavior.
- This ports bounded native PHP support-library behavior only. It does not
  invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, zip/unzip, external template engines, TeX/PDF engines, browser
  renderers, online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 140 assertions, 1 failures`
  - Failure: expected the HTML reader to keep expanded source column provenance
    on the table AST; actual `columnSources` was absent.
- Focused reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 157 assertions, 0 failures`
- Focused table geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 548 assertions, 0 failures`
- Focused Markdown/HTML reader plus table handoff:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 2867 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors detected.
  `php -l lanes/pandoc/src/TableGeometry.php`
  - Result: no syntax errors detected.
  `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: no syntax errors detected.
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors detected.
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `798 -> 799`; this slice adds one new focused
  `TableGeometryReaderHandoffTest` PASS case.
- `benchmarkDenominator.mapped`: `1258 -> 1259`.
- Focused reader handoff assertion count moved from `139` to `157`.
- Focused table family assertion count moved from `530` to `548`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 92`.
- Added `mappedTableGeometryHtmlColgroupProvenanceCases: 1`.
- Added `tableGeometryHtmlColgroupProvenanceAssertions: 18`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-cell coordinate diagnostics,
overlap diagnostics, occupied-slot metadata, accessible header relationships,
reader-attached review packets, nested table rollups, source-attribute
serialization, HTML `rowspan=0`, Markdown writer downgrade diagnostics, or
the previous colgroup width/alignment expansion behavior. The new behavior is
source provenance for expanded HTML `colgroup` / `col` column declarations in
the table review-packet contract.

## Dependency Closure

No new support component is needed. This reuses the existing native HTML table
reader, Pandoc-like table AST, `TableGeometry` layout/coverage/review-packet
helpers, and WordPress table handoff smoke. Remaining table follow-up work is
default accessibility emission policy, malformed source-coordinate
normalization, richer column-group mismatch diagnostics, non-HTML writer
downgrade policies, and full upstream Pandoc Haskell runner execution after
the pinned checkout and Cabal test executables are hydrated.
