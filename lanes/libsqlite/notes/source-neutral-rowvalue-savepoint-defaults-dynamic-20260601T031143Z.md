# Source-Neutral Row-Value Savepoint Defaults Dynamic

Micro-slice: `source-neutral-src-rowvalue-savepoint-defaults-dynamic-20260601T031143Z-0`

## Scope

- Neutralized production defaults in these row-value savepoint helpers:
  - `SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan`
  - `SQLiteRowValueReturningSavepointConflictCurrentSourceNextPlan`
  - `SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan`
  - `SQLiteRowValueSavepointUpsertCurrentSourceNextPlan`
  - `SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan`
- Default savepoints now use `app_settings_*`.
- Default row identifiers now use `setting_id`.
- Existing option-shaped fixtures pass `option_id` explicitly where they still exercise historical data.

## Evidence

- Focused test passed: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteSavepointCurrentSourceNext126Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteSavepointCurrentSourceNext133Test.php lanes/libsqlite/tests/SQLiteRowValueReturningSavepointConflictCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteRowValueSavepointUpsertCurrentSourceNext131Test.php lanes/libsqlite/tests/SQLiteRowValueUpsertReturningConflictCurrentSourceNext134Test.php lanes/libsqlite/tests/SQLiteRowValueReturningFailSavepointCurrentSourceNext132Test.php lanes/libsqlite/tests/SQLiteRowValueDeleteReturningSavepointCurrentSourceTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Result: `9 test files, 429 assertions, 0 failures`.
- PHP lint passed for changed PHP files.
- Example smoke passed for all seven changed row-value examples with their existing self-test/CLI checks.
- `git diff --check -- lanes/libsqlite` passed.

## Dependency Closure

No new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING, UPSERT, conflict handling, and savepoint current-source helpers.
