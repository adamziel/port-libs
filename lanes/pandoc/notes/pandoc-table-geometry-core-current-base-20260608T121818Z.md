# Pandoc Table Geometry Flat Grid Fallbacks

Micro-slice: `pandoc-table-geometry-core-current-base-20260608T121818Z`
Base accepted HEAD: `8b757b752c32a913689c6a0dc988f3a7a453105e`
Date: 2026-06-08 UTC

## Behavior

Added a bounded native table-geometry handoff for importers that consume
flattened visual grids. `TableGeometry::flatGridFallbackDiagnostics()` now
groups `flatGrid` covered slots into an anchor-replay diagnostic and missing
visual slots into an empty-placeholder diagnostic. `reviewPacket()` includes
the same records as `flatGridFallbacks` plus summary counts, codes, sections,
rows, global rows, and columns.

This keeps Markdown/RST writer downgrade diagnostics unchanged while giving
WordPress and other importers enough metadata to serialize or synthesize
covered/missing visual slots in a later target-specific slice.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1600 assertions, 0 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1641 assertions, 0 failures`
- Adjacent reader handoff: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 496 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- PHP lint passed for:
  - `lanes/pandoc/src/TableGeometry.php`
  - `lanes/pandoc/tests/TableGeometryTest.php`
  - `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`

## Status Delta

- `lane-status.json` `phpPass`: `1639 -> 1640`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2059 -> 2060`
- `mappedTableGeometryCoreCases`: `9 -> 10`
- `tableGeometryCoreAssertions`: `155 -> 196`

## Dependency Closure

No new native support component is needed. This slice reuses existing
`TableGeometry` section-grid and `flatGrid` support plus the WordPress table
geometry handoff example. No Pandoc, Cabal/Haskell runner, external writer,
browser renderer, online service, live provider test, or live-service provider
test was executed.

## Next Task

Consume `flatGridFallbacks` in one bounded writer/importer path that either
replays span anchors for covered slots or synthesizes empty placeholders for
missing visual slots for a specific output target.
