# Pandoc Plain Writer Tab Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for tab break
opportunities during plain output wrapping.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  `tabBreakOpportunityCount` values.
- Diagnostics also expose `maxTabDisplayAdvance`, using the existing
  `UnicodeText::lineBreakOpportunities()` column metadata to show the widest
  tab-stop expansion encountered in the rendered source text.
- The focused fixture verifies tabs remain part of native soft-break accounting
  while rendered plain output continues to use the existing bounded wrapping
  behavior.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 70 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 57115 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2833` to `2834`; mapped
focused checks move from `736` to `737`. `UPSTREAM_TEST_MANIFEST.json` mapped
denominator moves from `3048` to `3049`.
