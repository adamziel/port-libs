# CSL BibLaTeX Division Metadata Current Base

Date: 2026-06-11
Bead: `plib-mucbx`
Base: `origin/main` `357d049c`

## Scope

This slice maps BibLaTeX `division` and `subdivision` fields into the native
CSL `division` metadata path. It keeps the handoff local to `lanes/pandoc` and
does not invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Coverage

- Added parser mapping in `BibtexCslParser` for `division` and `subdivision`.
- Added `CitationCslProcessorTest` coverage for raw BibTeX extraction,
  normalized item metadata, default bibliography review text, CSL style text
  variable rendering, and appended WordPress bibliography output.

## Direct-Format Parity Accounting

- `lane-status.json` `phpPass`: `3122 -> 3123`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3211 -> 3212`
- Added `mappedCslBiblatexDivisionCases: 1`
- Added `cslBiblatexDivisionAssertions: 17`

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test file, 4812 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66122 assertions, 0 failures`
