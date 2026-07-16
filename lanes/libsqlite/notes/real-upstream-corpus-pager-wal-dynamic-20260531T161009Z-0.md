# real-upstream-corpus-pager-wal-dynamic-20260531T161009Z-0

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/crash5.test`
  - `crash5-$ii.$jj.1`: malloc failure during `CREATE UNIQUE INDEX` moves overflow page 4 and must not leave the moved page unsafely considered synced.
  - `crash5-$ii.$jj.2` and `crash5-$ii.$jj.3`: recovery preserves `integrity_check` and the original overflow row after the crash.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/crash6.test`
  - `crash6-1.*`: rollback recovery for page-size 4096 schema commits.
  - `crash6-2.*`: rollback recovery for page-size 2048 insert crash.
  - `crash6-3.*`: database-sync crash preserves the table signature across page sizes 1024, 2048, 4096, and 8192.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/crash7.test`
  - `crash7-1.*`: crash during `VACUUM` page-size changes against both `test.db` and `test.db-journal` preserves integrity.
  - `crash7-2.*`: crash during `VACUUM` after deleting odd rowids preserves unique-index integrity.

## Patch

Added `SQLitePagerCrashRecoveryDynamicPlan` as a bounded native PHP pager crash recovery profile and covered it with `SQLiteRealUpstreamCorpusPagerWalCrashDynamic20260531T161009ZTest.php`.

Focused TestRunner growth:

- 990 `crash5.test` movepage malloc recovery cases from the upstream `seed 0..9` x `malloc failure 1..99` loop.
- 50 `crash6.test` page-size rollback/signature cases.
- 146 `crash7.test` VACUUM crash integrity cases.
- 4 source citation, malformed-input, count, and non-overlap/dependency PASS cases.
- Total: 1,190 focused PASS cases, 29,406 focused assertions.

## Non-Overlap

This slice uses `crash5.test`, `crash6.test`, and `crash7.test`, which were not represented in current libsqlite source/tests/notes before this handoff. It avoids accepted `crash8.test` hot-journal recovery, superlock, WAL checkpoint, rollback-commit/apply, VFS sync/write/lock, master-journal, WAL-hook, WAL-no-SHM, WAL-bak, WAL-fault, WAL-protocol, and pager4 DBMOVED clusters.

`crashM.test` remains excluded because it is a multiplex/8.3-names capability test rather than this pager/WAL crash-recovery slice.

## Dependency Closure

No new external support component is required. The patch adds a lane-local native PHP profile under `lanes/libsqlite/src` and reuses the existing TestRunner/autoload infrastructure plus the hydrated upstream SQLite corpus as source truth.

## Verification

- `php -l lanes/libsqlite/src/SQLitePagerCrashRecoveryDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerCrashRecoveryDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrashDynamic20260531T161009ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrashDynamic20260531T161009ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCrashDynamic20260531T161009ZTest.php`
  - `1 test files, 29406 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
