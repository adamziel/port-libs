# VFS Locking URI File-Control Current Source Next135

Status: focused PHP corpus growth for URI-routed SHM/WAL file-control writes
that must refresh the current-source `data_version` before applying write
controls through a stale handle.

Behavior covered:

- Adds `SQLiteVfsLockByteUriShmCurrentSourceNext::currentSourceNext135()` as
  the bounded next135 dependency marker for VFS locking, URI file-control, and
  current-source generation behavior.
- Blocks write file-controls such as `chunk_size` and
  `powersafe_overwrite` when the active SHM/WAL URI handle opened at an older
  source generation than the database owner now reports.
- Preserves `file_control(data_version)` and
  `file_control(data_version, refresh)` as the explicit freshness probe and
  refresh path before sidecar write controls can proceed.
- Keeps URI read helper controls countable while stale; `uri_parameter` and
  `uri_boolean` inspect the opened URI and do not mutate owner state.
- Preserves existing priority for readonly and missing byte-lock blockers:
  readonly write controls are ignored before stale-write rejection, and write
  controls without a reserved/pending/exclusive byte lock remain blocked by the
  lock requirement first.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsLockingUriFileControlCurrentSourceNext135Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 64 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-vfs-locking-uri-filecontrol-current-source-next135.php
{
    "scenario": "application-vfs-locking-uri-filecontrol-current-source-next135",
    "status": "ok",
    "owner": "/srv/www/wp-content/database/wp fresh.sqlite",
    "staleShmWriteStatus": "blocked",
    "staleShmWriteReason": "stale_current_source_requires_data_version_refresh",
    "refreshedShmChunkSize": 8192,
    "mainReserveBytes": 32,
    "staleWalWriteStatus": "blocked",
    "staleWalWriteReason": "stale_current_source_requires_data_version_refresh",
    "refreshedWalPowersafeOverwrite": true,
    "generation": 5,
    "dependencies": [
        "sqlite-file-uri",
        "sqlite-lock-byte-range-current-next",
        "sqlite-wal-shm-locks",
        "vfs-locking-uri-filecontrol-current-source-next135",
        "vfs-current-source-file-control-data-version",
        "vfs-current-source-uri-file-control",
        "vfs-current-source-stale-write-refresh"
    ]
}
```

Non-overlap: this avoids accepted VFS lock byte ranges, file-control
generation checks through next117/next128, process file locks, lock-state
apply, VFS file writer/locked writer, rollback/sync apply, temp URI locking,
and queued/conflicting SHM file-control lock current-source next132 work. The
new behavior is stricter stale current-source write gating before URI-routed
SHM/WAL file-control writes.

Dependency closure: no new support component is needed. This reuses existing
lane-local `SQLiteFileUri`, `SQLiteLockByteRangePlan`, and current-source
VFS owner generation state.
