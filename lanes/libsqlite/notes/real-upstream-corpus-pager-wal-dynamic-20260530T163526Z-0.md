# Real upstream corpus pager WAL dynamic

Scope: `real-upstream-corpus-pager-wal-dynamic-20260530T163526Z-0`.

This slice ports a focused upstream pager/WAL savepoint rollback cluster into PHP assertions over the native WAL parser and transaction-recovery boundary:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
  - `wal-4.1` through `wal-4.6.1`: WAL savepoint rollback preserves committed rows/pages and excludes rolled-back savepoint writes.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-8.1.3` and `wal2-8.1.4`: a rolled-back WAL transaction does not leak into the next connection's visible data.

The new `SQLiteRealUpstreamPagerWalSavepointRollbackCorpusTest.php` builds 78 distinct WAL frame layouts with varying committed-frame counts, rolled-back valid tail-frame counts, and committed database page counts. Each case verifies transaction recovery status/reason, committed prefix length, discarded valid draft tail, checkpoint database sizing, committed image visibility, draft image exclusion, and checkpoint plan metadata. This adds 939 focused assertions without claiming new mapped denominator rows.

Non-overlap: this does not repeat accepted WAL byte truncation, rollback-journal commit/apply, VFS savepoint rollback application, WAL checkpoint transaction wrappers, WAL checksum corrupt-tail recovery, hot-journal recovery, VFS writer/sync/lock clusters, B-tree, JSON, SQL executor, or source-neutral cleanup. The behavior here is real upstream WAL savepoint/transaction rollback parity over valid uncommitted WAL tails.

Dependency closure: no new support component is needed. The slice reuses existing native PHP `SQLiteWal` frame parsing, checksum validation, transaction recovery, checkpoint image, and checkpoint plan primitives.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSavepointRollbackCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSavepointRollbackCorpusTest.php`
- `git diff --check -- lanes/libsqlite`
