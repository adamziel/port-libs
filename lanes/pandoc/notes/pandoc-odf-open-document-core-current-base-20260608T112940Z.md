# ODF OpenDocument Core Current Base: Database Subtotal Rules

Slice: `pandoc-odf-open-document-core-current-base-20260608T112940Z`
Base: `755c39728fec0f9184818a939c6ff56a92152616`

## Behavior

Native `OdfReader` database-range metadata now preserves bounded
OpenDocument subtotal declarations:

- `table:subtotal-rules` policy flags:
  `table:bind-styles-to-content`, `table:case-sensitive`, and
  `table:page-breaks-on-group-change`.
- `table:sort-groups` review ordering metadata with nested `table:sort-by`.
- `table:subtotal-rule` group-by field numbers.
- `table:subtotal-field` field numbers and subtotal functions.

The data remains metadata-only. This slice does not execute database ranges,
spreadsheet formula evaluation, subtotal calculation, or external converters.

## Focused Evidence

Baseline before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1877 assertions, 0 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1896 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test
odf database field handoff self-test ok

php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-database-field-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-database-field-handoff.php
```

Lane JSON was updated to mapped denominator `2049`.
`odfOpenDocumentCoreCases` and `mappedOdfOpenDocumentCoreCases` are now `13`.
`odfOpenDocumentCoreAssertions` is now `295`.
`phpPass` remains `1629` because this extends an existing focused TestRunner
case rather than adding a new named PASS case.

## Dependency Closure

No new native PHP support component is needed. The slice reuses existing
`OdfReader` DOM parsing, `ZipPackage` fixture construction, the shared
Pandoc-like AST metadata handoff, and `WordPressBlockWriter` review output.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap And Follow-Up

This is additive to existing ODF database field/range metadata and does not
repeat recent ODF dropdown, hidden-paragraph, conditional/hidden text, heading
anchor, or page/statistic field slices.

Good follow-up ODF slices: named expressions, data-pilot metadata, tracked
table changes, or style-driven table cell semantics.
