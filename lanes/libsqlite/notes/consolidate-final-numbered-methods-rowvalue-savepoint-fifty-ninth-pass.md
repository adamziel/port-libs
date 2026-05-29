# Row-Value Savepoint Consolidation Fifty-Ninth Pass

Consolidated the nested row-value savepoint rollback batch away from the old
worker-numbered diagnostics.

- `executeNestedSavepointRollbackBatch()` now emits unsuffixed exception,
  dependency, and non-overlap strings.
- Renamed the direct focused test to
  `SQLiteRowValueNestedSavepointRollbackBatchTest.php`.
- Renamed the WordPress smoke to
  `wordpress-rowvalue-nested-savepoint-rollback-batch.php`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueNestedSavepointRollbackBatchTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-nested-savepoint-rollback-batch.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointRollbackBatchTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-nested-savepoint-rollback-batch.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this cleanup reuses the
native PHP row-value UPDATE/DELETE RETURNING executor and the existing
savepoint current-source row-image model.

Non-overlap: consolidation-only row-value savepoint cleanup; no WAL/VFS, JSON,
planner, trigger, B-tree, rowvalue-window, or behavior-counter surface changed.
