# SQLite VFS File-Control Locking Persistence Current Source Next90

Slice: `vfs-filecontrol-locking-persistence-current-source-next90`

Behavior added:

- Adds `SQLiteVfsOpenLockFileControlCurrentSource::currentSourceNext90()` for URI-aware current-source VFS open/file-control/lock flows.
- Write-affecting file controls (`chunk_size`, `size_hint`, `reserve_bytes`, `powersafe_overwrite`) are blocked until the handle holds a write-capable byte-range lock (`reserved`, `pending`, or `exclusive`).
- Successful persistent write file-controls increment a per-source `data_version` and survive close/reopen by canonical decoded file URI path.
- Read controls such as `mmap_size` and `lock_timeout` remain usable without a write lock and do not bump `data_version`.
- `delete_on_close`, read-only handles, `nolock` URI opens, and explicit current-source rehydration preserve existing accepted behavior while proving the next90 lock-gated persistence edge.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsFileControlLockingPersistenceCurrentSourceNext90Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 57 assertions, 0 failures
57 PASS lines
```

Application smoke:

```text
php lanes/libsqlite/examples/application-vfs-filecontrol-locking-persistence-current-source-next90.php
```

Non-overlap:

- Avoids accepted next80 file-control persistence without lock gating, next82 open/lock/file-control source persistence, next86 URI open locking, next88 URI/SHM file-control locking, VFS lock state, process file locks, locked writer, VFS file writer, rollback-journal apply/commit, sync-plan/apply, WAL reader/checkpoint handoffs, and batch88 VFS URI/SHM file-control locking.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLiteFileUri` and current-source VFS open/lock/file-control runner.
