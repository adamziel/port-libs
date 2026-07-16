# Row-Value Savepoint Consolidation Forty-Fourth Pass

Consolidated the bounded DISTINCT subquery and DISTINCT tuple row-value
savepoint surfaces away from worker-numbered production keys. The canonical
production methods remain descriptive:

- `executeBoundedDistinctSubquerySavepointRollback()`
- `executeDistinctTupleSavepointRollback()`

The direct tests and Application examples were renamed to stable unsuffixed
filenames and now assert stable status, dependency, receipt, and metadata keys.
This is consolidation-only cleanup; it does not add new behavior, change
`phpPass`, or change mapped upstream coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueDistinctSubquerySavepointTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueDistinctTupleSavepointTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-distinct-subquery-savepoint.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-distinct-tuple-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueDistinctSubquerySavepointTest.php lanes/libsqlite/tests/SQLiteRowValueDistinctTupleSavepointTest.php`
  -> `2 test files, 129 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-distinct-subquery-savepoint.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-distinct-tuple-savepoint.php --self-test`

Dependency closure: no new support component is needed; this cleanup reuses the
existing native PHP row-value UPDATE/DELETE RETURNING, SELECT subquery tuple
materialization, and savepoint current-source retry primitives.

Non-overlap: this pass avoids rowvalue-window wrappers and only removes
numbered exposure from the row-value savepoint DISTINCT subquery/tuple direct
surface.
