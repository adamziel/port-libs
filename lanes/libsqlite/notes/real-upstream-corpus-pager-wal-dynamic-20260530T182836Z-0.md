# real-upstream-corpus-pager-wal-dynamic-20260530T182836Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`

Ported behavior:

- `wal.test` `wal-18.1.*` checksum-prefix recovery and `wal-18.2.*` page-size/recovery-boundary style cases.
- `wal.test` `wal-19.*` stale reader recovery and `wal-20.*` large WAL-index mapping growth pressure represented as dynamic reader/checkpoint visibility cases.
- `walrestart.test` restart/truncate generation reuse after a committed WAL prefix.
- `walckptnoop.test` no-op checkpoint preservation of committed WAL frames without backfill.

Focused growth:

- Added `SQLiteRealUpstreamPagerWalRecoveryDynamicBatchTest.php`.
- The file defines 361 focused TestRunner PASS cases.
- The 360 dynamic cases each assert 21 native WAL recovery/checkpoint facts, plus one upstream citation case: 7,561 focused assertions when run locally.

Non-overlap:

- This batch does not add metadata-only denominator rows and does not repeat the already accepted warm-body, checkpoint-sync, no-op-only, persist-WAL, byte-truncation, or rollback-journal application files. It focuses on dynamic committed-prefix recovery across checksum, salt, truncated-tail, restart/truncate, and reader-pinned checkpoint outcomes.

Dependency closure:

- No new support component is needed. The batch reuses the existing native `SQLiteWal`, `SQLiteWalHeader`, and frame parser/checkpoint primitives.
