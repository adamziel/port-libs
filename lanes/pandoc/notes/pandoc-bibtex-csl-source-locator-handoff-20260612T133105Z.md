# Pandoc BibTeX/CSL Source Locator Handoff

Slice: `pandoc-bibtex-csl-source-locator-handoff-20260612T133105Z`

## Scope

Implemented one bounded native PHP legacy BibTeX/CSL support slice:

- `BibtexCslProcessor` now maps `source`, `source-title`, and `sourcetitle`
  into CSL `source` metadata.
- `section` and `supplement` now survive the same legacy handoff.
- Simple bibliography rendering includes source, section, and supplement text so
  Markdown definition lists and WordPress review blocks keep locator provenance
  visible.

This does not repeat the richer `CitationCslProcessor` source-locator support.
It closes the legacy `BibtexCslProcessor` WordPress review packet path only.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 test file, 174 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 71429 assertions, 0 failures

## Accounting

- `phpPass`: 3217 -> 3218
- `phpFail`: 0
- Added mapped case: `mappedLegacyBibtexSourceLocatorCases = 1`
- Added focused assertions: `legacyBibtexSourceLocatorAssertions = 11`
