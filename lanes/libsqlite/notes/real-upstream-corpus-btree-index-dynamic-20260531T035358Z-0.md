# real-upstream-corpus-btree-index-dynamic-20260531T035358Z-0

Base accepted HEAD: `9995fe4897b08d71e2d75db489dfa08c480a5292`.

Implemented a real upstream B-tree/index dynamic corpus slice from hydrated
SQLite upstream `test/index.test` sections `index-14.1` through `index-14.12`.
The slice adds `SQLiteBTreeIndexDynamicCorpusPlan::indexSortOrderComparisonCases()`
and focused PHP coverage for mixed `NULL`, numeric, and text index-key sort
order, equality predicates, range predicates, and integrity-check preservation
over index `t6i1(a,b)`.

Focused coverage:

- 1,200 dynamic upstream behavior cases.
- 1,203 focused TestRunner PASS lines.
- 16,709 focused assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexSortOrderDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexSortOrderDynamicTest.php`
  - `1 test files, 16709 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
  - `1 test files, 384926 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - clean

Non-overlap:

- Avoids accepted `index19` conflict-policy behavior.
- Avoids accepted `whereE` alter-planner behavior.
- Avoids accepted `indexA` partial-affinity planner behavior.
- Avoids accepted B-tree page move, overflow freelist, root collapse, and
  freeblock materialization clusters.

Dependency closure:

No new support component is needed. The slice reuses lane-local B-tree/index
dynamic corpus helpers and adds only bounded mixed-type sort-precedence helpers.
