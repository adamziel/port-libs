# Compact ODT Package Inventory Slice

- Bead: `plib-kdb56`
- Required base: `a2c06142a7e251525c2e35f19982c43208d7be1e`
- Scope: native PHP ODF/ODT OpenDocument package ingestion, focused on compact `OpenDocumentPackage` summaries.

## Coverage

Adds one focused `OpenDocumentPackageTest.php` case for compact ODT ZIP inventory review metadata:

- ZIP package entry inventory in `summarize()['packageInventory']`.
- Central/local header order preflight and compression bucket summaries from the shared `ZipPackage`.
- Manifest-declared package part roles for content, media, and directory entries.
- Undeclared ZIP payload reporting without treating undeclared payloads as manifest media.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`:
  `1 test files, 226 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`:
  `44 test files, 62683 assertions, 0 failures`

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
