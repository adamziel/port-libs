# VFS URI Temp Locking File-Control Current Source

This slice adds a bounded current-source model for SQLite URI temp handles used by copied WordPress import flows. It is intentionally separate from accepted SHM/current-source, process-lock, locked-writer, sync, rollback-journal, and file-control persistence clusters.

Behavior covered:

- URI main database opens remain persistent and can store selected file-control state.
- `mode=memory` / temp source handles are delete-on-close and keep file-control plus lock state handle-local.
- Temp `persist_wal` is ignored, while `locking_mode=exclusive` creates a handle-local blocker.
- Readonly and `nolock=1` temp/current handles reject writer or byte-lock attempts with explicit reasons.
- Closing a temp handle clears the temp owner state, so reopening creates a fresh owner with no reused locks.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriTempLockingFileControlTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-uri-temp-locking-filecontrol.php --self-test`
- `php -l` for changed PHP files
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the existing bounded SQLite file URI parser and lane-local VFS state models.
