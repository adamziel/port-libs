# Pandoc Table Geometry Flat Grid Handoff

Slice: `pandoc-table-geometry-core-current-base-20260608T114050Z`
Base: `43ff37fb93ab0efcf0387de169c58030c49df953`

## Behavior

This slice adds `TableGeometry::flatGrid()` and exposes the same data under
`reviewPacket()['flatGrid']`. The flat grid is derived from the existing native
section-grid span model, so visual cell, covered, and missing slots share the
same row/column, source-coordinate, rowspan, colspan, and header-cell metadata
used by the existing table geometry packet.

The new packet summary fields roll up flat-grid row, column, slot, covered,
missing, and span-anchor counts for fallback writer/importer decisions that
need a per-visual-slot handoff instead of only anchor-cell coverage records.

## Non-Overlap

This is a bounded table-geometry support-library patch. It does not repeat the
accepted row matrix, row-group range, global row coordinate, header
abbreviation, RST grid-table requirement, block-cell content, footer-section,
caption/source-summary, source colgroup scope, decimal alignment, or colgroup
mismatch slices. It only adds a flattened visual-slot representation reusing
the already accepted section-grid behavior.

## Verification

Red-first evidence before implementation:

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`

Result: failed with undefined `TableGeometry::flatGrid()`,
`1 test files, 1552 assertions, 1 failures`.

Final focused checks:

`php -l lanes/pandoc/src/TableGeometry.php`

`php -l lanes/pandoc/tests/TableGeometryTest.php`

`php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`

Result: `1 test files, 1600 assertions, 0 failures`.

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`

Result: `1 test files, 496 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`

Result: `table geometry handoff self-test ok`.

## Dependency Closure

No new support component is needed. The slice reuses native `TableGeometry`
section grids, review packets, focused PHP tests, and the existing WordPress
table-geometry example. No Pandoc, Cabal solver/build/test command, Haskell
runner, Word, LibreOffice, zip/unzip, external writer, browser renderer,
online service, live provider test, or live-service provider test was run.

Next useful table-geometry work is a bounded writer/importer fallback slice
that consumes `flatGrid` to serialize or downgrade covered/missing visual slots.
