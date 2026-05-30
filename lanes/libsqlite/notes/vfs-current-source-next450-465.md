# VFS current-source next450-465

- Behavior: carries merged next434-449 reusable current-source publication into next450-465 by snapshotting after the `shared-cache-next449` receipt, claiming that snapshot as reusable, and publishing the follow-on `shared-cache-next465` receipt.
- Focus: keeps the dirty-source, stale acknowledgement, missing-claim, and stale preclaim fences on the direct next449 to next465 handoff without reopening prior VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php -l lanes/libsqlite/examples/application-vfs-current-source-next450-465.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php lanes/libsqlite/examples/application-vfs-current-source-next450-465.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next434-449 and records the next450-465 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
