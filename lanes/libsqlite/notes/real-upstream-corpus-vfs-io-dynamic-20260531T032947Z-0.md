# real-upstream-corpus-vfs-io-dynamic-20260531T032947Z-0

Lane: `libsqlite`
Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T032947Z-0`
Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/syscall.test`
- `syscall.test 4.1`: attached database setup for the retry corpus.
- `syscall.test 4.2.wal.1-19`: retry `open()` after `EINTR` during attached-database WAL commit.
- `syscall.test 4.2.delete.1-19`: retry `open()` after `EINTR` during attached-database rollback-journal commit.
- `syscall.test syscall-5.*`: closing database handles in the same process must not drop locks held by peer handles on the same file.

## Implementation

- Added `SQLiteVfsIoDynamicPlan::syscallEintrOpenRetryProfile()`.
- Added `SQLiteVfsIoDynamicPlan::syscallClosePreservesPeerLockProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsSyscallRetryLockDynamicTest.php` with 1,000 dynamic upstream behavior cases plus source/provenance and malformed-input guards.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - PASS: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyscallRetryLockDynamicTest.php`
  - PASS: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyscallRetryLockDynamicTest.php`
  - PASS: `1 test files, 18540 assertions, 0 failures`
  - PASS lines: `1003`

Expected selected movement: `1854061 -> 1855064 pass / 0 fail`.
Mapped denominator coverage remains `1589 / 1589`.

## Non-Overlap

This does not repeat accepted syscall registry, single-byte-open, chunk-size 8.1/8.2, VFS file writer, locked writer, lock-state, process-lock sidecar, sync plan/apply, rollback-journal apply/commit, super-journal, appendvfs, cksumvfs, memory journal, subjournal, sysfault, ioerr, atomic/crash, WAL checkpoint/savepoint, mmap, quota/quota2, delete_db, or `io.test` device-matrix batches. The owned gap is upstream `syscall.test` attached-commit `EINTR` retry and same-process close/lock preservation.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded native VFS I/O dynamic planner and extends it with source-neutral syscall retry and same-process lock-preservation profiles.

## Follow-Up

Remaining nearby source sections include `syscall.test 6.1/6.2` temp/handle close behavior and `syscall.test 8.3/8.4` smaller chunk-size hint variants, if they are still non-overlapping at integration time.
