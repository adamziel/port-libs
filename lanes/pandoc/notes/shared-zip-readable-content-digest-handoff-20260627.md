# Shared ZIP readable content digest handoff 2026-06-27

Slice: `plib-79ojw`, shared ZIP/OPC package core blocker.

## Change

`ZipPackage::entryHandoffPreflight()` now emits a readable content digest
manifest for entries that pass the selected-entry handoff gates. The new
`handoffContentDigest*` fields record the manifest version, manifest SHA-256,
readable byte total, and compact per-entry payload SHA-256 rows.

Blocked oversized selections and missing package parts remain outside the
digest manifest; their per-entry `contentSha256` values stay `null`.

## Accounting

- `phpPass`: `465 -> 466`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2308 -> 2309`
- Added `mappedSharedZipReadableContentDigestCases = 1`

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 5144 assertions, 0 failures`
