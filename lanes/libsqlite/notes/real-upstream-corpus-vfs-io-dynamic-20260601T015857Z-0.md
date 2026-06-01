# real-upstream-corpus-vfs-io-dynamic-20260601T015857Z-0

## Source truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/win32lock.test`.
- Ported behavior: Windows transient mandatory lock handling from `win32lock.test`, including AV retry windows, default and updated `file_control_win32_av_retry` values, exclusive-lock contention between ordinary handles, and invalid-handle `SQLITE_IOERR_LOCK` mapping.
- Covered upstream sections: `win32lock-1.1`, `win32lock-1.2-*`, `win32lock-2.0`, `win32lock-2.1`, `win32lock-2.2-*`, and `win32lock-3.0` through `win32lock-3.4`.

## Patch delta

- Added `SQLiteVfsIoDynamicPlan::win32AntivirusLockRetryProfile()` for the real `win32lock.test` AV-retry and lock-error behavior.
- Added `SQLiteRealUpstreamCorpusVfsIoWin32LockDynamicTest.php` with 1000 dynamic cases over retry windows, transient lock delays, row counts, payload sizes, ordinary exclusive contention, and invalid handle lock errors.
- Added `application-vfs-win32-lock-retry.php` as a generic application smoke for the retry profile.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoWin32LockDynamicTest.php` passed.
- `php -l lanes/libsqlite/examples/application-vfs-win32-lock-retry.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoWin32LockDynamicTest.php` passed: `1 test files, 27768 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-vfs-win32-lock-retry.php --self-test` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

## Non-overlap

This slice owns only upstream `win32lock.test` transient mandatory-lock retry and invalid-handle lock-error behavior. It avoids accepted VFS file-writer, sync plan/apply, lock-state, process-lock, lock-byte range, rollback-journal apply/commit, super-journal, WAL checkpoint/savepoint byte-truncation, `nolock.test`/`win32nolock.test`, `lock2`/`lock3`/`lock4`/`lock5`/`lock7`, `unixexcl`, `exclusive`, mmap, appendvfs, cksumvfs, diskfull, and multiplex/crashM coverage.

## Dependency closure

No new support component is needed. The batch reuses the existing source-neutral VFS I/O dynamic plan surface and the hydrated upstream SQLite `win32lock.test` source file.
