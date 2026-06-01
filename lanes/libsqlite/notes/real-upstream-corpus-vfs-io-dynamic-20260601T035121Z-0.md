# real-upstream-corpus-vfs-io-dynamic-20260601T035121Z-0

## Source truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/win32nolock.test`.
- Ported behavior: Windows `win32-none` VFS no-lock behavior, including stale peer cache visibility until `sqlite3_release_memory`, ordinary-vs-no-lock exclusive transaction arbitration, and two no-lock handles both bypassing OS byte-range locks.
- Covered upstream sections: `win32nolock-1.2` through `win32nolock-1.7`, `win32nolock-1.9.1`, `win32nolock-1.10.1`, `win32nolock-1.11.1`, and `win32nolock-1.12.1`.

## Patch

- Added `SQLiteVfsIoDynamicPlan::win32NoLockProfile()` for the real upstream `win32nolock.test` cache and lock-arbitration behavior.
- Added `SQLiteRealUpstreamCorpusVfsWin32NoLockDynamicTest.php` with 1000 dynamic real-upstream cases plus source-truth and malformed-input guards.
- Added `application-vfs-win32-nolock.php` as a generic application self-test.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWin32NoLockDynamicTest.php` passed.
- `php -l lanes/libsqlite/examples/application-vfs-win32-nolock.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWin32NoLockDynamicTest.php` passed: `1 test files, 28683 assertions, 0 failures`, with 1006 focused PASS cases.
- `php lanes/libsqlite/examples/application-vfs-win32-nolock.php --self-test` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 4 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

## Non-overlap

This slice owns only upstream `win32nolock.test` `win32-none` cache visibility and exclusive transaction lock-arbitration behavior. It avoids accepted VFS file writer, locked writer, sync plan/apply, lock-state, process locks, lock-byte range, rollback-journal apply/commit, super-journal, WAL checkpoint/savepoint byte-truncation, `win32lock.test` AV retry, `nolock.test` URI no-lock suppression, `lock2`/`lock3`/`lock4`/`lock5`/`lock7`, mmap, appendvfs, cksumvfs, diskfull, and multiplex/crashM coverage.

## Dependency closure

No new support component is needed. The batch reuses the existing source-neutral VFS I/O dynamic plan surface and the hydrated upstream SQLite `win32nolock.test` source file.
