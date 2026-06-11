# ZIP Data Descriptor Byte Totals - 2026-06-11T225852Z

Bead: plib-o2w5j

Scope: lanes/pandoc shared ZIP/OPC package primitives.

Implemented native PHP data-descriptor byte summaries in `ZipPackage`:

- Aggregates descriptor bytes, value bytes, descriptor span bytes, signed/unsigned descriptor bytes, surplus bytes, and truncated bytes.
- Records the largest descriptor entry for review handoff.
- Propagates the same accounting through instantiated package preflight, strict import preflight, and raw strict import preflight.

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: 1 test file, 3597 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 66865 assertions, 0 failures
