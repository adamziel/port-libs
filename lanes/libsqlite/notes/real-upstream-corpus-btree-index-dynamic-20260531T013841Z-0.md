# Real upstream corpus: B-tree/index dynamic bestindex6 and bestindex7

- Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex6.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex7.test`
- Ported behavior cluster:
  - `bestindex6-1.1` through `bestindex6-1.4`: LEFT JOIN virtual-table `xBestIndex` usable equality plus `IS NULL`/equality constraint filtering.
  - `bestindex7-1.1` through `bestindex7-1.12`: virtual-table equality, OR, and IN-list constraint dispatch before and after a source row is updated to NULL.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::bestindex6And7VirtualTableNullConstraintCases(1000)` plus `SQLiteRealUpstreamBestIndex6And7VirtualTableDynamicTest.php`.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex6And7VirtualTableDynamicTest.php` passed with `1 test files, 20524 assertions, 0 failures` and 1003 PASS lines.
- Non-overlap: this targets upstream `bestindex6.test` and `bestindex7.test`. It does not repeat accepted/queued `bestindex2`, `bestindex3`, `bestindex4`, `bestindex5`, `indexA`, `index3`, `index5`, B-tree page relocation/root-collapse/overflow freelist, JSON, WAL, VFS, PRAGMA, or source-neutral cleanup clusters.
- Dependency closure: no new support component needed; this reuses the lane-local B-tree/index dynamic corpus planner and virtual-table usable constraint modeling.
