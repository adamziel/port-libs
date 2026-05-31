# real-upstream-corpus-vfs-io-dynamic-20260531T015424Z-0

Base accepted HEAD: `5355cb7ecea35e8be7c9099c3c6dbf4e5ec09d23`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cksumvfs.test`
- Covered sections: `cksumvfs.test 1.3`, `1.4`, `1.5`, `1.6`, `1.7`, `1.8`, `1.9`

Patch summary:

- Added `SQLiteRealUpstreamCorpusVfsIoChecksumReserveDynamicBatchTest.php`.
- Adds 1000 dynamic reserve-byte/page-size/row-count cases plus one source-citation case.
- Exercises `SQLiteVfsIoDynamicPlan::checksumReserveProfile()` for checksum trailer reservation, usable page bytes, large/small row payload page accounting, WAL checkpoint shape, reopen counts, and integrity sequences.
- Non-overlap: avoids accepted VFS writer, rollback-journal apply/commit, WAL byte truncation, mmap, atomic-write, ioerr, lock-state, and file-control clusters.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoChecksumReserveDynamicBatchTest.php`
- Result: `1 test files, 21001 assertions, 0 failures`
- PASS-line delta: `+1001`

Status movement:

- `phpPass`: `1566206 -> 1567207` if accepted.
- Mapped denominator: unchanged at `1589 / 1589`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded native PHP VFS dynamic profile helper and the hydrated upstream SQLite corpus as source truth.

Root harness:

- Not run; isolated micro-slice.
