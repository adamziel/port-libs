# Direct CSL reviewed-work compact aliases

Bead: `plib-g853r`
Base: `4810d5c8f`

## Scope

This slice keeps direct CSL JSON bibliography packets aligned with the
BibLaTeX reviewed-work handoff already accepted by the lane.

`CitationCslProcessor` now normalizes direct item aliases:

- `reviewtitle` / `reviewsubtitle`
- `reviewedtitle` / `reviewedsubtitle`
- `reviewgenre`

Reviewed title aliases are composed into `reviewedTitle` with the existing
title/subtitle punctuation rule, and reviewed genre aliases feed the existing
`reviewed-genre` style variable, default bibliography text, and WordPress block
handoff.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 test file, 4772 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 65638 assertions, 0 failures.

## Accounting

- Added 1 focused direct CSL JSON reviewed-work compact alias PASS case.
- Added 14 focused assertions.
- Updated `lane-status.json` `phpPass`: `3108 -> 3109`.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
