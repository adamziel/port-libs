# ZIP Archive Extra Data Preview Provenance

Bead: `plib-2fwg0`

Base: current `origin/main` `e9d25106ae1cce51d6cee6c0e85343533f9ba1ea`

## Summary

Shared ZIP/OPC package preflight now preserves bounded preview provenance for ZIP
archive extra data records.

- Archive extra data records report `dataPreviewByteCount`.
- Archive extra data records report bounded `dataPreviewHex` from the first 16
  data bytes.
- The preview metadata flows through direct archive-extra-data preflight and raw
  strict import preflight without exposing full archive extra data payloads.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3610 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67295 assertions, 0 failures
- `git diff --check -- lanes/pandoc`
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

## Accounting

- Adds one focused `ZipPackageTest.php` PASS case with 6 assertions.
- `phpPass` moves from 3145 to 3146; `phpFail` remains 0.
- Adds `mappedZipArchiveExtraDataPreviewCases = 1`.
- Adds `zipArchiveExtraDataPreviewAssertions = 6`.

## Boundaries

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
