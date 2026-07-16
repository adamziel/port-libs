# Pandoc Plain Writer Blank-Line Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for blank
line accounting during wrapping.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  `blankSourceLineCount` and `blankOutputLineCount` values.
- The new counters make intentionally blank plain output lines visible to
  native review packets while preserving the existing emitted plain text.
- The focused fixture verifies wrapped code-block output that retains a blank
  physical line between wrapped non-empty lines.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 140 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 58542 assertions, 0 failures.

## Accounting

- `lane-status.json` `phpPass`: `2913 -> 2914`.
- `lane-status.json` mapped focused checks: `816 -> 817`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3098 -> 3099`.
