# CSV/TSV Direct Text Reader Slice - 2026-06-13

## Scope

This slice moves Pandoc `csv` and `tsv` input tokens from unsupported to
partial native PHP reader coverage. It adds `DelimitedTextReader`, which parses
bounded headed CSV/TSV data into the shared table AST, attaches
`delimitedText` and `tableGeometry` review packets, and proves downstream
Markdown, WordPress table, and Pandoc JSON table export.

## Evidence

- Upstream denominator: 2 CSV command fixture rows from the accepted static
  inventory: `test/command/01.csv` and
  `test/command/3533-rst-csv-tables.csv`.
- Local numerator: 2 focused `DelimitedTextReaderTest.php` cases.
- Focused assertions: 34 for CSV quoting, multiline quoted cells, TSV tab
  splitting, ragged-row padding, table geometry, Markdown export, WordPress
  export, and Pandoc JSON table serialization.
- Registry evidence: `csv` and `tsv` are now `partial` input formats backed by
  `PortLibs\Pandoc\DelimitedTextReader`.

## Verification

- `php -l lanes/pandoc/src/DelimitedTextReader.php`
- `php -l lanes/pandoc/tests/DelimitedTextReaderTest.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed: 2 files, 1055 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed after rebase: 45 files, 74049
  assertions, 0 failures.

No Pandoc binary, spreadsheet application, browser renderer, Node tooling,
online validator, online service, live provider test, or live-service provider
test was invoked.

## Remaining Work

CSV/TSV remains partial, not ship-ready. Follow-up reader parity should cover
broader upstream fixture hydration, malformed input diagnostics, complete option
behavior, and any remaining Pandoc table-reader edge cases.
