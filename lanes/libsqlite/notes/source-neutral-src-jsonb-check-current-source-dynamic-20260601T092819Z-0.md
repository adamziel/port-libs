# Source-Neutral JSONB CHECK Current-Source Dynamic

Slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T092819Z-0`
Base accepted HEAD: `c5d5f0d16396d91eb61e17860e23daa5d67075e3`

## Change

- Removed the remaining option-shaped parent rowid fallback from
  `SQLiteJsonbGeneratedCascadePlan`; generated-cascade actions now use an
  optional neutral `rowid_column` or generic `rowid` / `setting_id` / `id`
  fallbacks.
- Neutralized the direct JSONB generated-cascade test and example fixtures from
  `option_id`, `option_name`, and `option_value` to `setting_id`, `key_name`,
  and `key_value`.
- Extended the source-neutral JSONB CHECK current-source guard to cover the
  generated-cascade source file and its neutral rowid behavior.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonbGeneratedCascadePlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteJsonbTableGeneratedCascadeCurrentNext53Test.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - no syntax errors.
- `php -l lanes/libsqlite/examples/application-jsonb-table-generated-cascade-current-next53.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbTableGeneratedCascadeCurrentNext53Test.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - 3 files / 72 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php lanes/libsqlite/tests/SQLiteJsonbGeneratedCheckIndexCurrentNext54Test.php lanes/libsqlite/tests/SQLiteJsonbTableGeneratedCascadeCurrentNext53Test.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - 8 files / 461 assertions / 0 failures.
- `php lanes/libsqlite/examples/application-jsonb-table-generated-cascade-current-next53.php` - emitted generic generated-cascade plan with `changes: 5` and no violations.
- `git diff --check -- lanes/libsqlite` - passed.

## Dependency Closure

No new support component is needed. This reuses existing JSONB extraction,
JSONB mutation, generated-column CHECK/index handling, and row-array cascade
planning.

## Non-Overlap

This is source-neutral production cleanup only. It does not add runner metadata,
dashboard/root edits, generated pass inflation, or duplicate accepted pager,
WAL, B-tree, JSON table, or JSONB CHECK admission behavior.

Root harness: not run - isolated micro-slice.
