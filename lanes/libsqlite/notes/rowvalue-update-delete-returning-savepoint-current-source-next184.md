# rowvalue-update-delete-returning-savepoint-current-source-next184

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
predicates using SQLite `IN (VALUES ...)` tuple lists.

This slice extends `SQLiteUpdateDeleteReturningSql` so row-value `IN` and
`NOT IN` predicates accept a `VALUES` tuple list, matching SQLite forms such as
`(blog_id, option_name) IN (VALUES (1, 'siteurl'), (2, 'home'))`. The focused
coverage runs the form through DELETE RETURNING, UPDATE RETURNING, RETURNING
expressions, and an OR ROLLBACK savepoint retry that restores the transaction
image before executing the retry statements from the current source.

Application smoke:
`application-rowvalue-values-savepoint-current-source-next184.php` models copied
`wp_options` cleanup where transient option keys are selected with row-value
`IN (VALUES ...)`, a savepoint conflict discards attempted RETURNING rows, and
retry UPDATE/DELETE statements read from the restored source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext184Test.php
php -l lanes/libsqlite/examples/application-rowvalue-values-savepoint-current-source-next184.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext184Test.php
php lanes/libsqlite/examples/application-rowvalue-values-savepoint-current-source-next184.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

Expected dashboard delta: `phpPass` moves from `86745` to `86795` from 50
newly passing focused PASS lines. Mapped upstream coverage remains `615 / 1589`;
this is a focused PHP executor behavior over already mapped row-value DML and
savepoint inventory.

Non-overlap: avoids accepted next176 NULL inequality, next178 OR ROLLBACK
transaction restoration, next181 row-value savepoint RETURNING behavior, row
value `IS`/`DISTINCT`/`BETWEEN` clusters, trigger RETURNING, WAL/pager/VFS,
B-tree, JSON, PRAGMA, planner, and encoding surfaces. The new surface is
specifically row-value `IN (VALUES ...)` / `NOT IN (VALUES ...)` parsing and
execution inside UPDATE/DELETE RETURNING plus savepoint current-source retry.

Dependency closure: no new support component is needed. This reuses the native
PHP UPDATE/DELETE RETURNING executor and existing transaction/savepoint
current-source plan.

Next task: continue with non-overlapping SQL executor/planner correctness or a
storage-backed savepoint/VFS application gap; avoid another row-value wrapper
unless it reaches a distinct parser/executor behavior.
