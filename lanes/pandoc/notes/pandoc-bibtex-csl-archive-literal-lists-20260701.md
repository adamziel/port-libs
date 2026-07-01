# Pandoc BibTeX/CSL Archive Literal Lists

Slice: `pandoc-bibtex-csl-archive-literal-lists-20260701`

## Scope

Implemented one bounded native PHP legacy BibTeX/CSL support slice:

- `BibtexCslProcessor` now preserves list-valued archive metadata for `archive`, `archive-collection`, `archive-place`, and `archive-location`/`eprint` aliases.
- The scalar CSL handoff remains available as semicolon-joined text, while new `archive-list`, `archive-collection-list`, `archive-place-list`, and `archive-location-list` arrays carry the original literal list parts.
- `CitationCslProcessor` normalizes those arrays into `archiveList`, `archiveCollectionList`, `archivePlaceList`, and `archiveLocationList`, and renders the matching CSL text variables.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, office suites, TeX/browser engines, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 test file, 1244 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 6112 assertions, 0 failures
- `git diff --check -- lanes/pandoc`
- `rg -n "^(<<<<<<<|=======|>>>>>>>)$" lanes/pandoc`

## Accounting

- Added mapped case: `mappedLegacyBibtexArchiveLiteralListCases = 1`
- Added focused assertions: `legacyBibtexArchiveLiteralListAssertions = 21`
