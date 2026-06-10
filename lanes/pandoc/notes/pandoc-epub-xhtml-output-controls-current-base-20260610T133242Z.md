# Pandoc EPUB XHTML Output Controls Slice 2026-06-10

## Scope

Bead: `plib-wwug`

This slice stays inside `lanes/pandoc` and covers EPUB3 package ingestion. It extends `EpubReader` XHTML form side-effect metadata so form-contained XHTML `output` controls are no longer dropped during static package review.

## Behavior

- `xhtmlFormControls()` now includes XHTML `output` alongside `button`, `input`, `select`, and `textarea`.
- Output controls stay inert and non-submit; they do not create separate form-control side effects.
- `xhtmlFormControlReport()` now preserves output text plus `forRaw` and parsed `forIds` target provenance for reviewer handoff.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 3962 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 60172 assertions, 0 failures`

No Pandoc, EPUBCheck, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.

## Accounting

- `phpPass`: `2972 -> 2973`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3136 -> 3137`
- Added `mappedEpubXhtmlOutputControlCases=1`
- Added `epubXhtmlOutputControlAssertions=8`
