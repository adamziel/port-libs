# Row-value delete/update savepoint method consolidation

Consolidated the direct `SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan`
numbered production entrypoints:

- the former distinct-returning generated entrypoint is now `executeDistinctReturningSavepoint()`.
- the former between-cleanup generated entrypoint is now `executeBetweenCleanupSavepoint()`.
- Numbered private helpers for the same family were folded into shared
  descriptive helpers.

Direct tests and WordPress examples for the delete/update and distinct
row-value savepoint scenarios were renamed away from generated numeric suffixes
and migrated to the canonical methods. This is a consolidation-only slice; it
preserves behavior and does not claim new `phpPass` growth.

Verification:

- `php -l` passed for the changed production class, four tests, and four
  examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourcePlanTest.php lanes/libsqlite/tests/SQLiteRowValueReturningDistinctSavepointCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteRowValueReturningSavepointDistinctCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextTest.php`
  passed with `4 test files, 222 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-rowvalue-delete-update-savepoint-current-source.php --self-test`
  passed.
- `php lanes/libsqlite/examples/wordpress-rowvalue-delete-update-savepoint-between-cleanup.php`
  completed and emitted the expected released savepoint summary.
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-distinct-savepoint-current-source.php --self-test`
  passed.
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-savepoint-distinct-current-source.php --self-test`
  passed.

Dependency closure: no new support component is needed; the existing
`SQLiteUpdateDeleteReturningSql` behavior is reused.
