# Pandoc ODF Manifest Key Derivation Size Provenance

Slice: `plib-dopq6`
Date: 2026-06-11 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion
Verified base: `aed180db256f20b9847844dad3ed5ee5d1b28ad2`

## Behavior

ODF/OpenDocument package handoff now preserves encrypted `manifest:key-derivation` `manifest:key-size` provenance through manifest items, media items, import-report encryption items, document manifest metadata, compact package review, and image metadata. Encrypted package bytes remain blocked from exposure.

No Pandoc binary, office suite, zip/unzip CLI, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

## Accounting

- `phpPass`: `3117 -> 3118`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3210 -> 3211`
- `inventory.mappedOdfOpenDocumentCoreCases`: `19 -> 20`
- `inventory.odfOpenDocumentCoreAssertions`: `446 -> 461`
- `mappedOdfManifestKeyDerivationSizeCases`: `1`
- `odfManifestKeyDerivationSizeAssertions`: `15`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: 1 test file, 4032 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 test file, 383 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 66012 assertions, 0 failures
