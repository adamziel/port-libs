# real-upstream-corpus-pager-wal-dynamic-20260531T035600Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- Upstream section: `wal2-14.1`, `wal2-14.2`, and `wal2-14.3`

Implemented coverage:

- Added `SQLiteRealUpstreamPagerWalFullSyncDynamicTest.php`.
- Exercises existing `SQLiteRealUpstreamPagerWalDynamicPlan::wal2CheckpointFullSyncCases()` for 1000 dynamic real-upstream rows.
- Covers checkpoint `fullfsync` disabled/default/enabled sync-count behavior, checkpoint mode variation, synchronous mode variation, page-size variation, and WAL autocheckpoint thresholds.
- Focused assertions: 19009.
- Focused PASS cases: 1001.

Non-overlap:

- Avoids accepted pager/WAL hook, protocol, checksum, crash recovery, WAL restart, readonly-SHM, WAL mode/persist, WAL checkpoint transaction, rollback journal apply/commit, VFS writer/sync/lock, savepoint byte truncation, and WAL setlk/blocking-lock clusters.
- This slice targets the unexercised real upstream `wal2.test` `wal2-14.*` checkpoint `fullfsync` sync accounting matrix already represented in production corpus data but not directly covered by a focused PHP test file.

Dependency closure:

- No new support component is needed.
- Reuses existing bounded libsqlite pager/WAL corpus and VFS sync-plan concepts.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalFullSyncDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalFullSyncDynamicTest.php` passed: `1 test files, 19009 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Root harness:

- Not run; isolated micro-slice.
