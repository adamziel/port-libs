# VFS Open File-Control Locking Current/Next 74

Slice: `vfs-open-filecontrol-locking-current-next74`

## Behavior

- Adds `SQLiteVfsOpenFileControl::currentNext74()` to model an open SQLite VFS handle as file-control calls and lock transitions interleave.
- Supports SQL-shaped `file_control(...)` and `PRAGMA ...` inputs plus shared/reserved/pending/exclusive lock operations.
- Applies `SQLITE_FCNTL_SIZE_HINT` through the existing native file handle, including chunk-size rounding, and records current/next lock holders.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsOpenFileControl.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsOpenFileControlLockingCurrentNext74Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-open-filecontrol-locking-current-next74.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenFileControlLockingCurrentNext74Test.php`

Focused result: `1 test files, 55 assertions, 0 failures` with 55 PASS lines.

- `php lanes/libsqlite/examples/wordpress-vfs-open-filecontrol-locking-current-next74.php`

Smoke result: reports `status` `released`, `preallocatedBytes` `8192`, `exclusiveHeld` `exclusive`, `persistWal` `true`, and empty `holdersAfterRelease`.

## Non-Overlap

This avoids accepted VFS file-control state transitions, VFS open size-hint-only application, VFS lock byte ranges, VFS lock state/process file locks, locked writer/sync/rollback-journal/super-journal paths, temp-file lifecycle, WAL checkpoint/savepoint byte truncation, JSON table source/cursor/constraint work, SELECT SQL text clusters, and B-tree page/freelist clusters. The new surface is the current/next interleaving of open-handle file-control side effects with lock escalation.

## Dependency Closure

No new support component is needed. The slice reuses lane-local `SQLiteVfsCapabilityPlan`, `SQLiteVfsFileControlState`, `SQLiteVfsFileHandle`, `SQLiteLockByteRangePlan`, and `SQLiteVfsLockState`.
