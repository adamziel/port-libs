# VFS current-source next402-417

- Behavior: carries merged next386-401 reusable current-source publication into next402-417 by snapshotting after the `shared-cache-next401` receipt, claiming that snapshot as reusable, and publishing the follow-on `shared-cache-next417` receipt.
- Focus: keeps the dirty-source, stale acknowledgement, missing-claim, and stale preclaim fences on the direct next401 to next417 handoff without reopening prior VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php -l lanes/libsqlite/examples/application-vfs-current-source-next402-417.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php lanes/libsqlite/examples/application-vfs-current-source-next402-417.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next386-401 and records the next402-417 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
