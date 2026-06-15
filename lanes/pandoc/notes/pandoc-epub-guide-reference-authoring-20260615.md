# Pandoc EPUB Guide Reference Authoring

Slice: `pandoc-epub-guide-reference-authoring`

## Scope

Compact EPUB3 package ingestion now preserves OPF `<guide><reference>`
authoring metadata for native package review.

`EpubPackage` now carries each guide reference's:

- `xml:lang` and `dir` values;
- sorted raw attributes;
- custom `data-*` and namespaced review attributes;
- aggregate authoring report rows in the package summary and WordPress import
  review packet.

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test is
used.

## Accounting

- `phpPass`: `3731 -> 3732`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped: `3749 -> 3750`
- `mappedEpubGuideReferenceAuthoringCases`: `1`
- `epubGuideReferenceAuthoringAssertions`: `23`

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 3337 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 88566 assertions, 0 failures`
