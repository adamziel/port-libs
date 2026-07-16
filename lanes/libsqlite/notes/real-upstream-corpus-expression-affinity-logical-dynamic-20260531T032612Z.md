# real-upstream-corpus-expression-affinity-dynamic-20260531T032612Z-0

Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Ported sections: `expr.test` `expr-1.27` through `expr-1.34`, `expr-1.78` through `expr-1.81`, `e_expr.test` `e_expr-2.4`, and `e_expr.test` `e_expr-37.*`.

Implemented behavior:

- Added `SQLiteRealUpstreamExpressionAffinityLogicalDynamic20260531Test.php`.
- The test uses local `sqlite3` as oracle for 3,059 dynamic logical expression cases over `AND`, `OR`, `NOT`, `NOT NOT`, `CASE WHEN`, `iif()`, `IS TRUE`, `IS FALSE`, `IS NOT TRUE`, `IS NOT FALSE`, and `coalesce()` NULL propagation.
- The matrix covers NULL, integer, REAL, text numeric, non-numeric text, signed text, large integer, and tiny REAL-text operands through parser-level `SQLiteSelectSql` expression execution.

Non-overlap:

- Does not repeat accepted CASE/iif branch-selection, real arithmetic/operator, bitwise, BETWEEN, IN-list, unbound parameter, current-time literal, row-context syntax, truth aggregate, Unicode GLOB, expression ORDER BY, grouped SELECT text, or source-neutral API cleanup slices.
- The owned surface is real upstream logical truth-table and NULL-propagation expression behavior in scalar projection contexts.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityLogicalDynamic20260531Test.php`
  - `1 test files, 18360 assertions, 0 failures`
  - `3060` focused PASS lines.

Dependency closure:

- No new support component needed. The slice reuses native parser-level `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteSelectPredicate`, CASE/iif, SQL truthiness, and scalar function execution.
