# Row-value savepoint method consolidation seventh pass

Consolidated the row-value savepoint ordered-limit, subquery-limit, and distinct-subquery public entrypoints away from generated `executeNextNN` production method names:

- `executeOrderedLimitSubquerySavepoint()`
- `executeSubqueryLimitSavepoint()`
- `executeDistinctSubquerySavepoint()`

Also renamed the direct numbered marker helpers for the ordered-limit and distinct-subquery wrappers to descriptive private helper names, and migrated the direct tests/examples for those three scenarios.

Verification:

- `php -l` on changed source, tests, and examples: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext213Test.php lanes/libsqlite/tests/SQLiteRowValueSubqueryLimitSavepointRetryTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext216Test.php`: 3 test files, 146 assertions, 0 failures.
- `php lanes/libsqlite/examples/wordpress-rowvalue-order-limit-savepoint-current-source-next213.php --self-test`: pass.
- `php lanes/libsqlite/examples/wordpress-rowvalue-subquery-limit-savepoint-retry.php --self-test`: pass.
- `php lanes/libsqlite/examples/wordpress-rowvalue-distinct-subquery-savepoint-current-source-next216.php --self-test`: pass.
- `git diff --check -- lanes/libsqlite`: pass.

Dependency closure: no new support component is needed; this is a production symbol consolidation over existing native PHP row-value UPDATE/DELETE RETURNING, SELECT subquery tuple materialization, and savepoint retry-image behavior.
