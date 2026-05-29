# VFS current-source next146-149

- Behavior: carries hydrated VFS current-source handles across the next146-149 window, reuses already-open main/temp handles, routes file-control and lock operations through the selected source, and blocks writer locks on readonly hydrated/opened sources.
- Regression covered: after-current VFS source state can be resumed without reopening an existing main handle, losing temp file-control state, or allowing a readonly archive source to take a writer lock.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-vfs-current-source-next146-149.php --self-test`
- Focused TestRunner: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php`
- Result: `1 test files, 20 assertions, 0 failures`.

Non-overlap:

This intentionally avoids pager, WAL, B-tree, PRAGMA, planner, JSON, trigger, and row-value next146-149 surfaces. It is a VFS-only after-current bridge over accepted VFS source routing and immediate current-source hydration prereqs.

Dependency closure:

No new support component is needed. The slice depends on lane-local VFS current-source hydration and the accepted URI/temp/file-control source routing pattern.
