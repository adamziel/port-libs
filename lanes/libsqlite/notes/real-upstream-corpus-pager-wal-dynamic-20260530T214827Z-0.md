# real-upstream-corpus-pager-wal-dynamic-20260530T214827Z-0

Base accepted HEAD: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`.

Added focused PHP coverage for real upstream pager/WAL transaction behavior:

- `wal2.test`: `wal2-10.*` multi-transaction checkpoint/reader visibility.
- `wal2.test`: `wal2-11.*` committed-prefix recovery with writer tail frames.
- `walrestart.test`: restart/truncate checkpoint behavior with pinned readers.
- `walpersist.test`: persistent WAL sidecar decisions after close/reopen.
- `wal.test`: `wal-2.*` reader snapshots over committed WAL prefixes.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicTransactionBatchTest.php`
- Result: `1 test files, 25001 assertions, 0 failures`
- PASS-line growth: `1001` focused TestRunner PASS cases.

Non-overlap:

- This batch avoids accepted pager/WAL2 snapshot, WAL persist mode, WAL restart/noop/checkpoint, WAL byte truncation, rollback-journal apply, checkpoint transaction, and VFS writer application clusters by exercising multi-transaction committed-prefix recovery, per-reader checkpoint state, and durable checkpoint sidecar byte accounting through `SQLiteWal` and `SQLiteWalMultiTransactionClusterPlan`.

Dependency closure:

- No new support component is needed. The tests reuse existing native PHP WAL parser, checksum, checkpoint, durable sidecar, and multi-transaction cluster helpers.
