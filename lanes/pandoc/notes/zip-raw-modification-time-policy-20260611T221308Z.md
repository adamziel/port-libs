# ZIP raw modification-time policy preflight (plib-vcnjp)

Hook: plib-vcnjp, Pandoc shared ZIP/OPC package core blocker slice 20260611T221308Z.
Scope: lanes/pandoc only.

## Implementation

- Added `ZipPackage::modificationTimePolicyPreflight()` to inspect central-directory modification-time metadata before package instantiation.
- Preserved DOS timestamp validity, selected extended timestamp metadata, NTFS modified timestamps, and per-entry issue codes for raw review packets.
- Wired raw strict import preflight to include `modificationTimes` and propagate invalid modification-time diagnostics even when local-header spoofing blocks package construction.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed: 1 test file, 3526 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66552 assertions, 0 failures.

Current main target: 7f3338836.
