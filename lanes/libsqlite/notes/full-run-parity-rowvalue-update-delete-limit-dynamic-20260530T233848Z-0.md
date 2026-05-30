# Row-value UPDATE/DELETE dynamic LIMIT parity

Status: focused behavior fix for generic UPDATE/DELETE RETURNING LIMIT
expressions used by row-value parity cases.

This slice extends `SQLiteUpdateDeleteReturningSql` LIMIT/OFFSET evaluation so
constant scalar expressions already supported by the SQL expression evaluator,
including `length(...)` and simple `CASE`, can participate in `LIMIT`, `OFFSET`,
and comma-form `LIMIT offset,count` clauses. The behavior is covered by a
source-neutral `app_settings` test matrix for row-value predicates plus
dynamic ordered UPDATE/DELETE selection.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteRowValueCurrentSourceNext110Test.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningRowValueCurrentSourceNext125Test.php lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourcePlanTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Result: `6 test files, 282 assertions, 0 failures`

Focused assertion delta: +26 new assertions in
`SQLiteUpdateDeleteLimitDynamicExpressionTest.php`.

Dependency closure: no new support component is needed; the change reuses the
existing native UPDATE/DELETE RETURNING expression evaluator and row-array
LIMIT/OFFSET executor.

Non-overlap: this does not repeat the prior row-id resolution fix, nested
savepoint returning behavior, row-value comparison matrix, WAL/app rollback,
or upstream corpus metadata. It is limited to constant dynamic LIMIT/OFFSET
expression parity for UPDATE/DELETE RETURNING.
