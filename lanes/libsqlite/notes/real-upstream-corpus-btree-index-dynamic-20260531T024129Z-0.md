# real-upstream-corpus-btree-index-dynamic-20260531T024129Z-0

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindexC.test`.
- Upstream sections covered: `bestindexC-1.2.*`, `bestindexC-2.1`, and `bestindexC-3.4` through `bestindexC-3.6`.
- Focus: virtual-table `xBestIndex` LIMIT/OFFSET constraint forwarding through `UNION ALL`, `UNION`, `INTERSECT`, and `EXCEPT` rowsets; EXCEPT limit pushdown; range-filtered series LIMIT/OFFSET; and fallback paths when a module declines LIMIT or OFFSET.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::bestindexCLimitOffsetConstraintCases(1000)` plus `SQLiteRealUpstreamBestIndexCLimitOffsetDynamicTest.php`.
- Focused assertion count: `21505` assertions and `1002` TestRunner PASS lines.

Non-overlap:

- This targets upstream `bestindexC.test` LIMIT/OFFSET virtual-table behavior.
- It does not repeat accepted `bestindex6`, `bestindex7`, `bestindex8`, `bestindex9`, `bestindexA`, `bestindexF`, `index5` write-locality, `index6` partial theorem, `index7` partial UNIQUE/planner, B-tree page relocation/root-collapse/overflow freelist, JSON, WAL, VFS, PRAGMA, or source-neutral cleanup clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexCLimitOffsetDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexCLimitOffsetDynamicTest.php`
  - `1 test files, 21505 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not present in this worktree

Dependency closure: no new support component needed; this reuses the lane-local B-tree/index dynamic corpus planner and virtual-table constraint/result-row modeling.
