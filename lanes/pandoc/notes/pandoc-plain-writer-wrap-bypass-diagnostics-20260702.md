# Pandoc Plain Writer Wrap Bypass Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for wrap-mode
bypass accounting.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate
  `wrapBypassLineCount` and `maxWrapBypassDisplayWidth` values.
- The counters identify source lines that exceed the configured column width
  while automatic wrapping is deliberately bypassed by `wrap=none` or
  `wrap=preserve`.
- Plain writer output is unchanged; the diagnostics reuse native display-width
  measurement and do not inspect external renderer output.

This slice does not invoke Pandoc, office suites, TeX/browser engines, Typst,
Jupyter, Node tooling, zip/unzip, external validators, online services, or live
provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 260 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - Result: 2 test files, 620 assertions, 0 failures.

## Accounting

- Added one focused PHP behavior case for `wrap=preserve` over-column bypass
  diagnostics.
- `lane-status.json` `phpPass` moves from `490` to `491`.
