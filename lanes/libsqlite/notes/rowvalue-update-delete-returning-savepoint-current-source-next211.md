# rowvalue-update-delete-returning-savepoint-current-source-next211

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source handling around `UPDATE OR IGNORE`.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext211Plan`
and focused coverage for copied `wp_options` repair rows:

- prior row-value UPDATE/DELETE RETURNING streams inside the savepoint remain
  yielded and current;
- `UPDATE OR IGNORE` applies non-conflicting row-value assignments and yields
  RETURNING rows only for changed rows;
- conflicting row-value updates are restored to the statement-start image and
  their attempted RETURNING rows are suppressed;
- subsequent UPDATE/DELETE RETURNING statements read from the post-ignore
  current source before the savepoint is released.

Application smoke:
`lanes/libsqlite/examples/application-rowvalue-or-ignore-savepoint-current-source-next211.php`
models copied `wp_options` repair and transient cleanup through the same
OR IGNORE current-source path.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext211Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 75 assertions, 0 failures
```

Focused test delta: +75 focused PHP PASS lines/assertions. Expected
`lane-status.json` `phpPass` moves from `102317` to `102392`; mapped upstream
coverage remains `622 / 1589`.

Non-overlap: avoids accepted next209 `OR FAIL`, next205 savepoint release,
next202 parenthesized rollback, row-value UPSERT, trigger RETURNING,
WAL/pager/VFS, B-tree, JSON, PRAGMA, encoding, planner, and suite-runner
clusters. The new surface is specifically row-value
`UPDATE OR IGNORE ... RETURNING` current-source behavior with ignored-row
RETURNING suppression and later statement chaining.

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP UPDATE/DELETE RETURNING, row-value predicate handling,
unique conflict handling, and savepoint current-source orchestration.

Root harness status: not run - isolated micro-slice.
