# Pandoc DOCX OpenXML Theme Scheme Slice

Slice: `pandoc-docx-openxml-theme-scheme-current-base-20260610T141910Z`

## Scope

Implemented a bounded compact DOCX/OpenXML package ingestion slice for document
theme relationship handoff.

`DocxOpenXmlReader` now:

- resolves document-level theme relationships through the package relationship
  target rather than assuming only conventional paths;
- records source part, relationship part, raw target, resolved target,
  target part, existence, and content type provenance in `themeRelationship`;
- parses bounded DrawingML `fontScheme` metadata for major/minor latin, East
  Asian, and complex-script typefaces;
- parses bounded DrawingML `clrScheme` metadata for core theme color slots,
  including system-color `lastClr` RGB fallbacks and common alias names such as
  `text1`, `background1`, `hyperlink`, and `followedHyperlink`.

This does not change the full `DocxReader` theme font/color run-resolution path.
It only extends the compact package ingestion metadata path.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 163 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 60292 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suite, `zip`/`unzip`, Cabal/Haskell
runner, browser renderer, external validator, online service, live provider
test, or live-service provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `2976 -> 2977`
- `lane-status.json` `suiteProgress`: `877 -> 878`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3139 -> 3140`
- `docxOpenXmlCoreCases`: `35 -> 36`
- `mappedDocxOpenXmlCoreCases`: `36 -> 37`
- `docxOpenXmlCoreAssertions`: `448 -> 472`
- Added `mappedDocxOpenXmlThemeSchemeCases: 1`
- Added `docxOpenXmlThemeSchemeAssertions: 24`
