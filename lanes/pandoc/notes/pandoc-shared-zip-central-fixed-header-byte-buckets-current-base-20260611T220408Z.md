# Pandoc Shared ZIP/OPC Central Fixed-Header Byte Buckets

Bead: `plib-o4xty`

Base: `origin/main` `4bb725eee`

Implemented a bounded native PHP ZipPackage slice for central-directory fixed-header byte-bucket provenance. `centralDirectoryFixedHeaderPreflight()` now reports aggregate byte totals for signature, version, flag/method, timestamp, CRC/size, length fields, disk/attribute fields, and local-header offsets. The same packet remains visible through raw strict and strict import preflight handoff.

Coverage:

- Added one `ZipPackageTest.php` case for three shared ZIP/OPC entries.
- `phpPass`: `3129 -> 3130`
- `phpFail`: `0`
- `mappedZipCentralDirectoryFixedHeaderByteBucketCases`: `1`
- `zipCentralDirectoryFixedHeaderByteBucketAssertions`: `15`

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: `1 test file, 3459 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`: `44 test files, 66485 assertions, 0 failures`

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
