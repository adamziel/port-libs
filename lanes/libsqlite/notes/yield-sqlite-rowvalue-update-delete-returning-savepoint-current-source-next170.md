# SQLite row-value UPDATE/DELETE RETURNING savepoint current-source next170

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext170Plan` for row-value `UPDATE OR ABORT` conflicts inside a savepoint batch.
- Models SQLite ABORT semantics for this focused executor path: the failed statement is rolled back, earlier successful UPDATE/DELETE RETURNING statements remain in the current source, yielded rows from those earlier statements stay countable, and the savepoint remains open for a retry before release.
- Includes a Application copied `wp_options` smoke where transient cleanup and staged option updates survive a later duplicate `(blog_id, option_name)` ABORT conflict, then a retry updates the current source.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext170Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-rowvalue-abort-savepoint-current-source-next170.php`
- PHP lint: `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext170Plan.php && php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext170Test.php && php -l lanes/libsqlite/examples/application-rowvalue-abort-savepoint-current-source-next170.php`
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-overlap

This slice avoids accepted `OR ROLLBACK` transaction rollback, `OR FAIL` partial row preservation, `OR IGNORE` suppressed RETURNING, trigger RETURNING/savepoint, WAL savepoint byte truncation, rollback-journal apply, VFS writer/lock/sync, JSON table planner, compound SELECT, and B-tree freeblock/freelist surfaces. It is specifically the statement-level ABORT boundary for row-value UPDATE/DELETE RETURNING savepoint batches.

## Dependency Closure

No new support component is needed. The patch reuses lane-local row-value UPDATE/DELETE RETURNING parsing/execution and savepoint current-source modeling.
