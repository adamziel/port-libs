# Shared ZIP selected creator host handoff 2026-06-27

Slice: `plib-qfu48`, shared ZIP/OPC package core blocker.

## Change

`ZipPackage::entryHandoffPreflight()` now summarizes selected and readable
creator-host/version provenance before package-reader byte exposure.

The new `selectedCreatorHostSystem*` and `handoffCreatorHostSystem*` fields
bucket entries by ZIP creator host system, preserve host names, selected and
handoff byte counts, roles, entry names, unknown-host issues, and creator
version-below-needed issues.

Blocked selections remain selected-only and do not contribute to readable
handoff creator-host buckets.

## Accounting

- Focused PHP pass cases: `+1`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2309 -> 2310`
- Added `mappedSharedZipSelectedHandoffCreatorHostSystemCases = 1`

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 5192 assertions, 0 failures`
