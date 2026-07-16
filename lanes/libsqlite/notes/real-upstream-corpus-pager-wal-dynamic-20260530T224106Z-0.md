# real-upstream-corpus-pager-wal-dynamic-20260530T224106Z-0

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.

Ported a non-overlapping pager/WAL sync corpus batch from the hydrated upstream SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-14.1`, `wal2-14.2`, `wal2-14.3`: checkpoint_fullfsync sync-count matrix.
  - `wal2-15.1` through `wal2-15.12`: checkpoint_fullfsync, fullfsync, and synchronous mode xSync matrix.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`
  - checkpoint noop source citation for read-only WAL frame accounting.

Behavior exercised:

- WAL checkpoint transaction planning through `SQLitePagerCheckpointTransactionPlan`.
- WAL frame parsing through `SQLiteWal`.
- VFS sync flag planning through `SQLiteVfsSyncPlan`.
- Upstream distinction between ordinary sync requests and fullfsync callback counts.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointSyncDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointSyncDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointSyncDynamicTest.php`
  - `1 test files, 8010 assertions, 0 failures`
  - 1004 distinct TestRunner PASS cases.

Non-overlap:

- Avoids the accepted pager/WAL mode-persist batch in `SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`.
- Avoids accepted WAL byte truncation, checkpoint transaction, rollback-journal apply, VFS writer, VFS sync apply, and rollback commit application production clusters.
- Adds no WordPress-specific libsqlite API or scenario names.

Dependency closure:

- No new support component is needed. This reuses existing bounded native PHP WAL parsing, checkpoint transaction, lock coordination, and VFS sync planning components.
