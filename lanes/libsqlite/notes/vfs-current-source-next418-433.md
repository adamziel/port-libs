# VFS current-source next418-433

- Behavior: carries merged next402-417 reusable current-source publication into next418-433 by snapshotting after the `shared-cache-next417` receipt, claiming that snapshot as reusable, and publishing the follow-on `shared-cache-next433` receipt.
- Focus: keeps the dirty-source, stale acknowledgement, missing-claim, and stale preclaim fences on the direct next417 to next433 handoff without reopening prior VFS lock, WAL checkpoint, dirty flushing, or B-tree behavior.
- Tests:
  - `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext418433Plan.php`
  - `php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext418433Test.php`
  - `php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next418-433.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext418433Test.php`
  - `php lanes/libsqlite/examples/wordpress-vfs-current-source-next418-433.php --self-test`

This slice is intentionally VFS current-source only. It is the direct follow-on to next402-417 and records the next418-433 receipt chain without modifying prior VFS slices or unrelated pager, WAL, B-tree, JSON, planner, PRAGMA, trigger, or row-value surfaces.
