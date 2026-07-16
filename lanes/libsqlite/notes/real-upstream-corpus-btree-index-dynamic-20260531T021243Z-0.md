# Real upstream corpus: B-tree/index dynamic bestindex9

Slice: `real-upstream-corpus-btree-index-dynamic-20260531T021243Z-0`.

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex9.test`.
- Upstream sections covered: `bestindex9-1.0`, `bestindex9-1.1`, `bestindex9-1.2`, `bestindex9-2`, `bestindex9-3`, and `bestindex9-4`.
- Focus: virtual-table `xBestIndex` `orderby` and `distinct` inputs for rowid composite primary keys, WITHOUT ROWID primary keys, NOT NULL primary-key columns, `DISTINCT ... ORDER BY`, `DISTINCT ... GROUP BY`, and joined DISTINCT sources.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::bestindex9VirtualTableDistinctOrderByCases(1000)` plus `SQLiteRealUpstreamBestIndex9VirtualTableDynamicTest.php`.
- Focused assertion/PASS shape: 1,000 generated real-upstream dynamic behavior cases plus summary, invalid-size, and dependency-closure tests; focused runner output is `1 test files / 20009 assertions / 0 failures / 1003 PASS lines`.
- Non-overlap: this targets upstream `bestindex9.test` only. It avoids accepted `bestindex6`, `bestindex7`, `bestindex8`, `index7`, `index8`, `index9`, autoindex, B-tree page relocation/root-collapse/overflow freelist, JSON, WAL, VFS, PRAGMA, and source-neutral cleanup clusters.
- Dependency closure: no new support component needed; this reuses the lane-local B-tree/index dynamic corpus planner and virtual-table `xBestIndex` order-by/distinct metadata modeling.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex9VirtualTableDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex9VirtualTableDynamicTest.php` -> `1 test files, 20009 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`.
- `git diff --check -- lanes/libsqlite` -> clean.
