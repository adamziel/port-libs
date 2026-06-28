# Shared ZIP handoff general-purpose flags 2026-06-28

Slice: `plib-562cc`, shared ZIP/OPC package core blocker.

## Change

`ZipPackage::entryHandoffPreflight()` now emits selected and readable handoff
general-purpose flag summaries. The new summary fields group exact flag buckets
and review rows for UTF-8 name flags, data-descriptor flags, deflate option
flags, unsupported flag bits, roles, entry names, and byte totals.

Blocked oversized selections remain visible in selected flag summaries and
review entries, but stay out of readable handoff flag buckets and payload
hashing.

## Accounting

- `phpPass`: `470 -> 471`
- Added one focused shared ZIP/OPC handoff case.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 5416 assertions, 0 failures`

## Boundary

Native PHP ZIP/OPC metadata only. No Pandoc executable, office suite, TeX or
browser engine, `ZipArchive`, `zip`/`unzip`, external validator, network
service, Jupyter, or Node tooling was invoked.
