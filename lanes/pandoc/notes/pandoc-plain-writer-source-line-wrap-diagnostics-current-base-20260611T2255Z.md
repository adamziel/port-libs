# Pandoc Plain Writer Source-Line Wrap Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for source-line
wrapping samples.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now materializes the existing
  `wrappedSourceLines` diagnostics payload instead of leaving the aggregate
  fields without an implementation.
- Each sampled wrapped source line records the block index, source line index,
  source display width, emitted output line count, generated break count, forced
  wrap break count, and a bounded text sample.
- The helper uses the same `preg_split('/\R/u')` source-line boundary and
  `UnicodeText::wrapByDisplayWidth()` wrapping behavior as the existing
  `wrapSplitLineCount` and `generatedWrapBreakCount` counters.

This slice does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser
renderers, office suites, TeX/PDF engines, external validators, online services,
live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 216 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 44 test files, 66850 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `3135` to `3136`; mapped
denominator moves from `3219` to `3220`.
