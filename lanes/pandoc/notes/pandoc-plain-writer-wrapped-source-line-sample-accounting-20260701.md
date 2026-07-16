# Pandoc Plain Writer Wrapped Source Line Sample Accounting

Implemented one bounded native PHP plain-writer diagnostics slice for wrapped
source-line sample accounting.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports
  `wrappedSourceLineCount`, `wrappedSourceLineSampleLimit`, and
  `wrappedSourceLinesTruncated` alongside the existing bounded
  `wrappedSourceLines` records.
- The wrapped-source-line sample cap is centralized so the per-block collection
  helper and aggregate diagnostics use the same limit.
- Plain writer output is unchanged; the new counters reuse the existing native
  wrapping diagnostics and do not inspect external renderer output.

This slice does not invoke Pandoc, office suites, TeX/browser engines, Typst,
Jupyter, Node tooling, zip/unzip, external validators, online services, or live
provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 243 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - Result: 2 test files, 481 assertions, 0 failures.

## Accounting

- Added one focused PHP behavior case for sample truncation across 18 wrapped
  source lines.
- `lane-status.json` `phpPass` moves from `489` to `490`.
