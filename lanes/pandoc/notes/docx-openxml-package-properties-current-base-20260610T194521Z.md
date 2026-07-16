# DOCX OpenXML package property handoff

Slice: `plib-m8rpy`
Base accepted HEAD after rebase: `2494dfb7e1722fde97a99d34d1c0a43b5b626ef1`

## Scope

This bounded DOCX/OpenXML package-ingestion slice stays in native PHP package
metadata handling.

- `DocxOpenXmlReader` now resolves package-root relationships for
  `docProps/app.xml` and `docProps/custom.xml`.
- Extended properties preserve template, manager, company, document statistics,
  booleans, `HeadingPairs`, and `TitlesOfParts`.
- Custom properties preserve typed values, first-value `byName` summaries,
  duplicate-name metadata, and relationship/content-type provenance.

## Evidence

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 233 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 61146 assertions, 0 failures`

## Mapping Delta

- `lane-status.json` `phpPass`: `3002 -> 3003`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3157 -> 3158`
- Added `mappedDocxOpenXmlPackagePropertyCases: 1`
- Added `docxOpenXmlPackagePropertyAssertions: 52`

## External Tools

No Pandoc, Cabal/Haskell runner, office suite, Word, LibreOffice, zip/unzip,
browser renderer, external validator, online service, live provider test, or
live-service provider test was executed.
