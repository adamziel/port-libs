# VFS current-source next370-385

- Behavior: carries merged next354-369 reusable current-source publication into next370-385 by snapshotting after the `shared-cache-next369` receipt, claiming that snapshot as reusable, and publishing the follow-on `shared-cache-next385` receipt.
- Focus: keeps the dirty-source, stale acknowledgement, missing-claim, and stale preclaim fences on the direct next369 to next385 handoff without reopening prior VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext370385Plan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext370385Test.php`
  - `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next370-385.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext370385Test.php`
  - `php lanes/libsqlite/examples/wordpress-vfs-current-source-next370-385.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next354-369 and records the next370-385 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
