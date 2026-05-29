# VFS SHM Lock-Byte File-Control Current Source Next112

This slice extends the current-source WAL/SHM VFS model with `xFileControl`
state routed through the active `main`/`wal`/`shm` source while database
byte-range locks decide whether write controls may mutate persistent state.

Behavior covered:

- Sidecar-first SHM opens share the canonical database owner with later main
  and WAL handles.
- Read-only and `nolock=1` paths do not admit mutating controls.
- `persist_wal`, `reserve_bytes`, `chunk_size`, and `powersafe_overwrite`
  require a reserved, pending, or exclusive database byte-range holder.
- Accepted write controls bump the owner data-version generation and leave
  already-open SHM handles stale until they observe `file_control(data_version)`.
- SHM locks and main database byte-range holders are released together for the
  same WordPress connection during yield/retry.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmLockByteFileControlCurrentSourceNext112Test.php
1 test files, 60 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsLockByteUriShmCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteVfsShmLockByteFileControlCurrentSourceNext112Test.php
2 test files, 137 assertions, 0 failures
```

Non-overlap: this does not repeat accepted VFS lock byte-range diagnostics,
process locks, lock-state apply, URI/SHM file-control next104 generation
checks, or locked writer/file writer slices. The new behavior is the combined
current-source path where SHM/WAL handles observe database file-control
generation changes gated by byte-range write locks.

Dependency closure: no new support component is needed. This reuses the
existing bounded `SQLiteFileUri`, `SQLiteLockByteRangePlan`, and
current-source SHM/lock model.
