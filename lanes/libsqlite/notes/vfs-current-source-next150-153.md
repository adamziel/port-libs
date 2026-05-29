# VFS current-source next150-153

- Behavior: carries the accepted next146-149 hydrated VFS source state into next150-153, tracks owner generations for stale sidecar `data_version` reads, records full syncs, releases locks on sidecar close, opens a fresh SHM source after close, and continues to block writer locks on readonly sources.
- Regression covered: a WordPress SQLite connection can resume with main and WAL handles from prior current-source state, observe stale WAL generation after a main file-control change, close the stale WAL sidecar without dropping main state, reopen SHM with the current owner generation, and reject readonly archive writer locks.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-vfs-current-source-next150-153.php --self-test`
- Focused TestRunner: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext150153Test.php`

Non-overlap:

This is intentionally VFS-only. It does not modify the existing pager, WAL, B-tree, PRAGMA, planner, JSON, trigger, or row-value next150-153 surfaces already present in the lane.

Dependency closure:

The slice depends on the validated `vfs-current-source-next146-149` handoff and extends the same lane-local current-source hydration model with close/reopen generation checks.
