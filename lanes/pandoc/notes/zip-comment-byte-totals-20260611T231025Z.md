# ZIP Comment Byte Totals - 2026-06-11T231025Z

Bead: plib-caahw

Scope: lanes/pandoc shared ZIP/OPC package primitives.

Implemented native PHP ZIP comment byte summaries in `ZipPackage`:

- Aggregates raw and decoded package/entry comment bytes.
- Adds per-entry decoded comment lengths alongside existing raw comment lengths.
- Records the largest package or entry comment source for review handoff.
- Propagates the same accounting through instantiated package preflight, strict import preflight, and raw strict import preflight.

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: 1 test file, 3597 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 66926 assertions, 0 failures
