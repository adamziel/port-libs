# Shared ZIP/OPC central-directory inventory archive-extra provenance

Slice: `plib-s44np` (`20260612T005027Z`)

## Scope

`ZipPackage::centralDirectoryInventoryPreflight()` now skips and accounts for
archive extra data records inside the central-directory byte stream. The
inventory packet keeps scanning later central-directory entries, records skipped
archive-extra counts, byte totals, locations, offsets, and per-record issues,
and remains valid when archive-extra records are otherwise reported by the
separate raw ZIP policy gate.

This prevents raw strict package review from losing central-directory entry
inventory behind an inter-entry archive-extra record.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed: 1 test file, 3684 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed after rebase: 44 test files, 68041 assertions, 0 failures.

## Accounting

- Added one focused ZIP package PASS case:
  `preflights central directory inventory across inter-entry archive extra records`.
- Parity movement is preserved in notes for this core-blocker slice:
  `mappedZipCentralDirectoryInventoryArchiveExtraCases = +1`.
- New focused assertion coverage:
  `zipCentralDirectoryInventoryArchiveExtraAssertions = 24`.

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were run.
