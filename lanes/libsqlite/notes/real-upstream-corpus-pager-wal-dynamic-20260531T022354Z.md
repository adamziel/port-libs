real-upstream-corpus-pager-wal-dynamic-20260531T022354Z

Slice: real-upstream-corpus-pager-wal-dynamic-20260531T022354Z-0
Base accepted HEAD: 5237a0589958b13a7df177706c832014179deb3d

Upstream source truth:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test
  - walpersist-1.* persistent WAL sidecar close behavior
  - walpersist-2.* journal_size_limit truncates persistent WAL on close
  - walpersist-3.* persistent WAL with zero-size sidecar and integrity check
  - walpersist-4.1 WAL/PERSIST/rollback journal-mode transition chain
- /home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test
  - wal2-1.* corrupted WAL-index header recovery
  - wal2-2.* stale WAL-index snapshot then recovery
- /home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test
  - wal3-1.* WAL rollback/recovery and committed content visibility
- /home/claude/port-libs/.upstream-cache/libsqlite/test/pager3.test
  - pager3-1.* rollback journal sidecar visibility in exclusive/normal locking transitions

Focused coverage:
- Added `SQLiteRealUpstreamCorpusPagerWalDynamic20260531T022354ZTest.php`.
- Adds 1,122 distinct TestRunner PASS cases and 14,058 focused assertions.
- Exercises existing generic `SQLiteWal` durable checkpoint/persistent close behavior and `SQLitePagerWalDynamicPlan` journal-mode state transitions with real upstream scenario names.

Non-overlap:
- Avoids accepted noop-checkpoint, checksum persistence, readonly-SHM, WAL byte truncation, checkpoint transaction, rollback-journal apply/commit, VFS sync/file writer/lock, and pager1 boundary batches.
- Covers persistent WAL close, journal_size_limit truncation, reader-limited close preservation, WAL recovery checkpoint counts, and rollback-journal sidecar visibility.

Dependency closure:
- No new support component needed.
- Reuses hydrated upstream SQLite `.test` files, `SQLiteWal`, and `SQLitePagerWalDynamicPlan`.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T022354ZTest.php`
  - result: 1 test files, 14058 assertions, 0 failures

Root harness:
- not run - isolated micro-slice
