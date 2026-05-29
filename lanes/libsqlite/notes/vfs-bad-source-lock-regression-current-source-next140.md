# VFS bad-source lock regression current-source next140

- Behavior: hardens `SQLiteVfsShmFileControlLockCurrentSourcePlan` current-state hydration so invalid source names, source-handle keys, hydrated handle sources, SHM lock names, SHM lock modes, and SHM owner-lock keys are rejected before any current-source transition.
- Regression covered: the batch137 blocker named `SQLiteVfsShmFileControlLockCurrentSourceNext87Test` bad-source exception behavior. This slice makes the rejection shared by next87, next126, and next131 instead of only rejecting direct operation input.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-vfs-bad-source-lock-regression-current-source-next140.php --self-test`
- Focused TestRunner: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsBadSourceLockRegressionCurrentSourceNext140Test.php`
- Result: `1 test files, 41 assertions, 0 failures`.

Non-overlap:

This avoids accepted VFS lock byte ranges, VFS lock state, process file locks, locked writer, VFS sync/write/rollback apply, URI SHM range lock next131 behavior, SHM owner tracking next126 behavior, and original SHM file-control routing next87 behavior. The new surface is current-source hydration validation for stale or corrupted source/lock namespaces before the accepted lock planners run.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local VFS SHM current-source routing and lock-state primitives and only adds stricter native PHP state admission.
