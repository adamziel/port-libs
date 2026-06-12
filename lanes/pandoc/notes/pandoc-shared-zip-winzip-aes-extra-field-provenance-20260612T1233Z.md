# Shared ZIP/OPC WinZip AES Extra Field Provenance

Slice: shared ZIP/OPC package core blocker `plib-manuj` on 2026-06-12.

## What changed

- `ZipPackage::encryptionPolicyPreflight()` now decodes WinZip AES extra-field metadata before package instantiation.
- The preflight preserves central and local AES records, vendor version/name, vendor id/hex, encryption strength, actual compression method, malformed/truncated field diagnostics, central/local mismatch diagnostics, and aggregate counts.
- `ZipPackage::rawStrictImportPreflight()` already carries the encryption policy summary, so DOCX/EPUB/ODF raw package review can now see AES metadata even when `ZipPackage::fromString()` remains fail-closed.

Encrypted and AES payload bytes remain blocked. This does not implement decryption, AES payload extraction, method 99 decompression, password handling, cryptographic verification, or any external archive validation.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: 1 test file, 4042 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: 2 test files, 10385 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 44 test files, 71064 assertions, 0 failures.

## Accounting

- Adds one mapped shared ZIP/OPC native PHP case.
- `phpPass`: 3207 -> 3208.
- `phpFail`: 0.
- `mappedZipWinZipAesPolicyCases`: 1.
- `zipWinZipAesPolicyAssertions`: 55.

No Pandoc, office suites, `zip`/`unzip`, `ZipArchive`, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
