# VFS current-source next338-353

- Behavior: carries the merged next322-337 reusable current-source publication into next338-353 by snapshotting after the `shared-cache-next337` receipt, claiming the snapshot as reusable, and publishing the follow-on `shared-cache-next353` receipt.
- Focus: fences dirty sources, stale acknowledgement receipts, missing reuse claims, and stale preclaimed publish attempts without reopening earlier VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next338-353.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php lanes/libsqlite/examples/wordpress-vfs-current-source-next338-353.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next322-337 and records the next338-353 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
