# Pandoc Table Geometry Header Associations

Slice: `pandoc-table-geometry-core-current-base-20260606T012245Z`
Session: `port-dev-pandoc-table-geometry-20260606T012245Z`
Base accepted HEAD: `21883d1cce6e5a3b0da2d2fd54a53e5c7dee4fe1`

## Behavior

This slice adds a bounded native table-geometry review-packet behavior:
`TableGeometry::headerAssociations()` now serializes the header/data-cell
relationship graph that was previously only available as WordPress table
attributes. The packet includes header-cell ids, scope, row/column geometry,
resolved data-cell headers, source header overrides, and summary counters.
`TableGeometry::reviewPacket()` carries the same association packet when
accessibility metadata is enabled, so reviewer tooling can audit complex
header relationships before writer-specific lowering.

The WordPress table geometry handoff example now self-tests those association
counts and source-header override fields. The rendered WordPress table behavior
is unchanged.

Accepted clusters avoided: existing span-grid/colspec behavior, section-grid
coverage, row-head WordPress output, source attribute preservation, captions,
nested rollups, writer downgrade diagnostics, footer diagnostics, and existing
WordPress accessibility attributes.

## Red-First Evidence

Before the implementation, the focused table test failed on the new behavior:

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`

Result: `1 test files, 737 assertions, 1 failures`

Failure: `Call to undefined method PortLibs\Pandoc\TableGeometry::headerAssociations()`

## Verification

`php -l lanes/pandoc/src/TableGeometry.php`

Result: `No syntax errors detected in lanes/pandoc/src/TableGeometry.php`

`php -l lanes/pandoc/tests/TableGeometryTest.php`

Result: `No syntax errors detected in lanes/pandoc/tests/TableGeometryTest.php`

`php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`

Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php`

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`

Result: `2 test files, 1079 assertions, 0 failures`

`php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`

Result: `table geometry handoff self-test ok`

`git diff --check -- lanes/pandoc`

Result: clean

Root harness: not run - isolated micro-slice.

## Status Delta

Focused `TableGeometryTest.php` gained one PASS case and 28 passing assertions
for header association serialization. `lane-status.json` `phpPass` moves from
`1137` to `1138`.

## Dependency Closure

No new support component is required. The slice reuses native PHP AST nodes,
`TableGeometry` section/accessibility grids, and the existing WordPress table
handoff example.

Full upstream Pandoc/Haskell table golden parity remains outside this
micro-slice and still depends on a hydrated Pandoc checkout and Cabal/Tasty
runner closure. No Pandoc, Cabal solver/build/test command, Haskell runner,
external writer, browser renderer, online sanitizer, online service, or live
provider test was executed.

## Next Task

Keep writer-specific AST lowering beyond review-packet diagnostics and full
upstream Haskell table golden runner parity as separate bounded slices.
