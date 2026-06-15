# Pandoc EPUB Guide Reference Authoring

Slice: `pandoc-epub-guide-reference-authoring`

## Scope

Compact EPUB3 package ingestion now preserves OPF `<guide><reference>`
authoring metadata for native package review.

`EpubPackage` now carries each guide reference's:

- `xml:lang` and `dir` values;
- sorted raw attributes;
- custom `data-*` and namespaced review attributes;
- aggregate authoring and guide-report language/direction/custom buckets in the
  package summary and WordPress import review packet.

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test is
used.

## Accounting

- `phpPass`: `15325 -> 15326`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped: `14996 -> 14997`
- `mappedEpubGuideReferenceAuthoringCases`: `1`
- `epubGuideReferenceAuthoringAssertions`: 29

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 3770 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `181 test files, 165327 assertions, 0 failures`
