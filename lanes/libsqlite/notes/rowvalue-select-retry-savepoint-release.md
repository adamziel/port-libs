# Row-Value Select Retry Savepoint Release

## Behavior

Adds a focused current-source row-value DML slice for copied WordPress options imports where `UPDATE` and `DELETE ... RETURNING` select mutation targets through row-value `IN (SELECT ...)` staging-table subqueries.

The modeled sequence is:

- run yielding `UPDATE`/`DELETE RETURNING` statements from a savepoint image;
- run additional attempted statements against the yielded current source;
- `ROLLBACK TO` the savepoint image, suppressing attempted rows;
- retry row-value `UPDATE`/`DELETE RETURNING` from the restored image;
- `RELEASE` the savepoint so the retry current source becomes the next source.

## Evidence

Focused test:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueSelectRetrySavepointReleaseTest.php`

Result:

`1 test files, 83 assertions, 0 failures`

WordPress smoke:

`php lanes/libsqlite/examples/wordpress-rowvalue-select-retry-savepoint-release.php`

Result:

`wordpress-rowvalue-select-retry-savepoint-release` JSON smoke passed and reported yielded ids `[3,4,2]`, suppressed ids `[3,4,5]`, retry ids `[3,4,6,2,7]`, and final option ids `[1,3,4,5,6]`.

## Non-Overlap

This avoids accepted yield-only rollback fencing and nested inner release discarded by outer rollback. The behavior is row-value `IN (SELECT ...)` target selection plus final savepoint `RELEASE` after retry. It also avoids trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree surfaces.

## Dependency Closure

No new support component is needed. This reuses lane-local native PHP row-value UPDATE/DELETE RETURNING dispatch, row-value subquery tuple selection, and savepoint row-image modeling.
