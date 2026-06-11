# DOCX OpenXML Image Target Suffix Package Provenance

Slice: `pandoc-docx-openxml-image-target-suffix-current-base-20260611T2201Z`

Current base: `origin/main` at `4bb725eee`.

## Summary

DOCX drawing image relationships can carry query and fragment suffixes, for
example `media/review.png?display=preview#inline`. The package part is still
`word/media/review.png`; the suffix is review provenance, not part of the ZIP
member name.

`DocxOpenXmlReader` now normalizes internal image AST `url` and `mediaPath`
attrs to the stripped package part while preserving the original `target`,
`resolvedTarget`, `targetPart`, `targetQuery`, `targetFragment`, and
`targetReferenceSuffix` metadata on the image node. Package relationship
summaries already expose the same suffix provenance, so reviewer handoff can
compare AST image output with OPC package metadata without attempting to fetch
or expose a non-existent suffixed media part.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 996 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66488 assertions, 0 failures.

No Pandoc executable, Cabal/Haskell runner, Word, LibreOffice, office suite,
zip/unzip, browser renderer, external validator, online service, live provider
test, or live-service provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `3126 -> 3127`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3215 -> 3216`
- Added `mappedDocxOpenXmlImageTargetSuffixCases: 1`
- Added `docxOpenXmlImageTargetSuffixAssertions: 18`
