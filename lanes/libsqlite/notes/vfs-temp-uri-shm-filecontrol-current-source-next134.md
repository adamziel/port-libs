# VFS Temp URI SHM File-Control Current Source Next134

## Behavior

- Added `SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext134()` for current-source routing across persistent database handles, temporary URI database handles, and temporary SHM sidecars.
- Temporary URI handles are handle-scoped, delete-on-close, and do not persist file-control state across reopen.
- `persist_wal` is ignored for temporary database handles, while ordinary persistent main handles continue to persist WAL/file-control state and bump data-version generation.
- Temporary SHM handles route `data_version` to the temporary database handle, but block persistent SHM byte-range locks because SQLite temp databases use private/transient locking rather than durable `-shm` sidecar locks.
- Persistent SHM range/owner behavior from next131 remains intact for ordinary main/shm handles.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTempUriShmFileControlCurrentSourceNext134Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 58 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-vfs-temp-uri-shm-filecontrol-current-source-next134.php
```

Expected dashboard movement: `phpPass +58` from a new focused lane test file. Mapped upstream coverage is unchanged; this is current-source behavior coverage rather than a newly mapped upstream manifest row.

## Non-Overlap

This does not repeat accepted next130 temp URI byte-lock/file-control behavior or accepted next131 SHM range/owner locks. The new slice is the bridge behavior for temp URI handles plus SHM/current-source routing: temp SHM lock denial, temp data-version routing, delete-on-close cleanup, and persistent SHM regression coverage in the same planner.

## Dependency Closure

No new support component is required. The patch reuses the existing `SQLiteFileUri` parser and current VFS SHM/file-control planner.
