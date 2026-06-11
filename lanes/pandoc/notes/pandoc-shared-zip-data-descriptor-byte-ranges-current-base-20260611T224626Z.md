# Shared ZIP/OPC data descriptor byte ranges

Slice: `plib-pvihg` (`20260611T224626Z`)
Current main target: `9c821d42a`

## Scope

`ZipPackage::dataDescriptorPreflight()` now summarizes data descriptor byte-range provenance for instantiated ZIP/OPC package review. The summary includes descriptor byte totals, descriptor value bytes, descriptor span bytes, signed and unsigned descriptor byte totals, surplus/truncated descriptor byte accounting, and the largest descriptor entry metadata.

The existing per-entry descriptor metadata remains unchanged, and `ZipPackage::strictImportPreflight()` continues to carry the same `dataDescriptors` summary for strict package-import handoff.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed: 1 test file, 3553 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66771 assertions, 0 failures.

No Pandoc binary, office suite, zip/unzip command, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
