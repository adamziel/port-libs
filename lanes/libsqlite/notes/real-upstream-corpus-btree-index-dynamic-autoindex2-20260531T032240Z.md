# real-upstream-corpus-btree-index-dynamic autoindex2 handoff

- Slice: `real-upstream-corpus-btree-index-dynamic-20260531T032240Z-0`
- Base accepted HEAD: `582d5b219b619868bb38159464dc8e8768230ba8`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex2.test`
- Upstream scenarios: `autoindex2-100`, `autoindex2-110`, and `autoindex2-120`
- Added focused coverage: `SQLiteRealUpstreamBtreeAutoindex2OveruseDynamicTest.php` with 1000 distinct dynamic TestRunner cases plus 3 guard/dependency tests.
- Behavior covered: wide real-world schema, 23 declared index/stat rows, `ORDER BY t1.ptime DESC LIMIT 500`, and planner suppression of a transient automatic covering index when declared indexes dominate the join cost.
- Non-overlap: does not repeat accepted `autoindex1` join counters, `autoindex3` declared-index shadow cases, `autoindex4` partial joins, `autoindex5` coroutine subqueries, expression-index range costs, B-tree page relocation, or existing one-row `autoindex2` smoke.
- Dependency closure: no new support component needed; this reuses lane-local planner-stat and automatic-index admission models for declared-index cost comparison.

Verification:

- `php -l lanes/libsqlite/src/SQLiteAutoIndexDynamicPlan.php` - pass
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindex2OveruseDynamicTest.php` - pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindex2OveruseDynamicTest.php` - `1 test files, 27008 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamAutoIndexDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindex2OveruseDynamicTest.php` - `2 test files, 90542 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` - pass

Root harness: not run - isolated micro-slice.
