# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T053006Z`

Base accepted HEAD: `4d91007bafdf12504e3d93f023ba1b74fc3b19ae`

## Behavior Added

- Added nested table rollups to `TableGeometry::reviewPacket()` coverage
  records for cells that contain descendant table AST nodes.
- Each nested table summary is JSON-safe and records the descendant path,
  caption, column counts, section/row/cell counts, header/covered/missing slot
  counts, diagnostics, span presence, and whether that nested table contains
  deeper nested tables.
- Review-packet summaries now expose `nestedTableCount` and
  `nestedTableCellCount`, so importer queues can flag nested table evidence
  without walking the AST.
- Added focused coverage for the upstream `nested-table-to-asciidoc-6942`
  shape, including a third-level nested HTML table imported through
  `MarkdownReader`.
- Updated the WordPress table geometry handoff smoke to prove nested table
  review packets remain serializable while WordPress output keeps the nested
  table HTML visible for reviewers.

## Source Truth

- Uses the existing pinned Pandoc static inventory as source truth for the
  nested HTML table command fixture `test/command/nested-table-to-asciidoc-6942.md`.
- This slice ports the bounded support-library handoff contract only. It does
  not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office
  tooling, tar, zip/unzip, lz4, external template engines, TeX/PDF engines,
  browser renderers, online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 383 assertions, 2 failures`
  - Missing fields: `nestedTableCount` and coverage `nestedTables`.
- Focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 336 assertions, 0 failures`
- Focused table reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 75 assertions, 0 failures`
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 411 assertions, 0 failures`
- Affected reader family:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2610 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7532 assertions, 0 failures`
  - PASS lines: `654`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: remains `654`, matching the measured full focused lane PASS-line
  count from this worktree.
- Manifest mapped native checks: `1130 -> 1132`.
- `mappedTableGeometryCoreCases`: reconciled to `18`.
- `tableGeometryCoreAssertions`: reconciled to `336`.
- Added `mappedTableGeometryReaderHandoffCases: 3` and
  `tableGeometryReaderHandoffAssertions: 75`.
- Added `mappedTableGeometryNestedRollupCases: 2` and
  `tableGeometryNestedRollupAssertions: 32`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, section-scoped rowspans, declared-column overflow
diagnostics, source-cell coordinate diagnostics, section-grid slot reports,
cell coverage metadata, body-local head-row role metadata, source-aware
accessibility, reader-attached review packets, or WordPress nested table
rendering. The new behavior is a JSON-safe nested-table rollup inside the
already accepted review packet.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout/review-packet helpers, `MarkdownReader`, and native
WordPress writer smoke. Remaining table follow-up work is malformed
source-coordinate attribute normalization, default accessibility emission
policy, section-level nested table aggregate reports, writer-specific table
downgrade diagnostics, and full upstream Pandoc Haskell runner execution after
the pinned checkout and Cabal test executables are hydrated.
