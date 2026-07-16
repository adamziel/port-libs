# Pandoc EPUB3 Manifest Resource-Kind Matrix

Slice: `plib-8aod6`
Base: current main `ad79cfb39b`

## Coverage

EPUB3 package ingestion now exposes an OPF manifest resource-kind matrix from
`EpubPackage::manifestResourceKinds()` and the compact package summary.

- Maps ten manifest resource kinds: navigation, cover-image, XHTML, style, SVG,
  font, audio, video, script, and generic asset.
- Reports per-kind counts, media-type-base counts, package part-name buckets,
  existence/external/exposable counts, per-ID rows, and per-kind rows.
- Carries the matrix into WordPress import review fields as summary, item rows,
  and kind counts.

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php` - 1 file, 2505 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 46 files, 84440 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- conflict-marker scan

## Accounting

- `phpPass` moves 3589 -> 3590; `phpFail` remains 0.
- Mapped upstream cases move 3547 -> 3557.
- `mappedEpubManifestResourceKindMatrixCases = 10`
- `epubManifestResourceKindMatrixAssertions = 44`
