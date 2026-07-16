# real-upstream-corpus-pager-wal-dynamic-20260531T212852Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
- Upstream sections: `wal-11.1` through `wal-11.14`.

Behavior ported:
- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walCacheSpillRows()` for
  1000 dynamic `wal.test` `wal-11.*` rows.
- Added focused TestRunner coverage that builds WAL byte streams for cache-spill
  frames written before commit, committed-frame publication, rollback after
  spill, and checkpoint database-size stability.
- Each row exercises `SQLiteWal::parse()`,
  `SQLiteWal::transactionRecoveryBoundary()`, committed-prefix truncation,
  uncommitted-tail discard, committed transaction grouping, and checkpoint
  planning.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
  -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCacheSpillDynamic20260531T212852ZTest.php`
  -> no syntax errors.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCacheSpillDynamic20260531T212852ZTest.php`
  -> `1 test files, 40014 assertions, 0 failures`.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  -> `1 test files, 3 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  -> `lane-status json ok`.
- `git diff --check -- lanes/libsqlite`
  -> passed with no output.

Status delta:
- Focused PASS cases: `+1003`.
- Focused assertions: `+40014`.
- Lane-local `phpPass`: `3847998 -> 3888012`.
- Mapped denominator: unchanged at `1589 / 1589`; this is behavior assertion
  growth against an already mapped upstream script.

Non-overlap:
- This targets `wal.test` `wal-11.*` cache-spill commit and rollback recovery
  boundaries.
- It avoids accepted WAL savepoint byte truncation, checkpoint transactions,
  rollback journal apply/commit, super-journal commits, WAL checksum/crash
  recovery, `walshared` locks, `walsetlk` timeouts, `walro` readonly-SHM,
  `wal64k` SHM growth, VFS writer/sync/lock clusters, and previous
  `journal1`/`journal3` rollback-journal batches.

Dependency closure:
- No new support component is needed. The slice reuses existing lane-local
  `SQLiteWal` parser, transaction recovery, checkpoint planning, and hydrated
  upstream `wal.test` source truth.

Next candidate:
- Continue with non-overlapping late `wal.test` pager/WAL sections such as
  `wal-12`, `wal-14`, `wal-21` through `wal-26`, or default-memory pager
  pressure if a focused runner exposes it.
