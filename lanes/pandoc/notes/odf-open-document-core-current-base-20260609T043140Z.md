# ODF OpenDocument Consolidation Metadata

Slice: `pandoc-odf-open-document-core-current-base-20260609T043140Z`
Base: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`

## Implementation

Native `OdfReader` now preserves top-level OpenDocument
`table:consolidation` declarations as metadata-only content declarations:

- `function`
- `source-cell-range-addresses` as both raw text and split source ranges
- `target-cell-address`
- `use-labels`
- `link-to-source-data`
- import-report `consolidationCount` and `consolidationSourceRangeCount`

The reader does not evaluate spreadsheet consolidation formulas or fetch source
ranges. It only makes the source/target consolidation policy visible to
WordPress review/import tooling.

## Verification

Baseline focused ODF reader test before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2951 assertions, 0 failures
```

Red-first focused ODF reader test after adding the assertion:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
FAIL maps ODT consolidation declarations into content declarations
Expected: 2
Actual: NULL
1 test files, 2952 assertions, 1 failures
```

Final focused ODF reader test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2972 assertions, 0 failures
```

WordPress ODF database-field smoke:

```text
php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test
odf database field handoff self-test ok
```

## Status Delta

- `phpPass`: `2305 -> 2306`
- `benchmarkDenominator.mapped`: `2705 -> 2706`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 316`
- Focused ODF assertions: `2951 -> 2972` (`+21`)

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OdfReader` DOM/XML content declaration parsing, focused `OdfReaderTest.php`
coverage, and the WordPress ODF database-field handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF dropdown fields, metadata-field fallbacks,
page-variable/chapter/file/statistic fields, database ranges/subtotal rules,
label ranges, data-pilot tables, named expressions, calculation settings,
content validations, table print ranges, table scenarios, tracked table
changes, annotations, drawing layers, chart/object metadata, or style
inheritance. It is limited to metadata-only `table:consolidation`
declarations.

## Root Harness

Root harness not run - isolated micro-slice.

## Follow-Up

Good follow-up ODF slices: detective metadata, additional data-pilot source
metadata, quoted sheet-name range tokenization, or style-driven table/list
semantics.
