# Row-Value Nested Release Outer Rollback Savepoint Consolidation

Date: 2026-05-29

Scope:
- Consolidated the direct row-value nested inner RELEASE plus outer ROLLBACK TO savepoint variant away from `next230` production/test/example references.
- Renamed the direct focused test and Application smoke to descriptive unsuffixed filenames.
- Kept behavior unchanged: the canonical `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint()` entry point still covers the same nested savepoint release, rollback, retry, and RETURNING suppression path.

Dependency closure:
- No new support component needed. This reuses native row-value UPDATE/DELETE RETURNING execution, subquery row-value predicates, and savepoint current-source images already in the lane.

Verification:
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueNestedReleaseOuterRollbackSavepointTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-nested-release-outer-rollback-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedReleaseOuterRollbackSavepointTest.php` -> `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-nested-release-outer-rollback-savepoint.php --self-test`
- `git diff --check -- lanes/libsqlite`
