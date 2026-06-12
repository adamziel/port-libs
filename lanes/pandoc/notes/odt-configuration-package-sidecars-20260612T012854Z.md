# ODT Configuration Package Sidecars - 2026-06-12

Slice: `plib-1lo44`

This slice classifies compact ODT `Configurations2/` package parts as metadata-only package configuration review items in `OpenDocumentPackage`.

The package summary now exposes `packageConfigurations`, inventory role/count metadata for `package-configuration`, and manifest review counts/items for configuration package parts. Configuration payload bytes remain blocked from document media handoff while stored byte length, CRC32 provenance, media-type parameters, suffix/query/fragment provenance, missing status, encryption status, directory state, and undeclared status remain visible for review.

Verification on current main `d9fe936efb`:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` - 1 file, 698 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 68580 assertions, 0 failures
