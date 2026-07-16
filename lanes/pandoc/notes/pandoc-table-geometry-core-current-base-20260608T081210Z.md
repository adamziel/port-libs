# pandoc-table-geometry-core-current-base-20260608T081210Z

## Scope

Implemented a bounded table-geometry behavior for explicit source HTML `scope="colgroup"` headers. The native accessibility pass now expands those source headers through parsed `columnGroups()` provenance, so a header cell imported from a `<colgroup span="2">` can label every data cell in that source column group even when the header cell itself occupies only one physical column.

The expanded source colgroup columns are serialized into accessibility attributes, header association packets, and row-matrix records. Computed Pandoc colgroup headers that come from normal visual colspans still use their physical cell span.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 1912 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` failed as expected with `1 test files, 396 assertions, 1 failures` because the source `scope="colgroup"` header had no expanded parsed-colgroup columns.
- Final focused tests: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 1976 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/TableGeometry.php`, `lanes/pandoc/tests/TableGeometryTest.php`, `lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`, and `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- Adds one mapped native table accessibility and WordPress handoff case.
- Adds two focused PHP PASS cases and 64 focused assertions in the table-geometry test pair.
- Updates the lane-local WordPress table-geometry handoff example to cover source colgroup headers without invoking Pandoc or external writers.

## Dependency Closure

No new support component is needed. This slice reuses existing native `TableGeometry` column-group provenance, accessibility/header-association logic, `MarkdownReader` HTML table metadata, `WordPressBlockWriter`, focused table geometry tests, and the lane-local WordPress example. No Pandoc executable, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external writer, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice is distinct from prior table geometry work on visual spans, colgroup provenance/writer diagnostics, row group ranges, global row coordinates, source summaries, footer writer diagnostics, block cell content, source header abbreviations, duplicate source header IDs, source `scope="row"`/`headers` references, and explicit source `scope="rowgroup"` semantics. The new behavior is limited to explicit source HTML `scope="colgroup"` semantics across the parsed source column group.
