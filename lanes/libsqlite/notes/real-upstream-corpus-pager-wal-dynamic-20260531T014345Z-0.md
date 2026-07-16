# real-upstream-corpus-pager-wal-dynamic-20260531T014345Z-0

Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`

## Upstream Source

- Hydrated file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
- Ported scenarios: `walrestart.test` 1.0 through 1.5, focused on the checkpoint race where one connection reads `mxFrame`, another writer shrinks the live WAL, and the checkpoint must avoid stale `nBackfill`.

## Added Coverage

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walRestartCheckpointRaceRows()`.
- Added `SQLiteRealUpstreamCorpusPagerWalRestartDynamicTest.php`.
- Focused movement: `+3001` TestRunner PASS cases.
- Behavior assertions: `34004` focused assertions.
- Non-overlap: does not touch accepted WAL byte truncation, checkpoint transaction, rollback-journal apply, VFS writer/lock/sync, wal2/wal3/walhook/walpersist dynamic rows, or app-WAL slices.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalRestartDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalRestartDynamicTest.php`
  - `1 test files, 34004 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - `1 test files, 57878 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses existing pager/WAL corpus planning and models the upstream WAL checkpoint restart race as lane-local PHP data and assertions.
