# Source-Neutral JSONB CHECK Current-Source Dynamic

Slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T125812Z-0`
Base accepted HEAD: `27cf721c25e91c9dcac0b599677df25582e922d2`

## Change

- `SQLiteJsonbCheckCurrentNextPlan` now derives a neutral row identity column
  from the current `CREATE TABLE` source when it sees an inline
  `INTEGER PRIMARY KEY`, instead of depending only on preselected fallback
  names.
- The source-neutral JSONB CHECK guard now also scans
  `SQLiteJsonbCheckCurrentNextPlan.php` alongside the generated JSON path,
  generated CHECK index, generated cascade, and JSON table planner source
  files.
- Added focused neutral coverage for an `event_records(record_id INTEGER
  PRIMARY KEY, payload_jsonb BLOB, ...)` CHECK plan with no explicit
  `rowidColumn` option.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - 1 file / 19 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - 5 files / 350 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - 1 file / 6 assertions / 0 failures.
- `php lanes/libsqlite/examples/application-jsonb-check-current-next69.php` - emitted generic `app_settings` JSONB CHECK admission with 2 accepted and 2 rejected changes.
- `git diff --check -- lanes/libsqlite` - passed.

## Dependency Closure

No new support component is needed. This reuses existing CREATE TABLE parsing,
JSONB mutation, JSON inspection/extraction, and CHECK evaluation helpers.

## Non-Overlap

This is source-neutral production cleanup only. It does not edit root
coordination files, runner metadata, pass counters, or dashboard artifacts, and
it does not repeat accepted JSON table, WAL, pager, B-tree, or upstream-corpus
coverage.

Root harness: not run - isolated micro-slice.
