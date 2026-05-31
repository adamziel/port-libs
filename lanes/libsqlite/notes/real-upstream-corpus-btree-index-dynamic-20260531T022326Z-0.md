# real-upstream-corpus-btree-index-dynamic-20260531T022326Z-0

Status: ready for integration after focused verification.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindexF.test`.
- Upstream sections covered: `bestindexF-1.1.1` through `bestindexF-2.11`.
- Focus: virtual-table `xBestIndex` DISTINCT and ORDER BY contracts, no-sorter VFilter `idxStr` handoff, ordered DISTINCT row production, GROUP BY ordered virtual-table scans, and cases where DISTINCT still requires `IdxInsert`.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::bestindexFDistinctOrderByCases(1000)` plus `SQLiteRealUpstreamBestIndexFDistinctOrderByDynamicTest.php`.

Non-overlap:

- Existing accepted bestindex coverage handles `bestindex2` through `bestindex9`, plus B-tree page relocation, overflow freelist release, bulk overflow freeblocks, root collapse, index-interior merge, indexA planner/affinity, index7 partial unique, index9 bound partial indexes, autoindex, index expression, indexfault, and numindex batches.
- This slice targets `bestindexF.test` only and does not add metadata-only rows or fabricated upstream script ids.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexFDistinctOrderByDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexFDistinctOrderByDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Focused assertion/PASS movement:

- Adds 1,002 focused TestRunner PASS cases from real upstream `bestindexF.test` scenarios.

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and virtual-table planner-detail modeling.
