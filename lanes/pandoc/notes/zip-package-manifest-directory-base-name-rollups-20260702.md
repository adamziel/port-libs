# ZIP package manifest directory base-name rollups

## Slice

- Bead: `plib-e6q9q`
- Scope: shared ZIP/OPC package manifest metadata in `ZipPackage::packageManifestPreflight()`
- Date: 2026-07-02

## Added

- Per-entry `packageDirectory`, `packageDirectoryBaseName`, and `packageCaseFoldDirectoryBaseName` fields.
- Exact `packageDirectoryBaseNameSummaries` with duplicate detection, byte totals, directory counts, extension-key counts, directory-root counts, and entry names.
- Case-fold `packageCaseFoldDirectoryBaseNameSummaries` with base-name variant counts and the same metadata-only aggregate fields.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` (1 file, 6110 assertions, 0 failures)
