# VFS Lock/Open File-Control Persistence Current/Next75

Implemented `SQLiteVfsFileControlPersistence`, a bounded native PHP coordinator for current/next open-handle file-control persistence. It opens through the existing VFS capability plan, acquires a reserved byte-range lock, applies xFileControl state, persists only durable controls to a lane-local sidecar, releases the lock on close, and rehydrates the next open from the sidecar.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsFileControlPersistenceCurrentNext75Test.php`
- Result: `1 test files, 268 assertions, 0 failures`
- PASS lines: 42

Application smoke:

- `php lanes/libsqlite/examples/application-vfs-filecontrol-persistence-current-next75.php`
- Scenario: copied `wp_options` database persists `persist_wal`, `chunk_size`, `reserve_bytes`, and `mmap_size` across reopen, while per-connection `name_hint` and `lock_timeout` do not leak to the next handle.

Non-overlap:

This avoids accepted VFS file-control state transitions/current-next64/68/69, VFS open size-hint application, lock byte ranges, lock state/process locks, locked writer, file writer, sync plan/apply, rollback-journal apply/commit, savepoint rollback, and WAL checkpoint transaction clusters. The new behavior is the close/reopen persistence boundary that composes open admission, lock acquisition/release, durable file-control sidecar state, and next-handle rehydration.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP VFS capability, lock-byte-range, lock-state, file-control, and file-handle primitives.
