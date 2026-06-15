# Pandoc EPUB Guide Reference Authoring

Slice: `pandoc-epub-guide-reference-authoring`

## Scope

Compact EPUB3 package ingestion preserves OPF `<guide><reference>` authoring
metadata for native package review.

`EpubPackage` carries each guide reference's:

- `id`, `xml:lang`, `dir`, and metadata-only `xml:base` values;
- sorted raw attributes and structural attribute summaries;
- custom `data-*` and namespaced review attributes;
- aggregate authoring report rows in the package summary and WordPress import
  review packet.

`xml:base` is reported for authoring review only and is not applied to package
path resolution.

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test is
used.

## Accounting

- `phpPass`: `15359` (unchanged from current main)
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped: `15014` (unchanged)
- `mappedEpubGuideReferenceAuthoringCases`: `1`
- `epubGuideReferenceAuthoringAssertions`: `36`

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 4131 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `181 test files, 166564 assertions, 0 failures`
