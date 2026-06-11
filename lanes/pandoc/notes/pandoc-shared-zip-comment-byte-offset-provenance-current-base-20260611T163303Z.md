# Pandoc Shared ZIP Comment Byte Offset Provenance

## Scope

Implemented a bounded native PHP shared ZIP/OPC package slice for comment byte provenance. `ZipPackage::commentPreflight()` and `ZipPackage::commentPolicyPreflight()` now expose absolute package-comment and central-directory entry-comment byte offsets plus end offsets, and `rawStrictImportPreflight()` carries the same policy summary before package handoff.

This keeps DOCX/EPUB/ODT review queues able to identify exact package comment bytes without extracting payloads or invoking Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 3201 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65560 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `3104 -> 3105`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3204 -> 3205`
- Added `mappedZipCommentByteOffsetProvenanceCases = 1`
- Added `zipCommentByteOffsetProvenanceAssertions = 18`
