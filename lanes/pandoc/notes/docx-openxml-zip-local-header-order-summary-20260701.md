# DOCX OpenXML ZIP Local Header Order Summary - 2026-07-01

## Scope

Completed a bounded native PHP `DocxOpenXmlReader` package-ingestion slice for
source ZIP central-directory/local-header order provenance.

The reader already stored detailed ZIP entry order metadata under
`packageProvenance.zipPackage`. This slice promotes the review-safe order
summary into `packageProvenance.summary`:

- `zipCentralDirectoryOrderNames`
- `zipLocalHeaderOrderNames`
- `zipLocalHeaderOrderEntryCount`
- `zipLocalHeaderOrderMismatchCount`
- `zipLocalHeaderOrderMismatches`

No package bytes are exposed; the metadata remains under the existing
`docx-zip-entry-metadata-only` policy.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 12156 assertions, 0 failures after rebase
- `php tools/run-tests.php lanes/pandoc/tests`
  - Baseline-red outside this slice: 379 files, 130190 assertions, 9259
    failures

No Pandoc, Haskell/Cabal, office suite, browser, TeX/PDF engine, unzip/zip
binary, external validator, online service, or live provider was invoked.
