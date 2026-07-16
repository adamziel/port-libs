# Real Upstream Corpus Pager/WAL Dynamic Slice

Session: port-dev-sqlite-yield-dyn-real-pager-20260530T235424Z
Base accepted HEAD: c18695783d58d6f8245967de682828c93b145ece

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test`
- Ported `walpersist.test` 3.1 through 3.4.

Behavior added:
- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walPersistLimitRows()`.
- Models upstream `PRAGMA journal_mode=WAL`, `PRAGMA wal_autocheckpoint=128`, `PRAGMA journal_size_limit=16384`, composite primary-key table creation, 200 inserted `(randomblob(500), randomblob(500))` rows, persist-WAL file-control enablement, close-time WAL truncation to zero bytes, and reopen `PRAGMA integrity_check` returning `ok`.
- Added 200 focused TestRunner PASS cases. Each case checks deterministic 500-byte payload shape, distinct key digests, composite primary-key metadata, WAL frame/checkpoint-batch placement, persist-WAL state, truncation, integrity result, and real upstream dependency tags.

Non-overlap:
- This extends the existing pager/WAL dynamic corpus but owns only the `walpersist.test` 3.1-3.4 persistent-WAL journal-size-limit workload.
- It does not repeat accepted `walckptnoop.test` NOOP/PASSIVE checkpoint behavior, `waloverwrite.test` overwrite recovery, WAL setlk snapshot, WAL persist mode toggles, WAL checkpoint transaction, savepoint byte truncation, rollback-journal apply/commit, VFS writer/sync/lock-state, or pager master-journal numbered surfaces.

Evidence:
- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - Result: `1 test files, 29612 assertions, 0 failures`.
  - New focused movement: `+200` TestRunner PASS cases and `+5200` behavior assertions in the existing pager/WAL dynamic corpus.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:
- No new support component is needed. This reuses the existing PHP corpus-plan/test runner structure and lane-local WAL persist/file-control, journal-limit, and integrity-result modeling.
