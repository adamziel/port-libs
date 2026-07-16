# real-upstream-corpus-btree-index-dynamic-20260531T105358Z-0

Status: ready for integration.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereF.test`
- Upstream sections: `whereF-1.1` through `whereF-5.6`.

Focused coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereFJoinOrderDynamicCases(1000)`.
- Added `SQLiteRealUpstreamBtreeWhereFJoinOrderDynamicTest.php`.
- The generator models upstream costed join-order selection, `CROSS JOIN`
  order preservation, composite `t1(a,b)` index prefix selection, primary-key
  prefix choice over wider alternate indexes, and the OR-clause
  guard-before-seek behavior that keeps the `t2(f2)` range scan from running
  when `t1.f1!=-1` is false.
- Focused PASS-line movement: `+1003` TestRunner PASS cases with `16085`
  behavior assertions in the focused whereF file.

Non-overlap:

- This owns `whereF.test` sections 1 through 5 only.
- It avoids accepted `whereA` reverse scans, `whereB` expression-affinity,
  `whereD` covering OR-index unions, `whereG` expression-affinity/planner-hint
  shards, `whereH/J/K/L/M/N`, `where9`, indexed-by enforcement,
  expression-index range-cost, B-tree page relocation/root collapse/interior
  merge/overflow freelist/freeblock release, JSON, WAL, VFS, PRAGMA,
  trigger/FK, UPSERT, and source-neutral cleanup clusters.
- Mapped denominator coverage remains `1589 / 1589`; this is countable PHP
  PASS-line growth against already mapped real upstream B-tree/index inventory.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereFJoinOrderDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereFJoinOrderDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereFJoinOrderDynamicTest.php`
  - `1 test files, 16085 assertions, 0 failures`
  - `1003` focused TestRunner PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereFJoinOrderDynamicTest.php`
  - `2 test files, 81253 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index
  dynamic corpus planner and existing join-order, composite-index, OR-index,
  vmstep-threshold, and guard-before-seek metadata helpers.

Root harness:

- Not run - isolated micro-slice.
