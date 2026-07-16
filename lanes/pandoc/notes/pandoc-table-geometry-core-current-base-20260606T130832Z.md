# Pandoc Table Geometry Source Coordinate Shift Records

Slice: `pandoc-table-geometry-core-current-base-20260606T130832Z`
Base accepted HEAD: `eecc865658e5cd10e8284e626f10d8b8a1b3a078`

## Behavior

`TableGeometry::reviewPacket()` now includes a top-level
`sourceCoordinateShifts` list. Each record is a JSON-safe audit projection for
cells whose source physical column differs from the visual output column after
rowspan layout normalization.

The records carry section, row role, visual columns, source cell/column spans,
visual shift distance, normalized/raw span metadata, header markers, row-head
column count, text, and source attributes when present. This lets importer and
WordPress review code inspect only shifted cells instead of scanning the full
coverage list while leaving rendered Markdown and WordPress table output
unchanged.

## Source Truth

This builds on the accepted table-coordinate contract already mapped from the
pinned Pandoc table fixtures and source rows in
`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, including pipe/simple/native table
fixtures, HTML-reader table sections, rowspans, and writer handoff behavior.
The behavior is implemented as a native PHP projection over existing
`TableGeometry::cellCoverage()` output, so it does not introduce a new layout
engine or external writer dependency.

## Verification

Baseline before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 1446 assertions, 0 failures`

Focused after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 1468 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- `php -l lanes/pandoc/src/TableGeometry.php`
  - `No syntax errors detected in lanes/pandoc/src/TableGeometry.php`
- `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/TableGeometryTest.php`
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php`

Final JSON validation and whitespace checks were run after metadata updates.
Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1332 -> 1333`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1746 -> 1747`.
- `mappedTableGeometryCoreCases`: `8 -> 9`.
- `tableGeometryCoreAssertions`: `143 -> 165`.
- Added `mappedTableGeometrySourceCoordinateShiftRecordCases: 1`.
- Added `tableGeometrySourceCoordinateShiftRecordAssertions: 22`.
- Focused table family: `1446 -> 1468` assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `AstNode`,
`TableGeometry`, `MarkdownWriter`, `WordPressBlockWriter`, and the focused lane
test harness.

No Pandoc, Cabal, Haskell runner, external table writer, Word, LibreOffice,
zip/unzip, browser renderer, online sanitizer, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted source-coordinate coverage fields and summary
counts, source-attribute writer diagnostics, caption source handoff, header
abbreviation handoff, row occupancy summaries, row-header maps, row-header
writer diagnostics, multiple body writer diagnostics, or body-local head-row
writer diagnostics. The new behavior is limited to a first-class
review-packet audit list for shifted source coordinates.

## Follow-Up

Keep writer-specific remediation, full upstream Pandoc Haskell runner parity,
and richer importer UI grouping for shifted rows/sections as separate bounded
slices.
