# pandoc-table-geometry-core-current-base-20260608T061953Z

## Scope

Implemented one bounded table-geometry support-library behavior cluster on accepted base `2d05ed88b1dffcb6c3d210fc88a0ebf93fb3fb5a`: row-oriented visual matrix handoff metadata for accessible table review packets.

The slice adds `TableGeometry::rowMatrix()` and wires the same matrix into `reviewPacket()`. The matrix reuses existing section grids and header associations, preserving:

- table section and section-local row coordinates;
- shared global row coordinates across head/body/foot sections;
- header/data cell separation;
- source header IDs and source header references;
- covered rowspan slots with anchor keys;
- missing slot counts;
- row-matrix summary counters surfaced in the review-packet summary.

## Red-First Evidence

After adding the focused test and before implementing the behavior:

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`

failed as expected:

`FAIL builds row-oriented visual matrices with header association handoff metadata`

`Call to undefined method PortLibs\Pandoc\TableGeometry::rowMatrix()`

Count: `1 test files, 1417 assertions, 1 failures`.

## Final Focused Evidence

Focused table test:

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`

passed with `1 test files, 1477 assertions, 0 failures`.

Table family:

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`

passed with `2 test files, 1870 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`

passed with `table geometry handoff self-test ok`.

This maps one additional native table-geometry support case, increases lane `phpPass` from `1548` to `1549`, increases `benchmarkDenominator.mapped` from `1969` to `1970`, increases `mappedTableGeometryCoreCases` from `9` to `10`, and adds `+60` focused TableGeometry assertions.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `TableGeometry` section grid, header-association, source-attribute, row-header-map, review-packet, focused test, and WordPress table geometry handoff example paths.

No Pandoc, Cabal/Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted table geometry slices for RST grid-table writer requirements, block-cell content handoff, footer-section writer diagnostics, header abbreviation metadata, row-group ranges, global row coordinates, or source-summary preservation. The added behavior is the row-oriented matrix contract that lets reviewers consume existing span and header metadata without reconstructing the visual grid.

## Follow-Up

Useful follow-up should stay in table geometry and avoid external writers: writer-specific matrix degradation metadata, additional malformed span diagnostics, or parser handoff coverage for source table section attributes.
