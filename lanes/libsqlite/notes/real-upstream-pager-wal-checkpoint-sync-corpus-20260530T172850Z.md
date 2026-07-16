# Real Upstream Pager/WAL Checkpoint Sync Corpus

- Slice: `real-upstream-corpus-pager-wal-dynamic-20260530T172850Z-0`
- Base accepted HEAD: `3c71f3e7ae505629a27d91487b87ceab9ac9eac4`
- Added test file: `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointSyncCorpusTest.php`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- Ported scenario ranges:
  - `wal.test`: `wal-5.1..5.5`, `wal-7.1..7.2`, `wal-8.1..8.3`, and `wal-10.*` checkpoint lock/reader cases.
  - `wal2.test`: `wal2-6.1..6.6` journal-mode transition cases and `wal2-13.*` checkpoint fullfsync/sync-mode cases.
- Focused behavior: `SQLiteWal` frame parsing, last committed transaction detection, checkpoint mode results, reader visibility stability, uncommitted WAL tail preservation, and `SQLiteVfsSyncPlan` rollback commit sync sequencing across `off`, `normal`, and `full` sync modes.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointSyncCorpusTest.php` passed with `1 test files, 627 assertions, 0 failures` and 55 PASS lines.
- Expected dashboard movement: `phpPass` `216146 -> 216201`; mapped denominator unchanged at `958 / 1589`.
- Dependency closure: no new support component needed; reused existing `SQLiteWal`, `SQLiteWalHeader`, and `SQLiteVfsSyncPlan` behavior.
- Non-overlap: this avoids accepted WAL savepoint byte truncation, rollback-journal apply/commit, checkpoint transaction wrapper, VFS file writer/sync apply, and earlier real-upstream WAL warm-body/recovery/savepoint rollback corpus files.
