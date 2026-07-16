# Pandoc Direct CSL Howpublished Medium Aliases

Slice: `pandoc-csl-direct-howpublished-medium-aliases-20260612T105200Z`

Implemented a bounded Direct CSL JSON medium alias handoff for BibTeX-shaped
publication medium keys:

- `howPublished`
- `how-published`
- `howpublished`

These aliases normalize into canonical `medium` metadata and render through CSL
`medium`, `howpublished`, and `how-published` text variables. The focused test
also verifies bibliography rendering and WordPress review block propagation.

This preserves direct-format parity with the existing BibTeX/BibLaTeX
`howpublished` to CSL `medium` parser handoff without invoking Pandoc,
citeproc, BibTeX, Biber, bibliography managers, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 5173 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 70603 assertions, 0 failures`

## Accounting

- New focused case: `normalizes bounded direct csl json howpublished medium aliases`
- `phpPass`: `3192 -> 3193`
- Direct CSL mapped cases: `mappedCslDirectHowpublishedMediumAliasCases = 1`
- Focused assertions added: `cslDirectHowpublishedMediumAliasAssertions = 14`
