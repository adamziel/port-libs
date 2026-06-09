# Pandoc Plain Writer Hard Separator Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for hard line
separator accounting.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  counters for LF, Unicode line separator U+2028, and Unicode paragraph
  separator U+2029 hard breaks.
- The counters reuse `UnicodeText::lineBreakOpportunities()`, keeping the
  diagnostics aligned with the same display-width and wrapping primitives used
  by plain output.
- The focused fixture verifies mixed U+2028, U+2029, and CRLF-normalized LF
  input while preserving the existing wrapping output and line-ending
  normalization counters.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 82 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 57443 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2849` to `2850`; mapped
focused checks move from `752` to `753`.
