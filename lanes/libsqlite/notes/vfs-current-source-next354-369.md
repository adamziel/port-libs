# VFS current-source next354-369

- Behavior: carries merged next338-353 reusable current-source publication into next354-369 by snapshotting after the `shared-cache-next353` receipt, claiming that snapshot as reusable, and publishing the follow-on `shared-cache-next369` receipt.
- Focus: keeps the dirty-source, stale acknowledgement, missing-claim, and stale preclaim fences on the direct next353 to next369 handoff without reopening prior VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next354-369.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php lanes/libsqlite/examples/wordpress-vfs-current-source-next354-369.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next338-353 and records the next354-369 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
