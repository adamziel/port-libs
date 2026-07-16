# real-upstream-corpus-vfs-io-dynamic-20260601T183440Z-0

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/shared6.test`
- Ported sections: `shared6-1.2.1` through `shared6-4.2`
- Behavior: shared-cache lock protocol boundaries for exclusive transactions,
  table write/read locks, read-uncommitted exceptions, VFS implementation cache
  partitioning, exclusive upgrade while holding a read-lock, and safe statement
  finalization after a peer schema change.

## Local Changes

- Added `SQLiteVfsIoDynamicPlan::sharedCacheVfsLockProtocolProfile()` as a
  generic VFS/shared-cache profile for upstream `shared6.test` behavior.
- Added `SQLiteRealUpstreamCorpusVfsShared6LockProtocolDynamicTest.php` with
  1,000 generated upstream-shaped behavior cases plus source-citation,
  malformed-input, non-overlap, and dependency-closure assertions.
- Updated `lane-status.json` for the focused `+1003` TestRunner PASS-case
  delta.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`:
  `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsShared6LockProtocolDynamicTest.php`:
  `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsShared6LockProtocolDynamicTest.php`:
  `1 test files, 16715 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`:
  `1 test files, 8 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`:
  `lane-status json ok`
- `git diff --check -- lanes/libsqlite`: clean.

## Status Delta

- Focused PASS-line growth: `+1003` real TestRunner cases.
- `lane-status.json` `phpPass`: `6185142 -> 6186145`.
- Mapped denominator unchanged at `1589 / 1589`; this is behavior/assertion
  growth from an already hydrated upstream file.

## Non-Overlap

This slice covers `shared6.test` shared-cache exclusive/table/read-uncommitted
locks, VFS implementation cache partitioning, exclusive upgrade, and
finalize-after-schema-change behavior. It avoids accepted `sharedlock.test`,
`shared2.test`, WAL shared-cache checkpoint, `lock6` proxy locking, `lock7`
schema-read, `superlock`, VFS writer/sync/rollback, WAL checkpoint/savepoint,
and `ioerr*` clusters.

## Dependency Closure

No new support component is needed. The slice reuses the bounded
`SQLiteVfsIoDynamicPlan` VFS/shared-cache model and the hydrated upstream
`shared6.test` source truth.
