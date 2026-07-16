# real-upstream-corpus-pager-wal-dynamic-20260531T044513Z-0

Implemented a real upstream pager/WAL corpus batch in
`SQLiteRealUpstreamCorpusPagerWalDynamic20260531T044513ZTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-1.*` corrupted wal-index header recovery
  - `wal2-2.*` out-of-date wal-index header snapshot behavior
  - `wal2-10.*` WAL format mismatch rejection
  - `wal2-13.*` savepoint rollback WAL tail handling
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
  - `walrestart-1.*` restart checkpoint race after `mxFrame` read
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  - `pager1-3.*` savepoint pager rollback boundaries
  - `pager1-4.*` hot-journal recovery visibility
  - `pager1-7.*` truncate journal mode commit visibility

Focused behavior:

- 1,000 generated but real-upstream-cited restart/checkpoint recovery cases
  across clean, valid-tail, checksum-corrupt, salt-corrupt, and truncated WAL
  tails.
- Each case builds concrete WAL bytes and database page images, then exercises
  `SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes()` and
  `SQLiteWal::transactionRecoveryBoundary()` behavior.
- The cases verify current and next reader frame boundaries, durable WAL action,
  checkpoint database usage, replay/reset classification, reader page-image
  equivalence, discarded valid/corrupt tail counts, committed frame boundaries,
  dependency tags, and upstream script/section provenance.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T044513ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T044513ZTest.php`
  - `1 test files, 26024 assertions, 0 failures`
  - `1003` focused PASS lines

Non-overlap:

- Avoids accepted invalid-page-size, exclusive/no-SHM, WAL byte truncation,
  checkpoint transaction, rollback-journal apply/commit, VFS writer/sync, and
  earlier `035039` dynamic pager/WAL journal-mode rows.

Dependency closure:

- No new support component needed.
- Reuses existing generic `SQLiteWalCheckpointCrashRecoveryPlan`,
  `SQLiteWal` transaction recovery, and the hydrated upstream SQLite pager/WAL
  scripts as source truth.
