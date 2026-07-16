# vfs-wal-shm-lock-byte-current-source

This slice adds `SQLiteVfsWalShmLockByteCurrentSourceNext`, a bounded native
PHP planner for WAL-mode current-source transitions that must satisfy both the
main database POSIX lock-byte state and the WAL SHM lock state.

Focused behavior:

- Reader shared lock bytes and SHM read locks block a writer from reaching main
  database `exclusive` until the current reader yields.
- A writer can progress through `reserved` and `pending` while a shared reader
  remains current, then acquire the exclusive byte range after that reader is
  removed from the current source.
- SHM shared/exclusive conflicts are tracked independently from main lock-byte
  conflicts, preserving WAL checkpoint/recover lock ownership.
- `nolock` sources block POSIX byte-range acquisition while still allowing the
  separate SHM lock diagnostic path to be represented for repair tooling.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsWalShmLockByteCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsWalShmLockByteCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-wal-shm-lock-byte-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsWalShmLockByteCurrentSourceTest.php`
- `php lanes/libsqlite/examples/application-vfs-wal-shm-lock-byte-current-source.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This does not repeat accepted VFS lock byte ranges, URI open lock-byte
current-source admission, VFS SHM/file-control lock current-source behavior,
file-control persistence, VFS lock-state/process-lock/locked-writer behavior,
VFS sync/apply, WAL byte truncation, WAL checkpoint transactions, or batch87
SHM/file-control locking. The new surface is the combined current-source
boundary between main database byte-range locks and WAL SHM lock ownership.

Dependency closure:

No new support component is required. The slice reuses the lane-local lock-byte
range planner and VFS/SHM lock concepts, adding only a bounded coordination
primitive for later pager/VFS WAL transaction application.
