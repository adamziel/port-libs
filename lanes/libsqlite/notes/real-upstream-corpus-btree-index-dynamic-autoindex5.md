## Real Upstream Corpus: B-tree/Index Dynamic autoindex5

Micro-slice: `real-upstream-corpus-btree-index-dynamic-20260530T215134Z-0`

Accepted base: `4d354e3a7fdb39040e393b5132f7de86a7766ad9`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex5.test`
- Upstream sections: `autoindex5-1.1`, `autoindex5-2.1`, `autoindex5-2.2`, `autoindex5-3.1`, `autoindex5-3.2`, and `autoindex5-3.3`

Coverage added:

- New focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindex5DynamicTest.php`
- Adds 1,001 distinct TestRunner PASS cases and 20,208 assertions.
- Exercises the existing lane-local `SQLiteBTreeIndexDynamicCorpusPlan::autoindex5CoroutineSubqueryCases()` behavior for automatic covering index probes over coroutine views, aggregate subquery duplicate preservation, nested coroutine rowid binding, DISTINCT coroutine subqueries feeding `IN`, and OR-connected indexed probes with scalar DISTINCT subqueries.

Non-overlap:

- This does not repeat the accepted `autoindex1.test`, `autoindex4.test`, `index4.test`, `index6.test`, `index7.test`, `index9.test`, `indexA.test`, `indexfault.test`, `numindex1.test`, or WITHOUT ROWID dynamic B-tree/index test files already present in the lane.
- The new rows cover `autoindex5.test`, which was already represented by a production corpus generator but had no focused PHP TestRunner file.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindex5DynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindex5DynamicTest.php`
  - Result: `1 test files, 20208 assertions, 0 failures`
  - PASS lines: 1,001

Dependency closure:

- No new support component is needed. This reuses the existing lane-local B-tree/index dynamic corpus planner and focuses the unexercised upstream `autoindex5.test` generator.
