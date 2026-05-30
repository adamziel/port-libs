# VFS URI SHM File-Control Current Source Next92

This slice adds `SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext92()` and fixes the shared VFS current-source planner to canonicalize file URI owners through `SQLiteFileUri` before persisting database controls or SHM locks.

Behavior covered:

- `file://localhost/...` and percent-encoded filenames decode to the same database owner used by plain paths.
- explicit `*.sqlite-wal` and `*.sqlite-shm` filenames strip their sidecar suffix before deriving the owner, then expose exactly one `-wal` or `-shm` handle path.
- unqualified `xFileControl` calls while the current source is WAL or SHM still route to the database handle and persist controls under the decoded owner.
- `mode=ro&mode=rw` and `nolock=1` are interpreted via parsed URI parameters instead of raw substring checks.
- stale SHM locks are released and not reused after a sidecar close/reopen when the owner was supplied as a URI sidecar path.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlCurrentSourceNext92Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 57 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmFileControlLockCurrentSourceNext87Test.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlCurrentSourceNext92Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 122 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-vfs-uri-shm-filecontrol-current-source-next92.php --self-test
application-vfs-uri-shm-filecontrol-current-source-next92 self-test passed
```

Non-overlap:

This avoids accepted VFS SHM/file-control current-source next87 and batch89 lock-byte behavior by focusing on URI-normalized owner identity across localhost authority, percent-decoded filenames, explicit `-wal`/`-shm` sidecar paths, and parsed URI readonly/nolock flags. It does not repeat VFS file-writer, sync, rollback-journal, process-lock, WAL reader/checkpoint, B-tree, JSON table, SELECT SQL, or encoding clusters.

Dependency closure:

No new support component is needed. The slice reuses the existing lane-local `SQLiteFileUri` parser and VFS SHM/file-control current-source state machine.

Next task:

Continue with broader pager/VFS transaction application or another non-overlapping VFS open/file-control edge; avoid another current-source wrapper unless it proves a distinct URI, sidecar, or durability behavior.
