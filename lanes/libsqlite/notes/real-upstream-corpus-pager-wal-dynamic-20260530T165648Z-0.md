# Real upstream corpus pager WAL dynamic

Scope: `real-upstream-corpus-pager-wal-dynamic-20260530T165648Z-0`.

This slice ports a focused upstream pager/WAL journal-mode, persistent-WAL, and read-only checkpoint cluster into PHP assertions over native libsqlite primitives:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walmode.test`
  - `walmode-4.1`, `walmode-4.7`, `walmode-4.11`, `walmode-5.1`, `walmode-5.3`, `walmode-6.*`, `walmode-7.*`, and `walmode-8.*`.
  - Behavior fix: unqualified `PRAGMA journal_mode = ...` now updates attached schemas while schema-qualified journal-mode changes remain targeted.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test`
  - `walpersist-1.5`, `walpersist-1.6`, `walpersist-1.8`, `walpersist-1.10`, `walpersist-2.2`, and `walpersist-3.3` persistent-WAL file-control state.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test`
  - Read-only WAL connections cannot checkpoint.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro2.test`
  - Read-only SHM clients observe WAL checkpoint/recovery boundaries.

The new `SQLiteRealUpstreamPagerWalModePersistDynamicTest.php` adds 125 focused TestRunner PASS cases and 797 assertions. It exercises 64 dynamic journal-mode scenarios, 36 persistent-WAL file-control scenarios, 24 read-only checkpoint planning scenarios, and one upstream-source citation case. Mapped coverage is unchanged because this handoff does not claim new denominator rows.

Non-overlap: this does not repeat accepted WAL savepoint rollback, WAL sync matrix, checkpoint recovery, WAL byte truncation, rollback-journal commit/apply, VFS savepoint rollback application, WAL checkpoint transaction wrappers, VFS writer/sync/lock clusters, B-tree, JSON, SQL expression ORDER BY, or source-neutral cleanup. The behavior here is real upstream WAL journal-mode propagation, persistent WAL state, and read-only checkpoint blocking.

Dependency closure: no new support component is needed. The slice reuses existing native PHP `SQLitePragmaJournalState`, `SQLiteVfsFileControlPersistencePlan`, `SQLiteWal`, and `SQLitePagerCheckpointTransactionPlan` primitives.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaJournalState.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
- `git diff --check -- lanes/libsqlite`
