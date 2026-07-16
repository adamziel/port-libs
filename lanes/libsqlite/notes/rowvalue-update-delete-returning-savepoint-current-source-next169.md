# Row-Value UPDATE/DELETE RETURNING Savepoint Current Source Next169

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext169Plan`.
It models the upstream SQLite `UPDATE OR ABORT` boundary for row-value
assignments inside a savepoint: prior statements in the savepoint remain
current, the conflicting statement is rolled back as a statement and yields no
`RETURNING` rows, the savepoint remains active, and retry UPDATE/DELETE
RETURNING statements continue from the preserved current source before release.

Application relevance: copied `wp_options` import batches can catch a duplicate
`(blog_id, option_name)` row-value key update without losing earlier staged
cleanup work, then retry from that current source.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext169Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 70 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-rowvalue-abort-savepoint-current-source-next169.php
application-rowvalue-abort-savepoint-current-source-next169 self-test passed
```

Status delta:

- `phpPass`: `76154 -> 76224` (`+70` focused PASS lines).
- `phpFail`: remains `0`.
- Mapped upstream coverage unchanged; this is focused current-source behavior
  coverage, not a newly mapped upstream manifest unit.

Dependency closure: no new support component is needed. This composes the
existing lane-local row-value UPDATE/DELETE RETURNING executor with savepoint
current-source behavior.

Non-overlap: avoids accepted/queued `OR IGNORE`, `OR FAIL`, `OR ROLLBACK`,
rollback-to-savepoint retry, trigger/deferred returning, WAL/pager, B-tree,
JSON, VFS, and encoding clusters. This slice is specifically the `OR ABORT`
statement rollback boundary inside a savepoint.
