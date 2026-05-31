# real-upstream-corpus-btree-index-dynamic-20260531T011233Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex4.test`.
- Ported behavior cluster: `bestindex4-1.$param1.$param2.2` through `bestindex4-1.$param1.$param2.4` for all 16 by 16 virtual-table support-bitmask combinations, plus `bestindex4-2.1` and `bestindex4-2.2` table-valued hidden-argument planning.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::bestindex4VirtualTableUsableFlagCases(1000)` plus `SQLiteRealUpstreamBestIndex4VirtualTableDynamicTest.php`.
- Focused TestRunner PASS growth: 1003 distinct focused PASS cases and 37681 assertions.
- Non-overlap: this extends B-tree/index dynamic coverage after accepted `bestindex2.test` and `bestindex3.test` batches. It does not repeat accepted B-tree page relocation, overflow freelist/freeblock release, root collapse, index-interior merge, `index4`, `index6`, `index7`, `index8`, `index9`, `indexA`, `autoindex*`, `indexedby`, `indexfault`, `numindex1`, `indexexpr`, JSON, WAL, VFS, PRAGMA, or source-neutral cleanup clusters.
- Dependency closure: no new support component is needed; the slice reuses the lane-local B-tree/index dynamic corpus planner and virtual-table xBestIndex constraint metadata model.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex4VirtualTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex4VirtualTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex2VirtualTableDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex3VirtualTableDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex4VirtualTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
