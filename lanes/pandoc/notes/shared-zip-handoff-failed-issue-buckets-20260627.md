# Shared ZIP Handoff Failed Issue Buckets

Slice: `plib-e6q9q`, shared ZIP/OPC package primitives.

`ZipPackage::entryHandoffPreflight()` now reports compact failed-entry issue buckets for selected package handoff review. The new `failedIssueBucketCount` and `failedIssueSummaries` fields group blocked rows by issue, including duplicate selected requests, oversized entries, missing required sidecars, and unreadable unsupported-compression entries.

Each bucket preserves request counts, required/optional splits, present/missing/blocked/unreadable counts, compressed and uncompressed byte totals, roles, entry names, and requested names. Raw `failedEntries` remain unchanged for detailed inspection, and blocked package bytes stay unexposed.

Mapped accounting:
- `phpPass`: `461 -> 462`
- `benchmarkDenominator.mapped`: `2307 -> 2308`
- `mappedSharedZipHandoffFailedIssueBucketCases`: `1`

Validation:
- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - 1 file, 5,041 assertions, 0 failures

No Pandoc, office suites, browser engines, `zip`/`unzip`, `ZipArchive`, external validators, online services, live provider tests, or live-service provider tests were invoked.
