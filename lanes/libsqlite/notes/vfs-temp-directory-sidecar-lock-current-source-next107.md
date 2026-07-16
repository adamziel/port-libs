# vfs-temp-directory-sidecar-lock-current-source-next107

Status: focused PHP behavior growth for SQLite temp-directory sidecar lock current/next behavior.

This slice adds `SQLiteVfsTempDirectorySidecarLockCurrentSourcePlan::currentSourceNext107()`. It models current temp handles retaining their original temp-directory sidecar lock namespace after the temp directory changes, while next temp handles open in the new directory with a distinct sidecar lock key, file-control state, and lock state. It covers temp, main, and attached sources; delete-on-close cleanup; explicit handle targeting; and invalid directory/source/suffix guards.

Application path: `application-vfs-temp-directory-sidecar-lock-current-source-next107.php` previews copied `wp_options` import statement-journal handles when a temp directory handoff moves the next temp file into a new directory. Current handles keep the old sidecar lock namespace; next handles do not inherit stale locks or xFileControl state.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTempDirectorySidecarLockCurrentSourceNext107Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 67 assertions, 0 failures
```

Expected dashboard delta: `phpPass +67` focused PASS lines. `benchmarkDenominator.mapped` is unchanged; this adds current-source VFS/temp-directory sidecar lock behavior without claiming a newly hydrated upstream Tcl inventory unit.

Non-overlap: avoids accepted VFS sidecar planning, temp file lifecycle, temp lock/file-control persistence and generation, URI/SHM/open/lock/file-control current-source behavior, xOpen device characteristics, VFS lock-state/process-lock/locked-writer/sync/file-writer clusters, WAL checkpoint/savepoint/rollback clusters, B-tree freelist/overflow/page-move clusters, JSON table/source/constraint clusters, SQL text/group/order/subquery clusters, and UTF-16/Unicode GLOB behavior. The new surface is temp-directory movement splitting the current and next sidecar-lock namespaces for already-open versus newly-open temp handles.

Dependency closure: no new support component is needed. The slice reuses lane-local VFS sidecar naming, temp-file lifecycle, xFileControl, and lock-state concepts in a bounded current/next planner.
