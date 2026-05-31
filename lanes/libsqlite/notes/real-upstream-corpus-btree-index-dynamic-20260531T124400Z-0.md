# real-upstream-corpus-btree-index-dynamic-20260531T124400Z-0

This slice adds a non-overlapping B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout.

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/where7.test`.
- Upstream sections covered: `where7-1.1.1` through `where7-1.32`.
- Focus: multi-index OR optimizer row production, overlapping OR rowid de-duplication, `count_changes` delete counts, unary-plus no-index guards, equality/range OR arms, large OR-list compilation, table-scan fallback, and temp-sort counters.
- Focused PHP movement: `SQLiteBTreeIndexDynamicCorpusPlan::where7MultiIndexOrOptimizerCases(1200)` plus `SQLiteRealUpstreamBtreeWhere7MultiIndexOrDynamicTest.php`.
- Count type: PASS-line growth only. The upstream denominator remains fully mapped at `1589 / 1589`.
- Non-overlap: avoids accepted `where8`, `where9`, `whereA`, `whereC`, `whereD`, `whereE`, `whereF`, `whereG`, `whereH`, `whereI`, `whereJ`, `whereK`, `whereL/M/N`, `index*`, `bestindex*`, B-tree page relocation/root collapse/overflow freelist/freeblock, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup clusters.
- Dependency closure: no new support component needed; this reuses lane-local B-tree/index dynamic corpus helpers, row-array predicate evaluation, rowid de-duplication, index-choice metadata, scan/sort counters, and TestRunner assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere7MultiIndexOrDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere7MultiIndexOrDynamicTest.php` passed: `1 test files, 26699 assertions, 0 failures`, `1203` focused PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere7MultiIndexOrDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereGLikelihoodPlannerDynamicTest.php` passed: `3 test files, 46796 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
