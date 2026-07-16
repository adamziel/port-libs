# Pandoc Plain Writer Soft-Wrap Break Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for output
line breaks inserted by `wrap=auto`.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  `softWrapBreakCount` values.
- The count compares emitted wrapped lines with source physical lines under the
  same Unicode hard-line boundary rules used by `UnicodeText::wrapByDisplayWidth()`.
- Source hard breaks, Unicode hard separators, tabs, protected separators, and
  control break opportunities remain reported through their existing counters.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 127 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 58127 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2888` to `2889`; mapped
focused checks move from `791` to `792`; `UPSTREAM_TEST_MANIFEST.json` mapped
denominator moves from `3085` to `3086`.
