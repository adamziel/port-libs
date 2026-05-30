# SQLite VFS File-Control Persist-WAL Lock Current Source Next94

Slice: `vfs-filecontrol-persistwal-lock-current-source-next94`

Behavior added:

- Adds `SQLiteVfsOpenLockFileControlCurrentSource::currentSourceNext94()` for URI-aware current-source open/file-control/lock flows where `persist_wal` follows SQLite write-control lock discipline.
- `file_control(persist_wal, ...)` is blocked until the handle has a reserved, pending, or exclusive lock, matching the existing lock-gated write-control behavior for chunk/reserve/powersafe controls.
- Successful persistent `persist_wal` changes increment the per-source `data_version`, survive close/reopen by decoded `file:` URI path, and remain distinct from read controls such as `mmap_size` and `lock_timeout`.
- Read-only handles ignore `persist_wal`, `nolock` handles cannot acquire the write lock needed for it, and explicit current-source rehydration preserves accepted persistent controls and locks.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsFileControlPersistWalLockCurrentSourceNext94Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 56 assertions, 0 failures
56 PASS lines
```

Application smoke:

```text
php lanes/libsqlite/examples/application-vfs-filecontrol-persistwal-lock-current-source-next94.php --self-test
application-vfs-filecontrol-persistwal-lock-current-source-next94 self-test passed
```

Non-overlap:

- Avoids accepted next90 file-control locking persistence for chunk/reserve/powersafe controls by adding the narrower previously ungated `persist_wal` lock edge.
- Avoids accepted VFS file writer, rollback-journal apply/commit, sync-plan/apply, process lock, VFS lock-state, SHM/file-control current-source, WAL checkpoint/savepoint, and B-tree/page-move clusters.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded `SQLiteFileUri` parsing and the current-source VFS open/lock/file-control runner.
