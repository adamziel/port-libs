# VFS URI SHM File-Control Current Source Next104

## Behavior

- Adds `SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext104()`.
- Combines decoded `file:` URI main/WAL/SHM owner routing with current-source generation tracking.
- `xFileControl(data_version)` reports freshness for the caller's current source handle, while mutating controls still persist on the decoded database owner.
- Mutating file controls from a WAL or SHM sidecar bump the owner generation and make older main/sidecar handles stale until reopened or used as the mutating source.
- SHM byte locks remain routed only to the SHM handle and are not treated as database file-control state.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlCurrentSourceNext104Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 56 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmFileControlOpenCurrentSourceNext91Test.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlCurrentSourceNext92Test.php lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlCurrentSourceNext99Test.php lanes/libsqlite/tests/SQLiteVfsLockByteUriShmCurrentSourceNextTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 244 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-vfs-uri-shm-filecontrol-current-source-next104.php
Self-test passed and emitted JSON for the copied Application database URI/sidecar scenario.
```

## Non-Overlap

This avoids accepted VFS file writer, lock-state/process-lock, rollback-journal apply/commit, sync plan/apply, URI open lock-byte, VFS open/file-control state-transition, WAL reader-pin restart/truncate, and batch68/99 file-control clusters. The new behavior is specifically current-source generation freshness across URI-decoded main/WAL/SHM sidecar handles.

## Dependency Closure

No new support component is required. The slice reuses existing bounded `SQLiteFileUri` parsing and the existing VFS SHM/file-control current-source planner.
