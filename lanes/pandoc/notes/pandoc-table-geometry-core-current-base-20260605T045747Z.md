# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T045747Z`

Base accepted HEAD: `cdb95742bd3c7687d0958af5d550c13a3176c52f`

## Behavior Added

- `TableGeometry::accessibilityAttributes()` now honors source HTML table-cell
  `scope` attributes before falling back to computed Pandoc geometry scopes.
- Source `scope="row"` is row-local even when the row header also has a
  rowspan; computed rowspanned row headers still use `rowgroup`.
- Source `headers` attributes on imported table cells are preserved in the
  accessibility review packet instead of being replaced by computed fallback
  headers.
- The WordPress table handoff smoke now verifies source `scope`/`headers`
  output and the JSON-safe accessibility packet behavior.

## Source Truth

- Uses the existing pinned Pandoc static inventory as source truth for
  attribute-carrying HTML tables and Pandoc-like table AST handoff behavior:
  `test/html-reader.html/native`, `test/tables/nordics.html5`,
  `test/pipe-tables.txt`, and `test/tables.markdown`.
- This slice ports bounded support-library behavior only. It does not invoke
  Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office tooling,
  zip/unzip, external template engines, TeX/PDF engines, browser renderers,
  online sanitizers, or online services.

## Verification

- Pre-fix focused check with the new source scope/headers expectations:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 301 assertions, 2 failures`
  - Failures: source `headers` cells still reported computed headers, and
    source `scope=row` rowspans still behaved like computed rowgroups.
- Focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 317 assertions, 0 failures`
- Focused table geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 379 assertions, 0 failures`
- Affected reader/writer family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `3 test files, 2976 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax and whitespace checks are recorded in the final worker handoff.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `636 -> 637` for the new focused `TableGeometryTest` PASS case.
- Manifest mapped native checks: `1111 -> 1112`.
- Added `mappedTableGeometrySourceAccessibilityCases: 1`.
- Added `tableGeometrySourceAccessibilityAssertions: 11`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, overlap diagnostics, serializable review
packet construction, reader-attached table packets, or opt-in computed
WordPress accessibility rendering. The new behavior is source-aware
accessibility packet semantics for table cells that already carry HTML
`scope` or `headers` attributes.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout/accessibility/review-packet helpers, and native
WordPress writer smoke. Remaining table follow-up work is nested-table packet
rollups, broader normalization reports, malformed source-coordinate
attributes, default accessibility emission policy, and full upstream Pandoc
Haskell runner execution after the pinned checkout and Cabal test executables
are hydrated.
