# source-neutral row-value savepoint defaults dynamic

Micro-slice: `source-neutral-src-rowvalue-savepoint-defaults-dynamic-20260601T122611Z-0`
Base accepted HEAD: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

## Delta

- Neutralized row-value window next240-next243 default row-id parameters from `option_id` to `setting_id`.
- Added default-row-id resolution so generic `setting_id` defaults infer the existing rowid column for legacy direct callers, while explicit malformed rowid columns are still rejected.
- Updated chained-statement retry delete id extraction to use the resolved rowid column instead of a hardcoded option-shaped key.
- Extended the source-neutral compound/window guard to cover next240-next243 and added a generic `app_settings` peer-window default behavior assertion.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php`
  - `1 test files, 32 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php`
  - `1 test files, 12 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext240Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext241Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext242Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext243Test.php`
  - `4 test files, 272 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLiteRowValueUpdateDeleteReturningWindow.*Test\.php$' | sort)`
  - `94 test files, 2940 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 6 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php`
  - no syntax errors
- `git diff --check -- lanes/libsqlite`
  - clean

## Dependency Closure

No new support component is needed. This reuses existing row-id resolution and row-value/window helpers.

## Non-overlap

This is source-neutral cleanup only for the next240-next243 row-value window default rowid surface. It does not add upstream corpus rows, dashboard counters, WordPress compatibility aliases, or new wrapper APIs.
