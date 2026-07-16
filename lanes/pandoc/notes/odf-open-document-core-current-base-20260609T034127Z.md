# ODF OpenDocument Table Print Ranges

Slice: `pandoc-odf-open-document-core-current-base-20260609T034127Z`
Base: `6de1d5b33718b9d2dccdce7e31246dedd9031bb9`

## Implementation

Native `OdfReader` now preserves `table:print-ranges` from ODT
`table:table` nodes as metadata-only table review attributes:

- `odfPrintRanges`
- `printRangeCount`
- `data-odf-table-print-range-count`
- `data-odf-table-print-ranges`
- `importReport.content.tablePrintRangeCount`

The value is split on ODF whitespace-separated range tokens, deduplicated, and
kept as reviewer metadata. It does not evaluate or render spreadsheet print
layout and does not invoke office tooling.

## Verification

Baseline focused ODF reader test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2879 assertions, 0 failures
```

Red-first focused ODF reader test after adding the assertion:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2882 assertions, 1 failures
```

Final focused ODF reader test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2888 assertions, 0 failures
```

WordPress ODF handoff smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

PHP syntax checks:

```text
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
```

Result: all reported no syntax errors.

Final JSON and patch hygiene checks:

```text
php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
passed with no output
```

## Status Delta

- `phpPass`: `2240 -> 2241`
- `benchmarkDenominator.mapped`: `2648 -> 2649`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 304`
- Focused ODF assertions: `2879 -> 2888` (`+9`)

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader` DOM/XML
parsing, existing ODT package fixtures, table AST attributes,
`WordPressBlockWriter` table attribute serialization, and focused
`OdfReaderTest.php` coverage.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF dropdown fields, metadata-field fallbacks,
page-variable/chapter/file/statistic fields, database ranges and subtotal
rules, label ranges, data-pilot metadata, named expressions, annotations,
drawing layers, chart/object metadata, or style inheritance. It is limited to
metadata-only table print-range preservation.

## Root Harness

Root harness not run - isolated micro-slice.

## Follow-Up

Good follow-up ODF slices: quoted sheet names in print ranges, additional
data-pilot source metadata, tracked table changes, or style-driven table/list
semantics.
