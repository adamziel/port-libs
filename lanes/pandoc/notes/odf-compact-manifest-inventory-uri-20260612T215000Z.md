# ODF compact manifest inventory URI package paths

Date: 2026-06-12
Bead: plib-09axl
Base: origin/main 02f4cf3c98

## Slice

Compact OpenDocument package inventory now exposes the resolved manifest package
path for URI-encoded manifest entries as `manifestPackagePath`, while preserving
the raw `manifestPath` value. This keeps decoded ZIP package parts declared in
review inventory even when the manifest uses encoded path segments.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 1066 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 73277 assertions, 0 failures

No Pandoc, office suites, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were used for progress.
