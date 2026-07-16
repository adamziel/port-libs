# real-upstream-corpus-btree-index-dynamic-20260531T162428Z-0

## Source truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/where3.test`
- Ported sections: `where3-1.1`, `where3-1.2`, `where3-2.1` through `where3-2.7`, `where3-3.0a` through `where3-3.3`, `where3-5.0a` through `where3-5.3`, `where3-6.*`, `where3-7.*`, and `where3-8.1`/`where3-8.2`.
- Behavior cluster: B-tree/index planner behavior for LEFT JOIN ordering boundaries, join reordering before the nullable side, NATURAL/USING shared-column joins, omit-noop LEFT JOIN stability, primary-key lookup placement after ANALYZE, temporary B-tree ordering, and composite-index equality guards.

## Lane changes

- Added `SQLiteBTreeIndexDynamicCorpusPlan::where3LeftJoinReorderPlannerCases()` with 1,200 real upstream-derived cases over 99 selected `where3.test` templates.
- Added `SQLiteRealUpstreamBtreeWhere3LeftJoinDynamicTest.php` with 1,203 focused TestRunner PASS cases and source/dependency guards.
- Updated `lane-status.json` from `3350074` to `3351277` selected PASS lines (`+1203`). Mapped coverage remains `1589 / 1589` because the denominator is already fully mapped.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere3LeftJoinDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere3LeftJoinDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere3LeftJoinDynamicTest.php`
  - `1 test files, 25907 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere3LeftJoinDynamicTest.php`
  - `2 test files, 91075 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Dependency closure and non-overlap

- No new support component is needed; the slice reuses the existing B-tree/index dynamic corpus planner.
- Non-overlap: this slice avoids the accepted `where2`, `where4`, `where6`, `where7`, `where8`, `where9`, `whereA`, `whereC`, `whereD`, `whereE`, `whereF`, `whereG`, `whereH`, `whereI`, `whereJ`, `whereK`, `whereL`, `whereM`, `whereN`, `index*`, and `bestindex*` batches, plus accepted B-tree page relocation/root-collapse/overflow/freelist release, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup surfaces.
- Root harness not run: isolated micro-slice.
