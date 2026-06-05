# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T073721Z`

Base accepted HEAD: `b1bc67413271f951f8fd8e1d27ffa2800e27f096`

## Behavior Added

- Added `TableGeometry::writerDowngradeDiagnostics()` for bounded
  writer-specific table geometry reports.
- The Markdown/pipe-table path now reports JSON-safe
  `markdown-colspan-flattened` and `markdown-rowspan-flattened` diagnostics
  for cells whose Pandoc visual spans cannot be represented as true pipe-table
  spans.
- Diagnostics include section, row/source coordinates, visual columns, raw and
  clamped span sizes, and flattened occupied-slot records, so import queues can
  explain Markdown review-table lossiness without changing rendered output.
- `TableGeometry::reviewPacket()` now carries `writerDowngrades.markdown` plus
  summary counts/codes/writers.
- The WordPress table geometry handoff smoke now proves the downgrade metadata
  is exposed in review packets while WordPress table HTML still preserves true
  `colspan`/`rowspan`.

## Source Truth

- Uses the pinned Pandoc static inventory for Markdown writer pipe-table span
  degradation and table span fixtures:
  `test/command/table-with-column-span.md`,
  `test/command/rst-writer-gridtable-if-rowspans.md`,
  `test/markdown-reader-more.native`, and `test/tables.markdown`.
- This ports bounded support-library handoff behavior only. It does not invoke
  Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office tooling,
  zip/unzip, external template engines, TeX/PDF engines, browser renderers,
  online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 372 assertions, 1 failures`
  - Failure: `Call to undefined method PortLibs\Pandoc\TableGeometry::writerDowngradeDiagnostics()`.
- Focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 391 assertions, 0 failures`
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 486 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8713 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `749 -> 750` on the accepted lane counter basis; this slice adds
  one new focused `TableGeometryTest` PASS case.
- Manifest mapped native checks: `1208 -> 1209`.
- `mappedTableGeometryCoreCases`: `6 -> 7`.
- `tableGeometryCoreAssertions`: `74 -> 93`.
- Added `mappedTableGeometryWriterDowngradeCases: 1`.
- Added `tableGeometryWriterDowngradeAssertions: 19`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-cell coordinate diagnostics,
overlap diagnostics, computed/source accessibility handoff, reader-attached
review packets, nested table rollups, section-level nested summaries,
source-attribute serialization, or occupied-slot metadata. The new behavior is
writer-specific Markdown pipe-table downgrade reporting layered onto the
existing table geometry review-packet contract.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `TableGeometry` layout/coverage/review-packet helpers, `MarkdownWriter`
pipe-table behavior, and the native WordPress table handoff smoke. Remaining
table follow-up work is richer writer-specific downgrade policies for other
writers, default accessibility emission policy, malformed source-coordinate
normalization, and full upstream Pandoc Haskell runner execution after the
pinned checkout and Cabal test executables are hydrated.
