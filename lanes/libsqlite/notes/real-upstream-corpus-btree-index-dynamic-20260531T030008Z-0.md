# real-upstream-corpus-btree-index-dynamic-20260531T030008Z-0

This slice adds a non-overlapping real upstream B-tree/index virtual-table planner batch from the hydrated SQLite upstream checkout:

- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindexD.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindexE.test`
- Upstream sections covered:
  - `bestindexD-1.1` through `bestindexD-1.6`: virtual-table `xBestIndex` column-usage masks for projection, WHERE constraints, FULL JOIN, and OR-connected predicates.
  - `bestindexE-1.1` through `bestindexE-3.2.3`: usable equality constraints, LEFT JOIN constraint propagation, compound UNION outer-WHERE pushdown, and eponymous virtual-table schema reload with `RETURNING`.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::bestindexDAndEVirtualTablePlannerCases(1000)` plus `SQLiteRealUpstreamBestIndexDAndEVirtualTableDynamicTest.php`.
- Focused PASS-line growth: 1,002 distinct TestRunner cases from real upstream scenario templates.
- Non-overlap: this targets upstream `bestindexD.test` and `bestindexE.test`. It avoids accepted `bestindex5`, `bestindex6`, `bestindex7`, `bestindex8`, `bestindex9`, `bestindexC`, `bestindexF`, `index5` write locality, `index6` late partial-index theorem, `index7` partial-index/stat behavior, `index9` bound partial indexes, `indexA` affinity/planner behavior, B-tree page relocation/root-collapse/overflow freelist/freeblock, JSON, WAL, VFS, PRAGMA, and source-neutral cleanup clusters.
- Dependency closure: no new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and virtual-table planner constraint modeling.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexDAndEVirtualTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndexDAndEVirtualTableDynamicTest.php`
- API guard: not run because the expected guard test is absent in this worktree.
- `git diff --check -- lanes/libsqlite`
