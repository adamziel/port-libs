# real-upstream-corpus-pager-wal-dynamic-20260530T170638Z-0

Status: focused real upstream pager/WAL corpus growth from hydrated SQLite `wal2.test`.

This slice ports distinct dynamic WAL scenarios from `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`:

- `wal2-1.2..1.12`: corrupt wal-index header fields before a reader snapshots the header, forcing recovery or readmark slot initialization while preserving consistent `count(a), sum(a)` results.
- `wal2-2.2..2.9`: checksum-valid but stale wal-index headers where the reader first sees the older snapshot, then recovers to the current snapshot after a second corruption.
- `wal2-4.1..4.3`: WAL databases require VFS `xShmOpen` support; a no-SHM VFS cannot read the WAL-backed database, while a SHM-capable VFS can.
- `wal2-5.1`: checkpoint clients that encounter stale wal-index state take checkpoint, writer, recovery, readmark, and release locks in the upstream order before backfill/reset can proceed.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- Result: `1 test files, 1493 assertions, 0 failures`, with `229` focused PASS lines.

Non-overlap: this avoids the recently accepted pager/WAL checkpoint, rollback-journal apply/commit, WAL savepoint byte truncation, WAL checkpoint transaction, VFS file writer/sync/lock, and prior real corpus pager/WAL batches. The new behavior is specifically upstream `wal2.test` wal-index header recovery, stale-header snapshot behavior, no-SHM WAL open failure, and checkpoint recovery lock ordering.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL/SHM concepts and adds a bounded source-neutral real-upstream corpus plan for the selected `wal2.test` scenarios.
