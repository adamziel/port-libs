# Pandoc Plain Writer Over-Column Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for emitted
lines that exceed the configured plain writer column limit.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  `overColumnLineCount` values.
- Diagnostics also expose `maxOverColumnDisplayWidth`, allowing native review
  packets to identify the widest retained over-column line.
- The focused fixture verifies intentionally preserved over-column output under
  `wrap=none` while keeping existing auto-wrap, protected-separator,
  line-ending, and wrap-control opportunity diagnostics intact.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 57 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 56885 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2820` to `2821`; mapped
focused checks move from `723` to `724`.
