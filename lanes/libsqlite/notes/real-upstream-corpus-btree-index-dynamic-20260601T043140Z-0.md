# real-upstream-corpus-btree-index-dynamic-20260601T043140Z-0

Status: ready for integration.

Base accepted HEAD: `a9f4989344098e67e1082ce806a8270acd26ace6`.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr2.test`.
- Upstream sections covered: `indexexpr2-4.200`, `indexexpr2-4.210`, and `indexexpr2-4.220`.
- Focus: `explain('UPDATE ...')` joined to `sqlite_master` rootpages opens only the table btree plus expression-index btrees whose dependent columns may change:
  - `UPDATE t2 SET b=b+1` opens `t2` and `t2abc`.
  - `UPDATE t2 SET c=c+1` opens `t2`, `t2abc`, and `t2cd`.
  - `UPDATE t2 SET c=c+1, f=NULL` opens `t2`, `t2abc`, `t2cd`, and `t2def`.
- Focused PASS growth: `1203` TestRunner PASS cases in `SQLiteRealUpstreamBtreeIndexExpr2UpdateOpenDynamicTest.php`.
- Behavior assertions: `33608`.
- Mapped denominator movement: none; libsqlite remains `1589 / 1589` mapped.

Non-overlap:

- Existing accepted indexexpr2 coverage handles section 3 collation/order behavior, sections 4.110 through 4.130 `refcnt()` recomputation, and later section 5 through 11 expression-index regressions.
- This owns only sections 4.200 through 4.220 and avoids accepted indexexpr1/indexexpr3, index2/index3/index5/index6/index7/index8/index9/indexA, autoindex, indexedby, bestindex, B-tree page relocation/root collapse/overflow/freelist/freeblock, JSON, WAL, VFS, PRAGMA, trigger, rowvalue, and source-neutral cleanup clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr2UpdateOpenDynamicTest.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr2UpdateOpenDynamicTest.php`
  - `1 test files, 33608 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 4 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr2UpdateOpenDynamicTest.php`
  - `2 test files, 98776 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

Dependency closure: no new support component is needed. This reuses lane-local expression-index dependency analysis, sqlite_master rootpage/open-opcode evidence, and B-tree/index dynamic corpus helpers.

Root harness: not run - isolated micro-slice.
