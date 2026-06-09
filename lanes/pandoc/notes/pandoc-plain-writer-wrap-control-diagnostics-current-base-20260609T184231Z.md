# Pandoc Plain Writer Wrap-Control Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for wrap-control
break opportunities.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  counts for zero-width-space, soft-hyphen, and visible break-after
  opportunities.
- The counters reuse `UnicodeText::lineBreakOpportunities()`, so they match the
  same display-width and wrapping rules already used by plain output.
- The focused fixture verifies soft-hyphen wrapping, zero-width-space splitting,
  and visible break-after handling while preserving the previous protected
  separator and line-ending normalization diagnostics.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 45 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 56834 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2816` to `2817`; mapped
focused checks move from `719` to `720`.
