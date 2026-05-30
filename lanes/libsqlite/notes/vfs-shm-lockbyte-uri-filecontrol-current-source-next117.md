# VFS SHM Lockbyte URI File-Control Current Source Next117

Status: focused PHP corpus growth for current-source SHM/WAL handles observing
and refreshing database `data_version` after URI-routed file-control writes.

Behavior covered:

- Adds `SQLiteVfsLockByteUriShmCurrentSourceNext::currentSourceNext117()` as
  the next dependency marker for URI, SHM, lock-byte, and file-control current
  source behavior.
- Models `file_control(data_version, refresh)` as a bounded current-source
  refresh for already-open SHM/WAL handles after another source writes
  `persist_wal`, `reserve_bytes`, or `chunk_size`.
- Keeps refresh scoped to the active handle: refreshing WAL does not silently
  refresh an older stale SHM handle.
- Preserves existing write gating through reserved/pending/exclusive database
  byte locks and readonly/nolock handling.
- Covers `file://localhost` URI owner canonicalization for Application copied
  database sidecars.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmLockByteUriFileControlCurrentSourceNext117Test.php
1 test files, 56 assertions, 0 failures
```

Non-overlap: this avoids accepted VFS lock byte ranges, process file locks,
lock-state apply, VFS file writer/locked writer, rollback/sync apply, URI/SHM
file-control generation checks through next104/next112, and WAL checkpoint or
savepoint durability clusters. The new behavior is the handle-local
current-source refresh after URI-routed database file-control writes.

Dependency closure: no new support component is needed. This reuses existing
lane-local `SQLiteFileUri`, `SQLiteLockByteRangePlan`, and current-source
SHM/WAL handle state.
