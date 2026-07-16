# real-upstream-corpus-btree-index-dynamic-20260531T103244Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereF.test`

Ported sections:

- `whereF-1.1` through `whereF-3.3`: costed join-order planner choices scan `t2` before probing `t1` through unique/composite indexes, including CROSS JOIN fixed-order variants.
- `whereF-4.0`: composite primary-key search selection for `a=? AND b=?` despite competing secondary indexes.
- `whereF-5.1` through `whereF-5.6`: OR-optimization row counts and VM-step guard behavior when a false outer-loop predicate must be tested before the `t2(f2)` seek.
- `whereF-7.1` through `whereF-7.3`: OR-factoring regression coverage for nullable correlated row-number emulation and GLOB-derived virtual terms excluded from factoring bytecode.

Focused addition:

- `SQLiteBTreeIndexDynamicCorpusPlan::whereFJoinOrderAndOrFactoringCases(1000)`
- `SQLiteRealUpstreamBtreeWhereFJoinPlannerDynamicTest.php`

Focused evidence:

- Adds 1003 focused TestRunner PASS cases from the real upstream `whereF.test` planner corpus.
- Focused run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereFJoinPlannerDynamicTest.php`
- Result: `1 test files, 20953 assertions, 0 failures`

Non-overlap:

- This owns upstream `whereF.test` sections `1.*`, `2.*`, `3.*`, `4.0`, `5.*`, and `7.*`.
- It deliberately excludes `whereF-6.*` because that branch uses correlated `json_each()` virtual-table subqueries and would overlap recent JSON table source/cursor/correlation work.
- It does not repeat the accepted `whereA` reverse-unordered scan, `whereC` rowid composite range, `whereD` covering OR, `whereE` alter-table planner, `whereH/I/J/K/L/M/N` planner batches, `index6/7/8/9/A`, `indexedby`, `bestindex*`, B-tree page relocation/root-collapse/overflow freelist/freeblock, VFS, WAL, PRAGMA, SELECT, or JSON table dynamic slices.

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner, join-order metadata, OR-index guard modeling, and opcode exclusion checks.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereFJoinPlannerDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereFJoinPlannerDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
