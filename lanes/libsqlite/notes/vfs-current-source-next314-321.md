# VFS current-source next314-321

- Behavior: carries the merged next306-313 reusable current-source publication into next314-321 by snapshotting after the `shared-cache-next313` receipt, claiming the snapshot as reusable, and publishing the follow-on `shared-cache-next321` receipt.
- Focus: fences dirty sources, stale acknowledgement receipts, missing reuse claims, and stale preclaimed publish attempts without reopening earlier VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next314-321.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
  - `php lanes/libsqlite/examples/wordpress-vfs-current-source-next314-321.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next306-313 and records the next314-321 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
