# CSL/BibLaTeX Legacy Date Variable Handoff

## Summary

`BibtexCslProcessor` now maps legacy BibLaTeX `available-date`, `submitted`,
and `label-date` fields into CSL date variables. Compact date fields and split
year/month/day fields are preserved in raw BibTeX provenance and flow through
the existing CSL renderer, WordPress bibliography handoff, and citation cluster
rendering.

## Accounting

- `mappedCslBiblatexLegacyDateVariableCases`: `1`
- `cslBiblatexLegacyDateVariableAssertions`: `24`

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1 test files, 756 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `3 test files, 6954 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite,
TeX/PDF engine, browser renderer, external validator, online service, live
provider test, or live-service provider test was executed.
