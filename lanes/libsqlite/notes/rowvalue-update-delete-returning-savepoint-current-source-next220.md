# rowvalue-update-delete-returning-savepoint-current-source-next220

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source handling around `UPDATE OR ABORT`.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext220Plan`
and focused coverage for copied `wp_options` repair rows:

- pre-abort row-value UPDATE and DELETE statements yield RETURNING rows inside
  an open savepoint;
- `UPDATE OR ABORT` on a row-value unique conflict suppresses the failing
  RETURNING stream and rolls back only that statement;
- prior statements in the same savepoint remain current after the statement
  abort;
- retry UPDATE/DELETE RETURNING statements read from the preserved pre-abort
  current source before releasing the savepoint.

Application smoke:
`lanes/libsqlite/examples/application-rowvalue-abort-savepoint-current-source-next220.php`
models copied `wp_options` import recovery where a row-value unique conflict
aborts one statement, then a retry updates option rows and deletes transient
rows without resurrecting rows deleted before the failed statement.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext220Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 87 assertions, 0 failures
```

Focused test delta: +87 focused PHP PASS lines/assertions. Expected
`lane-status.json` `phpPass` moves from `106763` to `106850`; mapped upstream
coverage remains `624 / 1589`.

Non-overlap: avoids accepted next217 transaction `OR ROLLBACK`, next210/next211
`OR IGNORE`, next209/next207 `OR FAIL`, next212 subquery rollback, row-value
UPSERT, trigger RETURNING, WAL/pager/VFS, B-tree, JSON, PRAGMA, encoding,
planner, and suite-runner clusters. The new surface is statement-level
`UPDATE OR ABORT ... RETURNING` suppression inside a preserved savepoint.

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP UPDATE/DELETE RETURNING, row-value predicate handling,
unique conflict handling, and savepoint current-source orchestration.

Root harness status: not run - isolated micro-slice.
