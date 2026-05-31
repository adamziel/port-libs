# real-upstream-corpus-expression-affinity-dynamic-20260531T002350Z-0

Added `SQLiteRealUpstreamExpressionAffinityUnboundParameterDynamicTest.php` as an additive real upstream expression-affinity corpus batch.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario: `e_expr-11.7.1` unassigned host parameters are treated as `NULL`.
- Adjacent expression semantics: `e_expr-7`, `e_expr-10`, and `expr.test` operator/result-class NULL propagation through arithmetic, comparisons, casts, `quote()`, `typeof()`, `coalesce()`, and truth contexts.

Behavior:

- `SQLiteSelectSql` now runs parameter binding for all SELECT text, even when no bind values are provided.
- Missing positional and named host parameters resolve to SQL `NULL`, matching SQLite's unbound-parameter behavior.
- The old direct no-FROM test that expected a missing bind exception now asserts `SELECT ?1 AS v` returns `NULL`.

Focused assertion/PASS growth:

- New dynamic corpus: 4,441 focused TestRunner PASS cases.
- Focused selected command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityUnboundParameterDynamicTest.php lanes/libsqlite/tests/SQLiteSelectNoFromCorpusTest.php`.
- Result: `2 test files, 22263 assertions, 0 failures`.
- Adjacent bound-parameter regression command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityParameterDynamicTest.php`.
- Result: `1 test files, 32768 assertions, 0 failures`.
- `SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree, so the guard could not be run.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local SELECT SQL parameter literalizer, scalar expression evaluator, and sqlite3 oracle pattern already used by adjacent real upstream expression-affinity tests.

Non-overlap:

- This does not repeat accepted `types2` affinity matrices, cast target conversion batches, parameter-with-bound-values coverage, real arithmetic/cast oracle batches, expression ORDER BY, grouped SELECT text, subquery execution, JSON table source/constraint work, or storage/VFS/B-tree clusters. The new surface is specifically unassigned host parameter NULL behavior from upstream `e_expr.test`.
