# Pandoc BibTeX/CSL Name Addendum Current Base

Slice: `pandoc-bibtex-csl-name-addon-current-base-20260611T163130Z`

Base accepted HEAD: `4c7bc388`

## Implementation

- `BibtexCslProcessor` now maps BibLaTeX `nameaddon` and `name-addon` into CSL `name-addon` metadata for legacy citation/bibliography review packets.
- Fallback bibliography text now includes `Name addendum: ...` so WordPress review handoff does not drop source-name provenance.
- The slice is limited to legacy BibTeX/CSL handoff. It does not alter `CitationCslProcessor` style rendering, BibLaTeX relation/xref metadata, creator-role mapping, date handling, identifiers, or package readers.

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1 test files, 157 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63891 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `3069 -> 3070`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3193 -> 3194`.
- Added `mappedBibtexCslNameAddonCases: 1`.
- Added `bibtexCslNameAddonAssertions: 7`.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.
