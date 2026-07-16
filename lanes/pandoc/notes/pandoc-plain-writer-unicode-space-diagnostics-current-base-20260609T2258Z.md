# Pandoc Plain Writer Unicode Space Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for spacing
break opportunity accounting.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  counts for ordinary ASCII space break opportunities.
- The same diagnostics separate non-ASCII Unicode space separator break
  opportunities, including a widest Unicode separator display-advance counter.
- The counters reuse `UnicodeText::lineBreakOpportunities()`, so they stay
  aligned with the plain writer's display-width and wrapping behavior.
- The focused fixture verifies thin-space and ideographic-space wrapping while
  preserving the rendered plain output.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 108 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 57754 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2867` to `2868`; mapped
focused checks move from `770` to `771`. `UPSTREAM_TEST_MANIFEST.json` mapped
denominator moves from `3072` to `3073`.
