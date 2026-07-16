# Pandoc wiki format registry metadata current-base slice

2026-06-10 UTC

- Added compact wiki format registry metadata backed by the existing audited wiki fixture/template evidence.
- Each wiki format now reports a human label, direction/status fields, reader and writer fixture paths, merged upstream fixture paths, and a default template resource when one exists.
- The metadata is review/accounting data only and does not claim native wiki reader or writer parity.

Validation:

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 950 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 test files, 59127 assertions, 0 failures

External tools not run: Pandoc, Cabal/Haskell runners, wiki renderers, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
