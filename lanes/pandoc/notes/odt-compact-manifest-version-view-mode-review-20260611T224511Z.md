# ODT compact manifest version/view-mode review (plib-hu4m5)

Hook: plib-hu4m5, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice 20260611T224511Z.
Scope: lanes/pandoc only.

## Implementation

- Added compact manifest review aggregate counts for per-file-entry `manifest:version` values.
- Added compact manifest review aggregate counts for per-file-entry `manifest:preferred-view-mode` values.
- Preserved those two attributes on `manifestFileEntryOrder` rows so order-sensitive review packets no longer have to join back through full manifest items.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` passed: 1 test file, 462 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66754 assertions, 0 failures.

Current main target: 9c821d42a.
