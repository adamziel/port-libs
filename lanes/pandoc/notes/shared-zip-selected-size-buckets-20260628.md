# Shared ZIP Selected Size Buckets

Slice: `plib-6153j`, shared ZIP/OPC package primitives.

`ZipPackage::entryHandoffPreflight()` now reports selected and readable package
entry size buckets before DOCX/EPUB/ODT readers consume package bytes. The
bucket summaries group zero-byte, tiny, small, medium, and large entries with
entry counts, file/directory counts, compressed and uncompressed byte totals,
roles, entry names, and largest-entry provenance.

Blocked oversized selections remain visible in `selectedSizeBucketSummaries`
but stay metadata-only and do not appear in `handoffSizeBucketSummaries`.
Missing optional entries are excluded from size buckets because they do not map
to package payload bytes.

No Pandoc, office suites, TeX/PDF engines, browser renderers, zip/unzip,
`ZipArchive`, external validators, online services, live provider tests, or
live-service provider tests were invoked.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 5388 assertions, 0 failures`

Accounting:

- `lane-status.json` `phpPass`: `470 -> 471`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2311 -> 2312`
- Added `sharedZipSelectedHandoffSizeBucketCases = 1`
- Added `mappedSharedZipSelectedHandoffSizeBucketCases = 1`
