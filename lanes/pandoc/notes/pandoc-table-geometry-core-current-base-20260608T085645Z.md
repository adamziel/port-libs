# Pandoc Table Geometry Header Axis Handoff - 2026-06-08

Slice: `pandoc-table-geometry-core-current-base-20260608T085645Z`
Base: `f0ab9f09ee4c07b41223f5f4b712e9f9688694c6`

## Behavior

- Added bounded source HTML `th axis` metadata handoff for table geometry.
- `TableGeometry::headerAssociations()` now exposes `axis`, `headerAxisCount`, `hasHeaderAxes`, and ordered `headerAxes`.
- Explicit `headers` source references now include `targetAxis` for resolved header targets.
- Row header maps and row matrices carry header `axis` metadata.
- Markdown, AsciiDoc, and LaTeX writer downgrade diagnostics now report header-axis review requirements.
- WordPress table output preserves safe legacy `axis` attributes on table cells.

## Source Truth And Non-Overlap

- Source truth came from the accepted static Pandoc inventory and existing lane HTML table reader/WordPress handoff fixtures. The local `/home/claude/port-libs/.upstream-cache` did not contain a `pandoc` checkout for additional fixture reads.
- This slice deliberately avoided already accepted table geometry surfaces: source `scope`, source `headers`, duplicate header ids, `abbr`, colgroup/rowgroup scoped headers, source summary, row matrices, global row coordinates, body head rows, RST grid-table writer diagnostics, and block-cell writer diagnostics.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

## Focused Evidence

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries html table header axis metadata into geometry and wordpress handoff
Values are not identical
Expected: 3
Actual: NULL
1 test files, 429 assertions, 1 failures
```

Final focused runs:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 460 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 2012 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

Final verification:

```text
php -l lanes/pandoc/src/TableGeometry.php
No syntax errors detected in lanes/pandoc/src/TableGeometry.php
php -l lanes/pandoc/src/WordPressBlockWriter.php
No syntax errors detected in lanes/pandoc/src/WordPressBlockWriter.php
php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
No syntax errors detected in lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok
git diff --check -- lanes/pandoc
0 issues
```

## Status Delta

- `phpPass`: `1586 -> 1587`
- `benchmarkDenominator.mapped`: `2006 -> 2007`
- `mappedTableGeometryCoreCases`: `9 -> 10`
- `tableGeometryCoreAssertions`: `155 -> 191`
- New focused assertion delta: `+36` in `TableGeometryReaderHandoffTest.php`.

## Dependency Closure

No new support component is needed. This reuses the existing native `MarkdownReader` HTML table path, `TableGeometry` review-packet helpers, and `WordPressBlockWriter` safe table-attribute renderer. The next non-overlapping table geometry work should stay in bounded native metadata/writer handoff behavior, not external Pandoc or writer execution.
