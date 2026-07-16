# Pandoc Citation Locator Review Summaries

## Scope

- Added bounded citation locator review summaries on normalized citation and
  citation-group AST nodes.
- Exposed locator label, value, raw locator text, diagnostic summaries, and
  diagnostic reasons through CSL text variables for WordPress review handoff.
- Kept the slice on the native PHP CSL path and reused the current locator
  diagnostics reason/severity model.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4187 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 test files, 58982 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner,
citeproc process, bibliography manager, browser renderer, external validator,
online service, live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2937 to 2938.
- `phpFail` remains 0.
- The mapped focused suite count moves from 840 to 841 with one focused
  citation locator review summary pass case.
