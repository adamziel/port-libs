# VFS current-source next434-449

- Behavior: carries merged next418-433 reusable current-source publication into next434-449 by snapshotting after the `shared-cache-next433` receipt, claiming that snapshot as reusable, and publishing the follow-on `shared-cache-next449` receipt.
- Focus: keeps the dirty-source, stale acknowledgement, missing-claim, and stale preclaim fences on the direct next433 to next449 handoff without reopening prior VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php -l lanes/libsqlite/examples/application-vfs-current-source-next434-449.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php lanes/libsqlite/examples/application-vfs-current-source-next434-449.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next418-433 and records the next434-449 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
