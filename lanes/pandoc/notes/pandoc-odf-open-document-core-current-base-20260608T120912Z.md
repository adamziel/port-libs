# ODF OpenDocument Core Current Base: Named Expressions

Slice: `pandoc-odf-open-document-core-current-base-20260608T120912Z`
Base: `dd4c8f52a083993fe65f949b8efa73cb4fa61848`

## Behavior

Native `OdfReader` content declarations now preserve bounded
OpenDocument named-expression metadata from `office:text`:

- `table:named-range` entries with `table:name`,
  `table:cell-range-address`, `table:base-cell-address`, and
  `table:range-usable-as`.
- `table:named-expression` entries with `table:name`,
  `table:expression`, and `table:base-cell-address`.
- `namedExpressionsByName` lookup metadata for deterministic handoff.
- Import-report counters for all named expressions, named ranges, and formula
  expressions.

The data remains metadata-only. This slice does not evaluate spreadsheet
formulas, calculate ranges, execute database ranges, or invoke external
converters.

## Focused Evidence

Baseline before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1896 assertions, 0 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1921 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test
odf database field handoff self-test ok

php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-database-field-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-database-field-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane json ok\n";'
lane json ok
```

Lane JSON was updated to mapped denominator `2058`.
`odfOpenDocumentCoreCases` and `mappedOdfOpenDocumentCoreCases` are now `14`.
`odfOpenDocumentCoreAssertions` is now `320`.
`phpPass` is now `1638`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses existing
`OdfReader` DOM parsing, `ZipPackage` fixture construction, the shared
Pandoc-like AST metadata handoff, `MarkdownWriter`, and
`WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Source Truth And Non-Overlap

No hydrated Pandoc upstream checkout was present in
`/home/claude/port-libs/.upstream-cache/pandoc` for direct runner comparison.
This slice uses the accepted lane ODF support-library contract, existing ODF
fixtures, and the current manifest next-task row that named ODF named
expressions as the next bounded OpenDocument target.

This is additive to the accepted ODF subtotal-rules, database fields,
dropdown, hidden paragraph, conditional/hidden text, heading anchor,
page/statistic field, table, list, section, image, caption, and chart slices.
Follow-up ODF work should stay on distinct metadata surfaces such as
data-pilot metadata, tracked table changes, or style-driven table cell
semantics.

Root harness status: not run - isolated micro-slice.
