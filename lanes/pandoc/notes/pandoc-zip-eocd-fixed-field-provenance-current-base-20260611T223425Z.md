# Shared ZIP/OPC EOCD Fixed-Field Provenance

- Bead: `plib-rwnlu`
- Base: `71ce25fbede5f0fddff3c600124768b6a12172a5` (`71ce25fbe`)
- Scope: shared ZIP/OPC package primitives under `lanes/pandoc`

## Slice

`ZipPackage::endOfCentralDirectoryPreflight()` now exposes `eocdFixedFields`
for the EOCD fixed 22-byte record. The review shape records absolute offsets,
field lengths, and parsed values for:

- signature
- disk number
- central-directory disk
- disk and total entry counts
- central-directory size
- central-directory offset
- package-comment length

`rawStrictImportPreflight()` already carries the archive preflight summary, so
the new EOCD layout remains visible to raw package-review queues before any
ZIP/OPC consumer exposes package bytes.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3550 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66708 assertions, 0 failures

No Pandoc, office suite, zip/unzip, browser, external validator, online
service, or live provider test was invoked.
