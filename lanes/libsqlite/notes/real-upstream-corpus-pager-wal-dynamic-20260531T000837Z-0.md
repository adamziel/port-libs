Real upstream corpus pager/WAL dynamic slice 20260531T000837Z-0

- Base accepted HEAD: 88eb6ac3e2ad25d5a4756e5a167672b605fd3e97.
- Added `SQLiteRealUpstreamPagerWalMvccRecoveryDynamicTest.php`.
- Upstream source truth: hydrated SQLite upstream files under `/home/claude/port-libs/.upstream-cache/libsqlite/test`.
- Cited upstream sections: `wal.test` wal-1.0..1.5, wal-2.1..2.6, wal-3.1..3.3, wal-4.1..4.3; `wal2.test` wal2-15.1..15.12; `walrestart.test` restart checkpoint reader-prefix behavior; `walcksum.test` checksum and truncated-tail recovery behavior.
- Focused behavior: native `SQLiteWal` frame parsing, committed transaction boundaries, MVCC reader snapshots, passive/restart/truncate checkpoint visibility, read-mark planning, uncommitted-tail rollback recovery, checksum-tail recovery, and hydrated upstream section provenance.
- Focused test result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalMvccRecoveryDynamicTest.php` => 1 test file, 1001 assertions, 0 failures.
- Expected dashboard movement: +1001 focused PASS cases/assertions if accepted; mapped denominator remains complete at 1589 / 1589.
- Non-overlap: does not touch accepted WAL persist-mode, checkpoint sync matrix, WAL protocol/no-SHM, WAL savepoint byte truncation, VFS rollback/commit/sync/file-writer, or app-domain paths. This is a new real-upstream pager/WAL MVCC/recovery dynamic corpus file.
- Dependency closure: no new support component needed; reused existing native `SQLiteWal` and `SQLiteWalHeader` primitives.
