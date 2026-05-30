# VFS Current-source Close/Reopen Handoff

- Behavior: carries the accepted hydrated VFS source state into the close/reopen handoff, tracks owner generations for stale sidecar `data_version` reads, records full syncs, releases locks on sidecar close, opens a fresh SHM source after close, and continues to block writer locks on readonly sources.
- Regression covered: a Application SQLite connection can resume with main and WAL handles from prior current-source state, observe stale WAL generation after a main file-control change, close the stale WAL sidecar without dropping main state, reopen SHM with the current owner generation, and reject readonly archive writer locks.
- Application smoke: retired with the numbered direct example during consolidation; retained behavior is covered by `SQLiteVfsCurrentSourceNextTest.php`.
- Focused TestRunner: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`

Non-overlap:

This is intentionally VFS-only. It does not modify the existing pager, WAL, B-tree, PRAGMA, planner, JSON, trigger, or row-value surfaces already present in the lane.

Dependency closure:

The slice depends on the validated `vfs-current-source-next146-149` handoff and extends the same lane-local current-source hydration model with close/reopen generation checks.
