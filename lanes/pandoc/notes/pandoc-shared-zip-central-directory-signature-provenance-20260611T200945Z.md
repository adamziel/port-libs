# pandoc-shared-zip-central-directory-signature-provenance-20260611T200945Z

## Slice

Shared ZIP/OPC package handoff now exposes raw central-directory digital signature provenance before native package construction.

## What changed

- `ZipPackage::centralDirectorySignaturePolicyPreflight()` now reports central-directory signature presence, offsets, length, bounded preview bytes, location, raw signature data, and the native cryptographic verification policy.
- `ZipPackage::rawStrictImportPreflight()` now carries `centralDirectorySignature` and emits `central-directory-signature-unverified` before object construction, so signed package metadata remains visible even when another raw ZIP gate blocks instantiation first.
- The focused fixture covers a signed central directory with a local-header name spoof, preserving signature review metadata alongside the instantiation failure diagnostics.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3300 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65856 assertions, 0 failures

## Accounting

- `phpPass`: 3113 -> 3114
- `phpFail`: 0
- `mappedZipCentralDirectorySignaturePolicyCases`: 1
- `zipCentralDirectorySignaturePolicyAssertions`: 20

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
