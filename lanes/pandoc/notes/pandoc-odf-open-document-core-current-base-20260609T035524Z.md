# Pandoc ODF/OpenDocument Table Scenarios

Implemented one bounded ODF/OpenDocument table metadata slice in native PHP.

`OdfReader` now preserves `table:scenario` children on table nodes as review
metadata:

- `table:name`
- `table:display-border`
- `table:border-color`
- `table:copy-back`
- `table:copy-styles`
- `table:copy-formulas`
- `table:is-active`
- `table:scenario-ranges`
- `table:comment`

The handoff records `odfTableScenarios`, `scenarioCount`,
`activeScenarioCount`, import-report `tableScenarioCount` /
`activeTableScenarioCount`, and WordPress table data attributes. Scenario
execution remains metadata-only.

## Verification

Baseline focused run before this slice:

```sh
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
# 1 test files, 2912 assertions, 0 failures
```

Red-first run after adding the focused scenario test and before implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
# 1 test files, 2915 assertions, 1 failures
```

Final focused ODF reader run:

```sh
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
# 1 test files, 2938 assertions, 0 failures
```

WordPress ODF handoff smoke:

```sh
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
# odf open document handoff self-test ok
```

Additional required checks:

```sh
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
git diff --check -- lanes/pandoc
```

## Status Delta

- `phpPass`: `2260 -> 2261`
- `benchmarkDenominator.mapped`: `2665 -> 2666`
- `odfOpenDocumentCoreCases`: `13 -> 14`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 321`
- Focused ODF reader assertions: `2912 -> 2938`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `OdfReader`
DOM parsing, table AST metadata, `WordPressBlockWriter` attribute
serialization, `MarkdownWriter`, `ZipPackage` fixtures, and focused
`OdfReaderTest.php` coverage. Full upstream Pandoc ODT runner parity remains a
separate upstream-runner dependency task requiring hydrated pinned upstream
sources and Haskell test executables.

## Non-Overlap

This avoids accepted ODF/OpenDocument print ranges, line-numbering, note
configuration/footnote separator, content validations, calculation settings,
named ranges/expressions, label ranges, database ranges/subtotals, data-pilot
tables, dynamic fields, tracked table changes, drawing layers, fields, and
table style/cell metadata. It only closes table-level `table:scenario`
metadata preservation for review handoff.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.
