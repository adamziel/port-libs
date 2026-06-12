# Shared ZIP/OPC local-header span byte buckets

Bead: `plib-gkpol`

This slice extends `ZipPackage::localHeaderSpanPreflight()` with aggregate
raw local-header span byte buckets before package instantiation. The preflight
now reports available local-header entry counts, local-header bytes, compressed
payload bytes, data-descriptor bytes, claimed record bytes, unclaimed byte
totals, unclaimed-entry counts, and contiguous-entry counts. The same summary
continues to propagate through `ZipPackage::rawStrictImportPreflight()`.

Accounting:

- `phpPass`: `3158 -> 3159`
- Added one focused `ZipPackageTest` PASS case.
- Added `mappedZipLocalHeaderSpanByteBucketCases = 1`.
- Added `zipLocalHeaderSpanByteBucketAssertions = 27`.

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed with `1 test files, 3711 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 68181 assertions, 0 failures`.

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
