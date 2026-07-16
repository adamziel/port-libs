# Pandoc Table Geometry Covered Slot Replay

Slice: `pandoc-table-geometry-core-current-base-20260608T182542Z`
Base: `0ca1726be1212764e1653162e91e283c2a5975b7`

## Scope

This slice adds a bounded WordPress handoff for Pandoc-style flat table grids
where colspan/rowspan cells cover visual slots that do not have standalone cell
nodes. `WordPressBlockWriter` now supports an opt-in
`preserveTableCoveredSlots` option that annotates only span-anchor cells with:

- `data-pandoc-span-anchor="true"`
- `data-pandoc-covered-slot-count`
- `data-pandoc-covered-slots="row:column:covering;..."`

Default WordPress table output remains unchanged. The existing
`preserveTableMissingCells` placeholder behavior remains a separate opt-in and
does not treat covered colspan/rowspan slots as missing cells.

## Status Delta

- `phpPass`: `1712 -> 1713`
- `benchmarkDenominator.mapped`: `2133 -> 2134`
- `mappedTableGeometryCoreCases`: `9 -> 10`
- `tableGeometryCoreAssertions`: `155 -> 168`
- New focused PASS case: `preserves flat grid covered visual slots as opt-in WordPress span-anchor metadata`
- New focused assertions in `TableGeometryTest.php`: `+13`

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1664 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 2195 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- `php -l lanes/pandoc/src/WordPressBlockWriter.php && php -l lanes/pandoc/tests/TableGeometryTest.php && php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - no syntax errors
- `php -r 'json_decode(...)'` for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - both JSON files valid
- `git diff --check -- lanes/pandoc`
  - passed
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The implementation reuses
`TableGeometry::sectionGrid()` occupied-slot metadata and the existing
`WordPressBlockWriter` table-cell attribute path. No Pandoc, Cabal solver/build
or test command, Haskell runner, external writer, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted table-geometry row-group/global-row/source-summary
or header-abbreviation slices. It targets a narrower covered-slot replay gap for
WordPress import/review tools, leaving external writer parity and upstream
Pandoc runner parity out of scope.

## Next

Choose a non-overlapping table-geometry follow-up such as writer-specific raw
HTML fallback diagnostics, additional non-WordPress span-anchor consumers, or
importer-side grid replay validation.
