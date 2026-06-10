# Shared ZIP/OPC Raw Central Names 2026-06-10

Slice: `plib-f5g22`
Rebase base: `3866414a3872bc8b19eaf933ca45b4725ec4b2f0`

Change:
- `ZipPackage::centralDirectoryInventoryPreflight()` now surfaces duplicate raw central-directory entry names as a bounded-reader issue via `hasDuplicateEntryRawNames` and `duplicate-central-directory-entry-raw-names`.
- Raw-name collisions are now visible at the shared ZIP inventory layer before decoded package handoff, including compressed archive stream wrappers.

Verification:
- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Result:
- Focused ZIP package test: 1 file, 3034 assertions, 0 failures.
- Focused ZIP plus archive stream tests: 2 files, 9382 assertions, 0 failures.
- Full Pandoc lane after rebase: 44 files, 61642 assertions, 0 failures.

No Pandoc, office suite, zip/unzip, browser renderer, external validator, online service, or live provider test was invoked.
