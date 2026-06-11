# CSL direct page extent aliases

Bead: `plib-n4okx`

Current base at submission: `308f61306` (`origin/main`)

## Scope

Direct CSL JSON/native citation items now normalize bounded page extent aliases
that were already common in BibTeX/BibLaTeX handoff paths:

- `pages` normalizes to CSL `page` and derives `page-first`.
- `pagetotal`, `numPages`, and `pageTotal` normalize to `number-of-pages`.
- `volumes`, `numberofvolumes`, and `volumeCount` normalize to
  `number-of-volumes`.

The change stays inside native PHP CSL citation/bibliography handoff and does
not invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test file, 4834 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66210 assertions, 0 failures`

## Status

- Added one focused `CitationCslProcessorTest.php` PASS case.
- Added 22 focused assertions.
- Updated `lane-status.json` `phpPass`: `3124 -> 3125`.
