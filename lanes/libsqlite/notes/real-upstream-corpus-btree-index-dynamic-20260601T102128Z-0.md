# real-upstream-corpus-btree-index-dynamic-20260601T102128Z-0

Base accepted HEAD: `7bd413e4c22aac9f2c5a76765dae0d142cb048cb`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/where.test`
- Upstream sections `where-1.1.1` through `where-1.41`

Implemented coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::where1BasicIndexSeekCases(1000)`.
- Added `SQLiteRealUpstreamBtreeWhere1BasicIndexDynamicTest.php`.
- Covers equality and `IS` probes on `i1w`, SELECT-alias constraint routing, unary-plus de-optimization, composite `(x,y)` probes on `i1xy`, range bounds, redundant equality bounds, parenthesized constraints, and expression predicates that must scan or fall back to a residual pass.
- Adds 1003 focused TestRunner PASS cases and 18069 behavior assertions from the real upstream section-1 `where.test` corpus.

Non-overlap:

- Owns upstream `where.test` section-1 basic B-tree/index seek behavior.
- Avoids existing accepted `where2` through `whereN`, B-tree page relocation/root-collapse/overflow release, VFS writer/sync/lock, and rollback-commit clusters.

Dependency closure:

- No new support component is needed.
- Reuses the lane-local B-tree/index dynamic corpus plan infrastructure for row synthesis, predicate routing, range search counts, planner detail evidence, and focused PHP TestRunner coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere1BasicIndexDynamicTest.php`
  - `1 test files, 18069 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere1BasicIndexDynamicTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere1BasicIndexDynamicTest.php`
  - `2 test files, 83237 assertions, 0 failures`

Root harness: not run - isolated micro-slice.
