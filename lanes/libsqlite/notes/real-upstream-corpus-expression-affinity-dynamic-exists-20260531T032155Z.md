# real-upstream-corpus-expression-affinity-dynamic-20260531T032155Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicExists20260531T032155ZTest.php` as an additive real upstream expression-affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `e_expr-34.1` through `e_expr-34.5`: `EXISTS` and `NOT EXISTS` expressions return integer 0/1 and are independent of subquery result-column count, values, NULLs, and ordering.

Implementation delta:

- `SQLiteSelectSql::valueExpression()` now parses projection-level `EXISTS (SELECT ...)` and `NOT EXISTS (SELECT ...)` expressions into the existing predicate evaluator.
- This reuses the existing correlated subquery executor and `SQLiteSelectPredicate` `EXISTS` / `NOT EXISTS` handling.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExists20260531T032155ZTest.php`
- Result: `1 test files, 10249 assertions, 0 failures`
- Focused PASS growth: `+2561` TestRunner cases from real upstream `e_expr.test` `e_expr-34.*`.

Non-overlap:

- This does not repeat accepted scalar subquery expression coverage from `e_expr-35` / `e_expr-36`, SELECT subquery filter coverage, `IN` / `NOT IN` subquery coverage, expression `ORDER BY`, grouped SELECT text, `types2`, `affinity2`, `affinity3`, CASE/iif, COLLATE, LIKE/GLOB, or real arithmetic/cast-prefix batches.
- The owned behavior is projection-level `EXISTS` / `NOT EXISTS` expression dispatch.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteSelectSql`, correlated subquery execution, and `SQLiteSelectPredicate` truth-value handling.
