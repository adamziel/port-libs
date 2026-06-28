# Shared ZIP parent directory handoff 2026-06-27

Slice: `plib-jng87`, shared ZIP/OPC package core blocker.

## Change

`ZipPackage::entryHandoffPreflight()` now carries `parentDirectory` for each
selected entry and emits `selectedParentDirectorySummaries` plus
`handoffParentDirectorySummaries`.

The summaries preserve parent-directory entry counts, file/directory splits,
byte totals, roles, and entry names for selected package parts. Blocked
oversized selections remain in selected buckets but stay out of readable
handoff buckets.

## Accounting

- `phpPass`: `467 -> 468`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2309 -> 2310`
- Added `mappedSharedZipSelectedHandoffParentDirectoryCases = 1`

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 5189 assertions, 0 failures`
