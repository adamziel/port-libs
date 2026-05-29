# VFS lock-byte URI SHM current-source next97

## Behavior

Adds `SQLiteVfsLockByteUriShmCurrentSourceNext`, a bounded VFS planner for
WordPress SQLite import/open paths where a SHM or WAL sidecar can be opened
before the main database. The planner canonicalizes `file:` URI paths back to a
single owner database path, routes main POSIX byte-range locks and WAL SHM locks
through the current source handle, and preserves owner-level conflicts across
main, WAL, and SHM handles.

This is deliberately narrower than the accepted lock-byte-range, lock-state,
SHM file-control, VFS writer, and rollback/sync apply clusters. It covers the
unhandled combined URI owner/source routing case for lock bytes plus SHM locks.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsLockByteUriShmCurrentSourceNextTest.php`
  - `1 test files, 77 assertions, 0 failures`
  - `77` focused PASS lines
- WordPress smoke:
  - `php lanes/libsqlite/examples/wordpress-vfs-lock-byte-uri-shm-current-source-next97.php --self-test`

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local
`SQLiteFileUri` parsing and `SQLiteLockByteRangePlan` constants/transition
planning, and adds only the missing current-source owner routing wrapper.

## Next

A follow-up VFS slice should move from owner/source lock planning into pager
transaction application only if it can prove new byte-level write or fsync
behavior without repeating accepted VFS file writer, lock-state, process-lock,
rollback-journal, sync, or savepoint apply clusters.
