# Shared ZIP selected handoff aggregate budget 20260612T010632Z

This slice tightens the native PHP shared ZIP/OPC selected-entry handoff
preflight.

- `ZipPackage::entryHandoffPreflight()` now accepts an optional total selected
  uncompressed-byte limit in addition to the existing per-entry limit.
- The preflight computes unique selected-entry compressed and uncompressed byte
  totals before reading payloads, so duplicate requests do not inflate the
  aggregate budget.
- If the selected total exceeds the limit, otherwise-readable file entries are
  blocked with `total-uncompressed-size-exceeds-limit` before content bytes are
  read or hashed.

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
