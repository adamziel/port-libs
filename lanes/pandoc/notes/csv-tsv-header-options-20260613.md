# CSV/TSV Header Option Slice - 2026-06-13

## Scope

This follow-up extends the bounded native PHP CSV/TSV direct reader with
explicit header option handling. `DelimitedTextReader` now accepts
`header => false` for CSV and TSV imports, keeps every source row in the table
body, emits an empty table head for downstream Pandoc JSON compatibility, and
records generated column labels plus an informational no-header diagnostic in
the `delimitedText` review packet.

The default remains `header => true`, preserving the first-row header behavior
from the initial direct reader slice.

## Evidence

- Upstream denominator: 2 CSV/TSV command fixture rows from the accepted static
  inventory: `test/command/01.csv` and
  `test/command/3533-rst-csv-tables.csv`.
- Previous local numerator on current main `48c0f4517a`: 3,333 PHP passes / 0
  failures and 3,292 mapped upstream cases.
- New local numerator after adding this follow-up: 3,335 PHP passes / 0 failures
  and 3,294 mapped upstream cases.
- Header-option evidence: 2 focused cases, 27 focused assertions covering
  CSV no-header import, TSV no-header import, generated column labels, empty
  table heads, body-row preservation, Markdown export, WordPress export, Pandoc
  JSON table-head shape, and non-boolean option rejection.

## Verification

- `php -l lanes/pandoc/src/DelimitedTextReader.php`
- `php -l lanes/pandoc/tests/DelimitedTextReaderTest.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed: 2 files, 1410 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 45 files, 75039 assertions, 0 failures.

No Pandoc binary, spreadsheet application, browser renderer, Node tooling,
online validator, online service, live provider test, or live-service provider
test was invoked.

## Remaining Work

CSV/TSV remains partial, not ship-ready. Remaining bounded gaps include broader
upstream fixture hydration, delimiter/quote option variants, malformed input and
multiline-cell policy diagnostics, table caption/metadata handoff, and any
writer/export parity edges not covered by the current Markdown, WordPress, and
Pandoc JSON table export checks.
