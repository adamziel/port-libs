# ODF/ODT layout-cache package sidecar policy

Date: 2026-06-16
Bead: plib-l4vm2

## Slice

Added bounded native PHP ODF/ODT package ingestion coverage for `layout-cache` package sidecars. The reader and compact package summarizer now classify declared and undeclared layout-cache parts as metadata-only review data, block byte exposure, keep them out of document media handoff, and preserve package inventory/manifest role provenance.

## Review issues

- `odf-layout-cache-missing-package-part`
- `odf-layout-cache-undeclared-package-part`
- `odf-layout-cache-encrypted-package-part`
- `odf-layout-cache-invalid-media-type`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php` (`2` files, `6,691` assertions, `0` failures)
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php` (`5` files, `7,022` assertions, `0` failures)
- `php tools/run-tests.php lanes/pandoc/tests` (`195` files, `169,995` assertions, `0` failures)
- No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
