# Pandoc Plain Writer Forced Wrap Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for forced
wraps of unbreakable text segments.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  `forcedWrapBreakCount` values when auto wrapping must split an unbreakable
  segment across output lines.
- The diagnostics also expose `maxForcedWrapSegmentDisplayWidth`, which records
  the widest segment that required forced splitting.
- The focused fixture covers a long reviewer identifier and a no-break-space
  phrase so native review handoff can distinguish forced token splits from
  normal soft wrapping and from over-column output.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 164 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 58743 assertions, 0 failures.

Status delta after rebase over current main:
`phpPass` moves from `2927` to `2928`; mapped focused checks move from `830`
to `831`.
