# Source-Neutral JSONB CHECK Current-Source Dynamic

Slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T210338Z-1`
Base accepted HEAD: `4891303774c9ca404591d2f3a4d35bc9e197e3fb`

## Change

- `SQLiteJsonbCheckCurrentNextPlan` now derives the default mutation JSON
  column from the schema's JSON CHECK expressions before falling back to the
  existing generic `key_value` column.
- Added focused coverage for a neutral `event_records(payload_jsonb ...)`
  schema that mutates JSONB without a caller-supplied `jsonColumn` option and
  verifies no legacy fallback column is synthesized into the after image.

## Evidence

- Pre-edit focused guard on the accepted base:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php`
  -> `1 test files, 23 assertions, 0 failures`.
- Post-edit focused guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php`
  -> `1 test files, 28 assertions, 0 failures`.
- Direct JSONB CHECK family plus source-neutral guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php`
  -> `5 test files, 359 assertions, 0 failures`.
- API/source guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  -> `1 test files, 8 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` -> no syntax errors.
- `git diff --check -- lanes/libsqlite` -> passed.

## Dependency Closure

No new support component is needed. This reuses existing JSONB mutation,
CHECK evaluation, schema-derived rowid handling, and neutral identifier
validation.

## Non-Overlap

This is source-neutral production-source cleanup for the JSONB CHECK
current-source family. It does not add upstream runner metadata, change
dashboard counters, or duplicate JSONB CHECK fixture neutralization from the
earlier source-neutral slices.

Root harness: not run - isolated micro-slice.
