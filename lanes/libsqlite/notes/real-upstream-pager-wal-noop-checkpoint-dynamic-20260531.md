# Real Upstream Pager WAL Noop Checkpoint Dynamic

Slice: `real-upstream-corpus-pager-wal-dynamic-20260531T021346Z-0`

Base: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`

Covered upstream scenarios:

- `walckptnoop.test` 1.1: first `PRAGMA wal_checkpoint=noop` reports WAL log frames with zero checkpointed frames.
- `walckptnoop.test` 1.2: repeated noop checkpoint stays non-mutating.
- `walckptnoop.test` 1.4: noop after passive checkpoint preserves already-checkpointed state.
- `walckptnoop.test` 1.5: restored connection noop reports sidecar log without backfill.
- `walckptnoop.test` 1.6: reopened checkpointed database with empty WAL reports zero counts.
- `walckptnoop.test` 1.8-1.9: committed tail noop reports log frames without checkpoint writes through pragma and C API paths.
- `walckptnoop.test` 1.10: rollback-journal mode stays outside WAL parsing and is documented as excluded from the WAL transaction helper.

Implementation delta:

- `SQLitePagerCheckpointTransactionPlan` now accepts `noop` and acquires only the shared checkpoint lock.
- `SQLiteWalFileWritePlan::checkpoint()` now returns an empty operation list for `noop`, preserving the database image and WAL sidecar bytes.
- Added 256 dynamic real-upstream WAL noop checkpoint PASS cases with 5,122 assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerCheckpointTransactionPlan.php`
- `php -l lanes/libsqlite/src/SQLiteWalFileWritePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoopCheckpointDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoopCheckpointDynamicTest.php` => 1 file, 5,122 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php` => 1 file, 797 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260530T230134ZTest.php` => 1 file, 21,400 assertions, 0 failures.

Dependency closure: no new support component needed. This reuses existing `SQLiteWal`, `SQLiteLockCoordinator`, and WAL checkpoint write-plan infrastructure.

Non-overlap: this does not repeat accepted WAL checkpoint transaction, WAL byte truncation, WAL file writer, rollback-journal apply, savepoint rollback apply, WAL mode persistence, or readonly checkpoint coverage. The owned behavior is the upstream `noop` checkpoint transaction/write-plan path.
