# Pandoc Plain Writer Generated Wrap Diagnostics

Integrated one bounded native PHP plain-writer diagnostics slice for actual
auto-wrap splits emitted by the plain writer.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  `wrapSplitLineCount` values for physical source lines split by auto wrapping.
- Diagnostics also report `generatedWrapBreakCount`, the number of soft wrap
  breaks inserted into the emitted plain output.
- The focused fixture verifies a multi-line code block where two source lines
  generate three emitted wrap breaks, distinguishing source-line split count
  from total generated breaks.
- The current-base implementation preserves the existing `softWrapBreakCount`,
  forced-wrap, blank-line, tab, Unicode-space, and Unicode hard-separator
  diagnostics.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 202 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 43 test files, 59044 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2940` to `2941`; mapped
focused checks move from `843` to `844`; `phpFail` remains `0`.
