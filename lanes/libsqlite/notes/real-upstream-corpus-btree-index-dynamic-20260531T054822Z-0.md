# real-upstream-corpus-btree-index-dynamic-20260531T054822Z-0

Status: ready for integration.

This slice adds a non-overlapping B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindexC.test`.
- Upstream sections covered: `bestindexC-5.2` through `bestindexC-6.6`.
- Focus: virtual-table `xBestIndex` equality constraint requirements, row-value equality decomposition into `a,b,c`, collation reporting for virtual-table constraints, no-query-solution behavior when collated OR conjuncts cannot be optimized, and `rhs_value()` extraction for LIMIT constraints.
- Focused PHP TestRunner cases: 1003 PASS cases from 1000 dynamic upstream-derived cases plus summary, invalid-size, and dependency-closure guards.
- Non-overlap: this extends `bestindexC.test` beyond the accepted `bestindexC-1.2` through `3.6` LIMIT/OFFSET batch. It does not repeat accepted `bestindex8`, `bestindex9`, `bestindexD/E/F`, indexed-by, where9, btree02 skip-next, autoindex, B-tree page relocation, root collapse, overflow freelist/freeblock, or JSON/WAL/VFS/SELECT clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexCConstraintRhsDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexCConstraintRhsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexCConstraintRhsDynamicTest.php`
  - `1 test files, 19282 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexCLimitOffsetDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexCConstraintRhsDynamicTest.php`
  - `2 test files, 40787 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not applicable in this worktree: focused path does not exist
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure: no new support component is needed; this reuses the lane-local B-tree/index dynamic corpus planner and virtual-table constraint, collation, OR-solution, and `rhs_value` metadata helpers.
