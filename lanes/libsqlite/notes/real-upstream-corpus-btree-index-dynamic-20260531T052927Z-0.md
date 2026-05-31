# real-upstream-corpus-btree-index-dynamic-20260531T052927Z-0

Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`

Added `SQLiteRealUpstreamBtreeWhere9LateOrJoinDynamicTest.php` as an additive real upstream B-tree/index dynamic corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/where9.test`

Ported upstream sections:

- `where9-6.4.1` through `where9-6.8.4`: OR-clause DELETE/UPDATE behavior, row preservation after rollback, unary-plus scan fallback, `NOT INDEXED`, and `INDEXED BY t1b` legal mutation cases.
- `where9-7.0` through `where9-7.3.2`: external AND terms distributed across OR arms using compound indexes.
- `where9-8.1` through `where9-10.2`: OR terms in joins around `LEFT JOIN` preserve NULL-extended rows and right-side join results.
- `where9-11.1`: multi-index OR copies subexpressions that contain flattened `UNION ALL` view subqueries.

Focused behavior:

- `SQLiteBTreeIndexDynamicCorpusPlan::where9LateOrJoinMutationCases(1000)`
- `1000` distinct dynamic TestRunner PASS cases plus `3` guard/source/dependency checks.
- Focused result: `1 test files / 22759 assertions / 0 failures / 1003 PASS lines`.

Non-overlap:

- This extends the accepted/previous `where9.test` early multi-index OR batch, which covered `where9-1.2.1` through `where9-6.3.2`.
- It avoids accepted B-tree page relocation, root collapse, overflow freelist/freeblock release, index-interior merge, index create-validation, index sort-order, index8 order/limit, `whereI`, `whereK`, `whereL/M/N`, bestindex, JSON, WAL, VFS, PRAGMA, SELECT expression ORDER BY, and source-neutral cleanup clusters.
- Count this as PASS-line growth only; mapped denominator coverage remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere9LateOrJoinDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere9LateOrJoinDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and records upstream planner/mutation metadata directly from the hydrated SQLite upstream `where9.test`.
