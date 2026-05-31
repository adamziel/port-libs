# real-upstream-corpus-expression-affinity-dynamic-20260531T060050Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicPrecedence20260531T060050ZTest.php` as an additive real upstream expression-affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario family: `e_expr-1.*` binary operator precedence matrix.

Behavior covered:

- 24 SQLite binary operators from the upstream precedence table: `||`, arithmetic, shifts, bitwise operators, comparison operators, `IS`, `IS NOT`, `LIKE`, `GLOB`, `AND`, and `OR`.
- 17 upstream value triples from the `e_expr-1.*` matrix.
- 9,792 operator-pair/value cases generated from real upstream structure, each checked against a local `sqlite3` oracle and then executed through native `SQLiteSelectSql`.
- MATCH/REGEXP are intentionally excluded because the upstream Tcl section installs connection-local callbacks to make those operators behave like equality; the PHP port does not expose that callback shim in this slice.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicPrecedence20260531T060050ZTest.php`
- Result: `1 test files, 29382 assertions, 0 failures`
- Focused selected movement: `+9793` real TestRunner PASS cases, counting 9,792 precedence cases plus one ownership/provenance case.

Non-overlap:

- This does not repeat accepted REAL arithmetic, overflow arithmetic, boolean truthiness, NULL/coalesce, BETWEEN, LIKE/GLOB residual-only coverage, operator-result-only `e_expr-2.*` through `e_expr-7.*`, syntax-diagram `e_expr-12.3`, expression `ORDER BY`, SELECT subqueries, whereG planner-hint affinity, cast/types affinity, JSON, WAL, VFS, B-tree, PRAGMA, trigger, or source-neutral cleanup batches.
- The narrower owned surface is adjacent binary operator precedence composition from `e_expr-1.*`.

Dependency closure:

- No new support component is needed. This reuses existing native `SQLiteSelectSql` expression parsing/execution and the local `sqlite3` binary only as an oracle for focused test generation.
