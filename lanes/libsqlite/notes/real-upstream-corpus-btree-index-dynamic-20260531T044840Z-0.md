# real-upstream-corpus-btree-index-dynamic-20260531T044840Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereI.test`, sections `1.1` through `3.0`.
- Ported behavior cluster: `WITHOUT ROWID` multi-index OR planning and result de-duplication over integer primary key, text primary key, and composite primary key tables.
- Added focused PHP corpus: `SQLiteRealUpstreamBtreeWhereIWithoutRowidOrDynamicTest.php` with 1,203 TestRunner cases, including 1,200 dynamic upstream-backed behavior cases plus source-range, invalid-size, and dependency-closure checks.
- Non-overlap: this does not repeat accepted B-tree page relocation/root collapse/overflow freelist work, index5/index6/index7/index8/indexA dynamic batches, whereK/whereL/whereM/whereN planner batches, JSON table cursor/source/constraint work, VFS rollback/sync/lock/write work, WAL byte/checkpoint work, or SQL grouped/subquery/order-expression text dispatch.
- Dependency closure: no new support component needed; the slice reuses lane-local B-tree/index dynamic corpus modeling and existing TestRunner infrastructure.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereIWithoutRowidOrDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereIWithoutRowidOrDynamicTest.php`: 1 test file, 28,808 assertions, 0 failures, 1,203 PASS lines.
- adjacent guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereKOrFactoringDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLMNConstantPropagationDynamicTest.php`: 2 test files, 32,156 assertions, 0 failures.
- API guard: generic-specificity guard file not present in this worktree.
- `git diff --check -- lanes/libsqlite`: passed.
