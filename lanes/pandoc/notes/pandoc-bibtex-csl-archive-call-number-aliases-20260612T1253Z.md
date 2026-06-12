# Pandoc BibTeX/CSL Archive Call-Number Alias Handoff

Slice: `pandoc-bibtex-csl-archive-call-number-aliases-20260612T1253Z`

## Scope

Implemented one bounded native PHP legacy BibTeX/CSL support slice:

- `BibtexCslProcessor` now maps `archiveCollection`/`archive-collection`/`archive_collection` into CSL `archive-collection`.
- `archiveLocation` now participates in the existing CSL `archive_location` handoff.
- `callnumber`, `call-number`, `library`, `shelfmark`, and `shelf-mark` now map into CSL `call-number`.
- Simple bibliography rendering includes call-number text so Markdown and WordPress definition-list handoff keeps shelf/location provenance visible.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 test file, 163 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 71163 assertions, 0 failures

## Accounting

- `phpPass`: 3211 -> 3212
- `phpFail`: 0
- Added mapped case: `mappedLegacyBibtexArchiveCallNumberCases = 1`
- Added focused assertions: `legacyBibtexArchiveCallNumberAssertions = 12`
