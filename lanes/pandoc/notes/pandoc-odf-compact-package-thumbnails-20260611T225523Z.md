# Pandoc ODF Compact Package Thumbnails - 2026-06-11

## Slice

`OpenDocumentPackage` now reports ODF/ODT `Thumbnails/*` package sidecars as metadata-only `packageThumbnails` records. The summary preserves declared, undeclared, missing, encrypted, invalid media-type, suffix/query/fragment, byte/CRC/compression, and package-inventory role provenance while keeping preview thumbnails out of document media handoff.

This is scoped to native PHP compact ODF package ingestion. It does not invoke Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Accounting

- Added one focused `OpenDocumentPackageTest.php` case with 49 assertions.
- `phpPass`: 3134 -> 3135; `phpFail`: 0.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 3218 -> 3219.
- Added `mappedOdfCompactPackageThumbnailCases = 1`.
- Added `odfCompactPackageThumbnailAssertions = 49`.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 test file, 501 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 66953 assertions, 0 failures
