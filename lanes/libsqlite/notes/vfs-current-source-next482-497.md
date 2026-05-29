# VFS current-source next482-497

- Behavior: carries merged next466-481 reusable current-source publication into next482-497 by snapshotting after the `shared-cache-next481` receipt, claiming that snapshot as reusable, and publishing the follow-on `shared-cache-next497` receipt.
- Focus: keeps the dirty-source, stale acknowledgement, missing-claim, and stale preclaim fences on the direct next481 to next497 handoff without reopening prior VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext482497Plan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext482497Test.php`
  - `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next482-497.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext482497Test.php`
  - `php lanes/libsqlite/examples/wordpress-vfs-current-source-next482-497.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next466-481 and records the next482-497 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
