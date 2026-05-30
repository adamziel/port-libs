# rowvalue-update-delete-returning-savepoint-current-source-next217

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source handling around `UPDATE OR ROLLBACK`.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext217Plan`
and focused coverage for copied `wp_options` repair rows:

- pre-rollback row-value UPDATE and DELETE statements yield RETURNING rows;
- `UPDATE OR ROLLBACK` on a row-value unique conflict suppresses the failing
  RETURNING stream and rolls back the whole transaction image, not just the
  statement or inner savepoint;
- the failed rollback closes the active savepoint and discards pre-rollback
  current-source changes;
- retry UPDATE/DELETE RETURNING statements open a new savepoint and read from
  the restored transaction image.

Application smoke:
`lanes/libsqlite/examples/application-rowvalue-rollback-savepoint-current-source-next217.php`
models copied `wp_options` import recovery where a row-value unique conflict
aborts the transaction, then a retry updates option rows and deletes transient
rows from the restored current source.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext217Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 83 assertions, 0 failures
```

Focused test delta: +83 focused PHP PASS lines/assertions. Expected
`lane-status.json` `phpPass` moves from `105283` to `105366`; mapped upstream
coverage remains `623 / 1589`.

Non-overlap: avoids accepted next210/next211 OR IGNORE rollback/current-source
handling, next209/next207 OR FAIL, next192 statement-only OR ABORT, row-value
UPSERT, trigger RETURNING, WAL/pager/VFS, B-tree, JSON, PRAGMA, encoding,
planner, and suite-runner clusters. The new surface is specifically
transaction-level `UPDATE OR ROLLBACK ... RETURNING` current-source behavior
with suppressed failing RETURNING rows and retry after transaction rollback.

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP UPDATE/DELETE RETURNING, row-value predicate handling,
unique conflict handling, and savepoint current-source orchestration.

Root harness status: not run - isolated micro-slice.
