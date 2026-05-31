# real-upstream-corpus-btree-index-dynamic-20260531T015800Z-0

Status: ready for integration.

This slice adds one non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex8.test`.
- Upstream sections covered: `bestindex8-1.1` through `bestindex8-5.1.5b`.
- Focus: virtual-table `xBestIndex` distinct/order-by handoff, LIMIT/OFFSET constraint forwarding, IN-vector constraint handling, `rhs_value` reporting, and generated `xFilter` SQL for vectorized and scalarized IN probes.
- Focused assertion growth: `1003` TestRunner PASS cases and `21492` assertions in `SQLiteRealUpstreamBestIndex8VirtualTableDynamicTest.php`.

Non-overlap:

- Existing accepted B-tree/index corpus already covers `bestindex2`, `bestindex3`, `bestindex4`, `bestindex5`, index create/drop lookup, index2/index3/index6/index7/index8/index9/indexA, autoindex families, and storage B-tree page/freeblock/freelist clusters.
- This avoids accepted B-tree page relocation, overflow freelist release, bulk overflow freeblocks, root collapse, index-interior merge, SQL expression `ORDER BY`, JSON table constraints/cursors, WAL checkpoint/savepoint, VFS writer/sync/lock, and earlier virtual-table best-index files.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex8VirtualTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex8VirtualTableDynamicTest.php` -> `1 test files, 21492 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses lane-local B-tree/index dynamic corpus planner, virtual-table `xBestIndex` distinct, LIMIT/OFFSET, IN-vector, `rhs_value`, and `xFilter` SQL metadata helpers.
