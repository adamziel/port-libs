# Pandoc Table Geometry Row Header Map Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T102900Z`
Base: `42fdc6ac8852fb015d719b5c26ba483c909bd979`

## Behavior

This slice adds bounded native PHP table row-header review metadata. `TableGeometry::rowHeaderMap()` and `TableGeometry::reviewPacket()['rowHeaderMap']` now expose data-row row-header labels for WordPress/import review packets without changing rendered table HTML.

The map covers row-scope and rowgroup-scope header cells, rowspanned rowgroup headers, source `scope="row"` behavior, generated header IDs, header text/abbr metadata, source cell coordinates, source attributes, per-row unlabeled diagnostics, and compact summary counts for row-header coverage.

## Evidence

- `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`: `1 test files, 993 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`: `2 test files, 1334 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`: `table geometry handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `TableGeometry`, the existing AST/header-association model, the focused PHP test harness, and the WordPress table geometry handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external table writer, browser renderer, online sanitizer, online service, live provider test, or external converter was executed.

## Non-Overlap

This does not rework accepted span normalization, rowHeadColumns rendering, section-boundary rowspan clamping, declared-column overflow diagnostics, source coordinates, row occupancy summaries, header abbreviation metadata, caption handling, colgroup rendering, vertical/inherited alignment, footer diagnostics, or writer requirement diagnostics. It is an additive review-packet metadata surface for row-header labels.

## Follow-Up

Future table geometry work can add writer-specific diagnostics for lossy row-header maps in plain text table writers if a slice explicitly targets writer handoff behavior.
