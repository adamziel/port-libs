# Pandoc EPUB3 Metadata Container Authoring

Slice: `pandoc-epub-metadata-container-authoring`
Base: `fe80f2e1cf`

This slice keeps EPUB3 package ingestion native-PHP-only and preserves OPF
`metadata` container authoring attributes that were previously only used for
language/direction inheritance. `EpubReader` now records the container `id`,
`xml:lang`, `dir`, `xml:base`, structural/custom attribute split, and
metadata-only base policy in package metadata, import reports, and document
attributes.

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test was
run.

## Accounting

- `phpPass`: `3684 -> 3685`
- `phpFail`: `0`
- mapped upstream cases: `3713 -> 3714`
- `mappedEpubMetadataContainerAuthoringCases`: `0 -> 1`
- `epubMetadataContainerAuthoringAssertions`: `0 -> 19`

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 4597 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87030 assertions, 0 failures`
