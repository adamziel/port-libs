# Row-value UPDATE/DELETE RETURNING savepoint current-source next210

## Behavior

- Adds current-source coverage for row-value `UPDATE OR IGNORE ... RETURNING` inside a savepoint.
- The conflicting row is ignored and does not yield a `RETURNING` row, while earlier non-conflicting row-value updates still yield rows before rollback.
- `ROLLBACK TO` restores the savepoint image and discards the attempted `RETURNING` stream and ignored-row metadata.
- Retry `UPDATE` / `DELETE ... RETURNING` statements read the restored savepoint image, then release the savepoint as the next current source.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext210Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-rowvalue-ignore-rollback-current-source-next210.php`

## Non-overlap

This avoids accepted next209/next208 OR FAIL behavior, next203 IGNORE/REPLACE release flow, next205 RELEASE admission, next206 released-inner rollback, next178 OR ROLLBACK transaction rollback, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters.

## Dependency Closure

No new support component is needed. This reuses lane-local row-value UPDATE/DELETE RETURNING execution, unique-conflict IGNORE handling, and savepoint current-source row images.
