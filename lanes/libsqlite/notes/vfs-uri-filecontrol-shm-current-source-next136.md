# VFS URI File-Control SHM Current Source Next136

## Behavior

This slice extends the existing VFS URI/SHM current-source planner with
`currentSourceNext136()`.

- URI `xFileControl` probes can be routed explicitly to `main`, `wal`, or `shm`
  in string operations such as `file_control(uri_parameter, role) on shm`.
- SHM `xLock` ownership now records the source handle that acquired each SHM
  lock for this next136 path.
- Closing the SHM source releases SHM locks acquired through that SHM handle,
  while preserving main database byte-range locks and persistent file-control
  state.
- Reopening SHM after a persistent file-control generation bump starts from the
  current owner generation and reads the new handle's URI parameters.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriFileControlShmCurrentSourceNext136Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

Adjacent VFS regression:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsLockByteUriShmCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteVfsOpenShmFileControlUriCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteVfsShmLockByteUriFileControlCurrentSourceNext117Test.php lanes/libsqlite/tests/SQLiteVfsShmLockByteFileControlCurrentSourceNext112Test.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 256 assertions, 0 failures
```

## Non-Overlap

Avoided accepted VFS lock-state, file-writer, rollback-journal, temp locking,
next128 URI file-control, and next130 temp URI file-control surfaces. This
patch focuses only on targeted URI file-control routing plus SHM-handle close
release semantics for the assigned next136 current-source behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP URI parser, lock-byte planner, and SHM current-source planner.
