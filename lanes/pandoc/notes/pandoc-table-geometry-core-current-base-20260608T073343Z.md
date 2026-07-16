# pandoc-table-geometry-core-current-base-20260608T073343Z

## Scope

Implemented a bounded table-geometry behavior for explicit source HTML `scope="rowgroup"` headers. The native table accessibility pass now applies a source rowgroup header to every data cell in the same table row group, records the source scope in header association and row-header review packets, and keeps the relationship scoped to the current `tbody`/`tfoot` section.

This does not change computed rowgroup headers derived from Pandoc row-head columns: those remain bounded by the normalized rowspan so existing rowspan geometry behavior is preserved.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 1870 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 1912 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/TableGeometry.php`, `lanes/pandoc/tests/TableGeometryTest.php`, and `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.

## Status Delta

- Adds one mapped native table accessibility and WordPress handoff case.
- Adds one focused PHP PASS case and 42 focused assertions in the table-geometry test pair.
- Updates the lane-local WordPress table-geometry handoff example to cover source rowgroup headers without invoking Pandoc or external writers.

## Dependency Closure

No new support component is needed. This slice reuses the existing native `TableGeometry` accessibility/header-association logic, `WordPressBlockWriter`, focused table geometry tests, and the lane-local WordPress example. No Pandoc executable, Cabal/Haskell runner, Word, LibreOffice, external writer, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice is distinct from prior table geometry work on visual spans, row group ranges, global row coordinates, source summaries, footer writer diagnostics, block cell content, source header abbreviations, duplicate source header IDs, and source `scope="row"`/`headers` references. The new behavior is limited to explicit source HTML `scope="rowgroup"` semantics across the current row group.
