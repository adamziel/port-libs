# real-upstream-corpus-pager-wal-dynamic-20260530T235043Z-0

Added `SQLiteRealUpstreamPagerWalHashSidecarDynamicTest.php` as an additive real upstream pager/WAL corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- Owned subtest ranges: `wal2-11.1`, `wal2-11.2`, `wal2-12.1`, `wal2-12.2`, `wal2-13.1`, `wal2-13.2`, `wal2-13.3`, and `wal2-13.4`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalHashSidecarDynamicTest.php`
- Result: `1 test files, 17001 assertions, 0 failures`, with 1001 distinct TestRunner PASS cases.

Non-overlap:

- This batch targets `wal2.test` hash-table read/write stability and WAL/SHM sidecar open-permission boundaries.
- It does not repeat accepted WAL snapshot boundary, crash recovery, checkpoint sync/noop, overwrite/restart, savepoint rollback, persist mode, WAL protocol, VFS writer/sync/lock, rollback-journal apply, or pager real-pager recovery batches.

Countability:

- Expected selected PHP PASS-line movement: `1202711 -> 1203712` (+1001).
- Mapped denominator remains `1589 / 1589`; this is behavior coverage over already mapped upstream WAL inventory, not mapped-denominator growth.

Dependency closure:

- No new support component is required. The test reuses native `SQLiteWal` parsing, checksum recovery, transaction recovery, checkpoint result, and reader snapshot behavior.
