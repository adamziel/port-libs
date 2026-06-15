# Pandoc BibTeX CSL Review Title Hierarchy

Implemented one bounded native PHP CSL/BibLaTeX handoff slice for the legacy
`BibtexCslProcessor` path.

## Scope

- `BibtexCslProcessor` now preserves BibLaTeX main, reviewed, volume, part, and
  issue title hierarchy aliases as CSL item metadata.
- The slice covers compact review/volume/title addendum aliases:
  `reviewed-genre`, `main-title-addon`, `volume-title-short`, and
  `issue-title-addon`.
- Legacy bibliography text now exposes those values without duplicating terminal
  punctuation, and the focused test verifies styled `CitationCslProcessor`
  rendering plus WordPress bibliography handoff.

## Accounting

- `phpPass`: `3722 -> 3723`
- `phpFail`: `0`
- mapped upstream cases: `3741 -> 3742`
- `mappedBibtexCslProcessorCases`: `7 -> 8`
- `mappedBibtexCslProcessorReviewTitleHierarchyCases`: `1`
- `bibtexCslProcessorReviewTitleHierarchyAssertions`: `19`

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1` file, `277` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46` files, `88271` assertions, `0` failures
- PHP JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- conflict-marker scan

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser, external
validator, online service, live provider, or live-service provider was invoked.
