# real-upstream-corpus-expression-affinity-dynamic-row-context-20260530T235221Z-0

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T235221Z-0`

Added `SQLiteRealUpstreamExpressionAffinityDynamicRowContextTest.php` as an additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `expr-1.1..1.122`: row-context arithmetic, REAL expressions, comparisons, boolean operators, bitwise operators, NULL propagation, BETWEEN, IS, `IS DISTINCT FROM`, and division/modulo-by-zero behavior over `test1(i1,i2,r1,r2,t1,t2)`.

Focused movement:

- `1,141` focused TestRunner PASS cases.
- `5,535` focused behavior assertions.
- Behavior fix: `SQLiteSelectSql` expression parsing now recognizes `IS DISTINCT FROM` and `IS NOT DISTINCT FROM` as multi-word comparison expression operators, reusing existing `SQLiteSelectPredicate` evaluator semantics.

Non-overlap:

- This shard is row-context expression evaluation over upstream `expr.test` table variables. It does not repeat accepted literal-only REAL arithmetic, literal CAST/target conversion matrices, parameter-token affinity, types2 storage/comparison matrices, expression `ORDER BY`, grouped SELECT text, or JSON/B-tree/WAL/VFS corpus slices.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRowContextTest.php`
- Result: `1 test files, 5535 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The patch reuses existing `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteSelectPredicate`, `SQLiteAffinityComparison`, and the local `sqlite3` oracle pattern already used by adjacent real upstream expression tests.
