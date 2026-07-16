# real-upstream-corpus-expression-affinity-dynamic-20260531T031700Z-0

Added `SQLiteRealUpstreamExpressionAffinityOperatorPrecedenceDynamicTest.php` as an additive real upstream expression-affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario family: `e_expr-1.*` binary operator precedence checks.

Coverage:

- 3,388 oracle-backed dynamic expression cases plus one ownership/provenance case.
- 22 binary operators: concatenation, arithmetic, modulo, bit shifts, bitwise operators, comparisons, `IS`, `IS NOT`, `AND`, and `OR`.
- 7 operand rows covering integer, negative integer, REAL, text-numeric, text, and NULL operands.
- Each case compares the default parse tree with left-grouped and right-grouped trees through `quote()` and `typeof()` against local `sqlite3` oracle output.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityOperatorPrecedenceDynamicTest.php`
  - `1 test files, 23722 assertions, 0 failures`
  - `3389` real TestRunner PASS cases.

Non-overlap:

- This does not repeat accepted `types2` affinity matrices, CAST target dispatch, parameter binding, syntax diagram expression forms, overflow arithmetic, real literal, modulo-only, NULL comparison, LIKE/GLOB, expression `ORDER BY`, SELECT subquery, or planner range-cost batches.
- It targets upstream `e_expr.test` operator-precedence parse/evaluation behavior through the existing bounded `SQLiteSelectSql` executor.

Dependency closure:

- No new support component is needed. The shard reuses the existing native `SQLiteSelectSql` executor, scalar expression evaluator, affinity comparison helpers, and local `sqlite3` oracle pattern already used by adjacent real upstream expression tests.
