# Pandoc citation locator source-class current-base slice

2026-06-10 UTC

- Added `citation-locator-source` and `locator-source` CSL text variables for locator source classification.
- Added `cslLocator.sourceClass` when locator diagnostics already attach `cslLocator` review metadata.
- Source classes distinguish explicit values, inferred labels, defaulted unsupported labels, unlabeled page fallbacks, and empty locator sources while preserving the existing locator diagnostics.

Validation:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4189 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 test files, 59129 assertions, 0 failures

External tools not run: Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
