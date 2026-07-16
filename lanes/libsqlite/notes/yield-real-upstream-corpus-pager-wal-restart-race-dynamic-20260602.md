# Real Upstream Corpus Pager/WAL Restart Race Dynamic 20260602

Slice: `real-upstream-corpus-pager-wal-dynamic-20260602T000914Z-0`

Accepted base: `df6aab6c7b87e548fe655763cf42a9438f111f94`

## Source truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
- Upstream sections: `walrestart.test` `1.0` through `1.5`
- Focused scenario: `walrestart.test` `1.2`, where checkpoint reads `mxFrame=45`, a second connection writes before `nBackfill` is read, and the checkpoint reports `{0 45 0}` before later checkpoint/integrity recovery.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalRestartRaceDynamic20260602Test.php`.
- The test admits 1,000 dynamic `walrestart.test 1.2` race cases plus source, inventory, and malformed-input guards.
- The cases build real WAL byte streams with `SQLiteWalHeader` and `SQLiteWal::checksumPair()`, parse them with `SQLiteWal::parse()`, and exercise `checkpointBoundaryResult()` / `checkpointModeResult()` over initial, pre-race, race-limited, post-writer, and final-large WAL phases.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalRestartRaceDynamic20260602Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalRestartRaceDynamic20260602Test.php`
  - `1 test files, 52022 assertions, 0 failures`
  - Adds `+1003` focused TestRunner PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 9 assertions, 0 failures`

## Non-overlap

This targets `walrestart.test` restart-race checkpoint result `{0 45 0}`. It avoids accepted WAL byte truncation, `wal.test` reused-log prefix, `walcksum` savepoint, `wal-11` cache spill, `wal5` blocking checkpoints, rollback-journal apply/commit, VFS writer/sync/lock, readonly-SHM, `walsetlk` timeout, and application-WAL slices.

## Dependency closure

No new support component is needed. The slice reuses native `SQLiteWal` parsing, checkpoint boundary results, checkpoint image application, and the hydrated upstream `walrestart.test` source file.
