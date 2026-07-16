# CSL direct status and taxonomy aliases

Bead: `plib-qoud6`

Current base at submission: `3cb9c3c08` (`pandoc: summarize ODF manifest reference suffixes`)

## Scope

Direct CSL JSON/native citation items now accept bounded publication-status and
taxonomy list aliases that were already common in BibLaTeX/BibTeX handoff paths:

- `publication-status`, `publicationStatus`, `publicationstatus`, and `pubstate`
  normalize to CSL `status`.
- `keyword-list`, `keywordList`, and `keywordlist` normalize to `keywords` and
  `keywordSummary`.
- `category-list`, `categoryList`, and `categorylist` normalize to `categories`
  and `categorySummary`.

The change stays inside native PHP CSL citation/bibliography handoff and does
not invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 4700 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 64896 assertions, 0 failures`

## Status

- Added one focused `CitationCslProcessorTest.php` PASS case.
- Added 21 focused assertions.
- Updated `lane-status.json` `phpPass`: `3089 -> 3090`.
