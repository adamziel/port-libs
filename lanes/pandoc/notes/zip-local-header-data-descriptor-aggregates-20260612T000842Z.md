# ZIP local header data descriptor aggregates

- Bead: `plib-anvqk`
- Base: current `origin/main` `008ca6991b`
- Scope: shared ZIP/OPC package ingestion in `ZipPackage::localHeaderPreflight()`.

This slice adds aggregate local-header data descriptor provenance for package review:
descriptor entry counts, signed versus unsigned standard descriptor counts, descriptor
byte totals, descriptor offsets, descriptor entry names, and zero local-header
placeholder entry counts/names.

The focused fixture covers one signed standard descriptor, one unsigned standard
descriptor, and one ordinary follower entry, then checks propagation through
strict and raw strict import preflight local-header summaries.

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 3635 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67595 assertions, 0 failures
