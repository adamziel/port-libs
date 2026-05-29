# rowvalue-update-delete-returning-savepoint-current-source-next188

Status: focused PHP behavior growth for row-value `IN ()` / `NOT IN ()`
inside `UPDATE` / `DELETE ... RETURNING` savepoint current-source execution.

This slice extends `SQLiteUpdateDeleteReturningSql` so an empty row-value
tuple list is accepted. SQLite treats `(a,b) IN ()` as false and
`(a,b) NOT IN ()` as true. The focused coverage runs that behavior through
`DELETE RETURNING`, `UPDATE RETURNING`, RETURNING expressions, rollback-to
savepoint suppression of attempted rows, and retry statements that read from
the restored current source.

WordPress smoke:
`wordpress-rowvalue-empty-in-savepoint-current-source-next188.php --self-test`
models copied `wp_options` cleanup where an empty candidate tuple list deletes
no rows, a speculative `NOT IN ()` update is rolled back, and retry cleanup
deletes non-autoloaded rows before updating the remaining autoloaded options.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext188Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-empty-in-savepoint-current-source-next188.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext188Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-empty-in-savepoint-current-source-next188.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures
```

Expected dashboard movement: `phpPass +61` from the new focused test file.
Mapped upstream coverage remains unchanged; this is focused PHP executor
behavior over already mapped row-value DML/savepoint/RETURNING inventory.

Dependency closure: no new support component is needed. The slice reuses the
native PHP UPDATE/DELETE RETURNING executor and lane-local savepoint
current-source planning.

Non-overlap: avoids accepted next176 nullable equality, next181 nullable
`IN`/`NOT IN` membership, next184 `IN (VALUES ...)`, next185 `OR FAIL`
rollback/retry, row-value conflict/upsert/trigger RETURNING clusters, and
WAL/pager/VFS/B-tree/JSON/encoding/planner surfaces. The new surface is
specifically SQLite's empty row-value tuple-list truth table inside
UPDATE/DELETE RETURNING plus savepoint rollback suppression.

Next task: continue with a different SQL executor/planner gap or pivot to a
storage-backed pager/VFS behavior slice; avoid another row-value savepoint
variant unless it reaches a distinct upstream parser/executor behavior.
