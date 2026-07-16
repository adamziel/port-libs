# Pandoc EPUB3 Compact Rights License Links

Bead: `plib-juqnb`
Date: 2026-06-12 UTC
Scope: `lanes/pandoc` EPUB3 package ingestion.

## Change

`EpubPackage` compact package ingestion now links OPF `dc:rights` metadata to
package `<link>` resources that refine those rights records. Rights details keep
their refined `authority` and `term` metadata, while linked local and remote
license resources carry package-link query/fragment provenance, manifest ids,
ZIP byte length, CRC32, byte-exposure policy, and external-link diagnostics.

The rights details and aggregate rights summary are propagated through
`summary()['wordpressImport']['metadataDetails']` so WordPress review queues can
inspect license provenance before import without fetching remote resources.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1997 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 70589 assertions, 0 failures

No Pandoc executable, EPUBCheck, zip/unzip, browser renderer, external
validator, online service, live provider test, or live-service provider test was
run.

## Accounting

- `phpPass`: `3191 -> 3192`
- New mapped row: `mappedEpubRightsLicenseLinkPackageCases = 1`
- New assertion row: `epubRightsLicenseLinkPackageAssertions = 48`

## Non-Overlap

This does not repeat accepted OPF agent display order, title/date/source/language
metadata, bibliographic field parsing, accessibility metadata, metadata-link
vocabulary, manifest/spine, guide/collection, OCF sidecar, media-overlay,
fallback, binding, or navigation work. This slice is limited to rights/license
link provenance on the compact `EpubPackage` review handoff surface.
