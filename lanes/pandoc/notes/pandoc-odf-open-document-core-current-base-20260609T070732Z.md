# ODF OpenDocument Core Current Base: Sheet And Formula Fields

Slice: `pandoc-odf-open-document-core-current-base-20260609T070732Z`
Base: `030e94cf137586963da96dca64555cebe2ff01ee`

## Behavior

Native `OdfReader` now maps bounded OpenDocument spreadsheet inline fields
into reviewable AST spans:

- `text:sheet-name` with `table:table-name` metadata.
- `text:table-formula` with `text:formula`,
  `table:cell-range-address`, `office:value-type`, `office:value`, and
  `style:data-style-name` metadata.

Empty `text:sheet-name` fields now fall back to the table name, so
metadata-only sheet markers remain visible in Markdown and WordPress review
output. Spreadsheet formula evaluation and recalculation remain out of scope;
this slice preserves source metadata only.

## Focused Evidence

Baseline before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3204 assertions, 0 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3235 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok

php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
```

Lane JSON was updated to mapped denominator `2852`.
`odfOpenDocumentCoreCases` and `mappedOdfOpenDocumentCoreCases` are now `14`.
`odfOpenDocumentCoreAssertions` is now `326`.
`phpPass` is now `2471`, adding one focused ODF PASS case.

## Dependency Closure

No new native PHP support component is needed. The slice reuses existing
`OdfReader` DOM parsing, `ZipPackage` fixture construction, the shared
Pandoc-like AST metadata handoff, and `WordPressBlockWriter` review output.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap And Follow-Up

This is additive to existing ODF page-variable, page-continuation, template,
line-number, data-pilot, subtotal-rule, and table-cell formula metadata slices.
It specifically covers the remaining inline `text:sheet-name` and
`text:table-formula` field handoff path.

Good follow-up ODF slices: RDF metadata extraction, formula evaluation
diagnostics as metadata, data-pilot source ranges, or tracked table changes.
