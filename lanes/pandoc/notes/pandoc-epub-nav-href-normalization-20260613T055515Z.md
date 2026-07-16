# EPUB nav href normalization diagnostics

## Scope

- Added direct `EpubPackageReader` `navReport.hrefNormalization` metadata for EPUB3 navigation href normalization diagnostics.
- Covered `toc`, `landmarks`, and `page-list` sections with stable per-section counts.
- Preserved per-entry labels, targets, fragments, external/local classification, and diagnostic lists while keeping the direct reader native PHP only.

## Coverage

- Percent-encoded local hrefs.
- Dot-segment normalization.
- Package-root escape diagnostics.
- Case-sensitive local target mismatch diagnostics.
- Empty href and empty label diagnostics.
- External nav targets and fragment diagnostics.

## Counters

- `phpPass`: `3413 -> 3414`
- `phpFail`: `0`
- `mappedEpubNavHrefNormalizationCases`: `1`
- `epubNavHrefNormalizationAssertions`: `77`

## Verification

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php` -> `1` file, `584` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests` -> `46` files, `78817` assertions, `0` failures

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
