# Row-Value Savepoint Consolidation Sixty-Eighth Pass

Consolidated the row-value inner FAIL rollback savepoint surface away from its
worker-numbered diagnostics.

- `executeInnerFailRollbackSavepoint()` now emits unsuffixed default savepoint
  names, status, dependency keys, receipt keys, phase labels, exception text,
  and non-overlap text.
- Renamed the direct focused test to
  `SQLiteRowValueInnerFailRollbackSavepointTest.php`.
- Renamed the Application smoke to
  `application-rowvalue-inner-fail-rollback-savepoint.php`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueInnerFailRollbackSavepointTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-inner-fail-rollback-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueInnerFailRollbackSavepointTest.php`
  - `1 test files, 89 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-inner-fail-rollback-savepoint.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this cleanup reuses the
native PHP row-value UPDATE/DELETE RETURNING executor, OR FAIL preservation,
and nested savepoint current-source row-image model.

Non-overlap: consolidation-only row-value savepoint cleanup; no WAL/VFS, JSON,
planner, trigger, B-tree, rowvalue-window, or behavior-counter surface changed.
