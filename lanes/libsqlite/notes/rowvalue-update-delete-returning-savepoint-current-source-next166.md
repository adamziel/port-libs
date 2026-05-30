# SQLite row-value UPDATE/DELETE RETURNING savepoint current-source next166

Status: focused PHP behavior growth for nested row-value `UPDATE` / `DELETE ... RETURNING` savepoints.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext166Plan`. It models a Application `wp_options` import cleanup where an inner savepoint performs row-value `UPDATE RETURNING` and `DELETE RETURNING`, then `RELEASE`s into an outer savepoint. A later outer rollback discards the already released inner `RETURNING` stream together with the outer attempted stream, restores the outer savepoint image, and retries from the original current source before release.

Application smoke: `application-rowvalue-nested-savepoint-current-source-next166.php` previews copied option rows where transient cleanup and option promotion from the inner release are undone by the outer rollback, then the retry yields rows from the restored table image.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext166Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext166Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-nested-savepoint-current-source-next166.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext166Test.php`
  - `1 test files, 73 assertions, 0 failures`
  - 73 `PASS rowvalue update delete returning savepoint current source next166 ...` lines

Focused test delta: +73 focused PASS lines/assertions.

Expected dashboard delta: `phpPass` moves from `74754` to `74827`. Mapped upstream coverage remains `610 / 1589`; this is additional current-source PHP behavior over already mapped row-value DML/savepoint primitives rather than a newly hydrated upstream inventory row.

Dependency closure: no new support component is needed. The slice reuses lane-local `SQLiteUpdateDeleteReturningSql` row-value DML execution and adds bounded nested savepoint composition.

Non-overlap: avoids accepted single-savepoint rollback/retry next158, FAIL retry next161, row-value ignore/replace next156, row-value DELETE-only and conflict/UPSERT clusters, trigger RETURNING savepoint work, pager/WAL/VFS savepoint application clusters, grouped/order/subquery SELECT SQL clusters, JSON table source/cursor/constraint clusters, B-tree freeblock/page-move clusters, and encoding surfaces. The new surface is specifically `RELEASE` of an inner row-value `RETURNING` savepoint being discarded by a later `ROLLBACK TO` the still-active outer savepoint before retry.

Next task: continue with broader SQL executor/planner correctness, pager/VFS transaction application, or another non-overlapping current-source DML gap only if it adds comparable focused behavior coverage.
