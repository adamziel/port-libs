# Source-Neutral JSONB CHECK Current/Next Dynamic

Micro-slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260530T165213Z-0`

Base accepted HEAD: `9dc20dce32143ddf9ade7c84c6244ce48fb3e470`

## Change

- Made `SQLiteJsonbCheckCurrentNextPlan::plan()` accept a neutral `jsonColumn` option for default JSONB mutation targets.
- Preserved the existing `key_value` default for directly coupled current callers.
- Added focused coverage using generic `event_records.id` and `payload_jsonb` columns, plus validation for invalid column identifiers.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php && php -l lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php`
  - Passed, no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php`
  - Passed: 4 files, 320 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite`
  - Passed.
- `lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Not present in this isolated worktree.

## Dependency Closure

No new support component is needed. The cleanup reuses the existing JSONB mutation and CHECK evaluation helpers.

## Counter Note

No `phpPass` or mapped denominator movement is claimed; this is a bounded production source-neutral cleanup with directly coupled focused assertions.
