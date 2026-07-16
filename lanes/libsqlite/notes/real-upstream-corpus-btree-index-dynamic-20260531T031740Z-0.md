# real-upstream-corpus-btree-index-dynamic-20260531T031740Z-0

- Base accepted HEAD: `148cfd0e2c7cc75dba20ff0e424e615192f1e7c6`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex1.test`.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::bestindex1VirtualTableInConstraintCases(1000)` plus focused assertions in `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Upstream scenarios ported: `bestindex1-1.1`, `bestindex1-1.2`, `bestindex1-2.2.use.4`, `bestindex1-2.2.omit.4`, `bestindex1-2.2.use2.5`, `bestindex1-3.4`, `bestindex1-3.5`, `bestindex1-4.1`, and `bestindex1-5.0`.
- Behavior covered: virtual-table `xBestIndex` usable equality planning, IN constraint replanning with the second callback marked unusable, omitted-vs-used equality residual behavior, temp B-tree order preservation for IN probes, virtual-table cross-join register stability, standalone IN replanning, and module argument validation.
- Non-overlap: this targets upstream `bestindex1.test`, which was not represented in the existing B-tree/index focused corpus. It does not repeat accepted/queued `bestindex2`, `bestindex3`, `bestindex4`, `bestindex5`, `bestindex6`, `bestindex7`, `bestindex8`, `bestindex9`, `bestindexA`, `bestindexB`, `bestindexC`, `bestindexD/E`, `bestindexF`, B-tree page move/root collapse/overflow freelist, JSON, WAL, VFS, PRAGMA, or source-neutral cleanup clusters.
- Focused test evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed with `1 test files, 384926 assertions, 0 failures`; the new block contributes 1000 distinct focused PASS cases.
- PHP lint: `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` and `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed.
- Dependency closure: no new support component is needed; this reuses existing generic PHP corpus-plan and TestRunner infrastructure.
