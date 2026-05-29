## Row-Value Savepoint Numbered Method Consolidation, Ninth Pass

This pass removes the production numbered row-value savepoint method families for the rollback-returning and nested-savepoint rollback variants:

- `executeNext146()` and its private `*Next146` helpers are now `executeRollbackReturningTransaction()` plus descriptive rollback-returning helpers.
- `executeNext157()` and its private `*Next157` helpers are now `executeNestedSavepointRollbackBatch()` plus descriptive nested-savepoint helpers.

Direct tests and WordPress examples now call the stable descriptive methods. Historical scenario strings and dependency markers are left intact so the existing assertions continue to describe the accepted behavior.

Verification:

- `php -l` on the changed source, two tests, and two examples: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext146Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext157Test.php`: `2 test files, 134 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-rowvalue-rollback-returning-current-source-next146.php --self-test`: pass.
- `php lanes/libsqlite/examples/wordpress-rowvalue-update-delete-returning-savepoint-current-source-next157.php --self-test`: pass.
- `git diff --check -- lanes/libsqlite`: pass.

Dependency closure: no new support component needed; this is a production method-name consolidation only.
