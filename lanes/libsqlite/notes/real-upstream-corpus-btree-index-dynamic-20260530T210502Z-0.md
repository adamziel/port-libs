# real-upstream-corpus-btree-index-dynamic-20260530T210502Z-0

Status: ready for focused integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex1.test`
- Ported sections: `autoindex1-100` through `autoindex1-1211`, including automatic-index ON/OFF joins, scalar subqueries, mutation during scan, ten-way joins, correlated `IN`, materialized view joins, aggregate view joins, LEFT JOIN null-preservation guards, unary-plus join terms, and WITHOUT ROWID automatic-index probes.

Behavior added:

- Extends `SQLiteBTreeIndexDynamicCorpusPlan` with `autoindex1PlannerCases()`.
- Adds 1,000 focused TestRunner cases to `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Updates the B-tree/index dynamic corpus source/count guard to include `autoindex1.test`.

Non-overlap:

- This does not repeat accepted B-tree page relocation, root collapse, overflow freelist release, index-interior merge, index5 write order, autoindex5 coroutine subquery, indexA join planner, or indexfault retry corpus coverage.
- It covers automatic-index planner behavior from a distinct upstream file, `autoindex1.test`.

Dependency closure:

- No new support component needed; this reuses existing lane-local B-tree/index planner corpus helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php`
  - Result: `2 test files, 217463 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output.
