# Real upstream corpus pager WAL dynamic 20260531T060720Z

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260531T060720Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcrash2.test`
  - `walcrash2-1.1`: committed 8-frame WAL prefix.
  - `walcrash2-1.2`: repeated crashed writers leave uncommitted wal-index hash entries.
  - `walcrash2-1.3`: a later reader recovers rows from the committed prefix.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcrash3.test`
  - `walcrash3-1.*`: crash after `journal_size_limit` WAL truncation keeps copied database consistent.
  - `walcrash3-2.*`: crash during checkpoint/full-sync sequence keeps `PRAGMA integrity_check` ok.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcrash4.test`
  - `walcrash4-1.*`: synchronous FULL sector-boundary commit requires durable WAL sync.

Implemented coverage:

- Added `SQLiteRealUpstreamCorpusPagerWalCrashRecoveryDynamic20260531T060720ZTest.php`.
- The file adds 1000 dynamic crash-recovery cases plus hydrated-source and handoff-provenance cases.
- Each dynamic case builds real WAL bytes and exercises native `SQLiteWal::transactionRecoveryBoundary()`, committed transaction extraction, checkpoint/durable checkpoint routing, reader snapshot visibility, checkpoint reader visibility, and persistent-WAL close planning.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrashRecoveryDynamic20260531T060720ZTest.php`
  - Result: `1 test files, 44016 assertions, 0 failures`
  - PASS-line growth: `+1002`

Non-overlap:

- This slice extends the `walcrash2.test`, `walcrash3.test`, and `walcrash4.test` crash/fault recovery boundaries.
- It avoids accepted WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, super-journal commits, VFS writer/sync/lock, `wal5` blocking checkpoint, `wal8` page-size, `wal64k`, `walvfs`, `walnoshm`, `walmode`, readonly-SHM, and pager master-journal batches.

Dependency closure:

- No new support component is needed.
- The tests reuse existing native WAL parser/recovery/checkpoint/reader-visibility helpers and the hydrated upstream SQLite checkout as source truth.
