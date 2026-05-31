# Real Upstream Corpus: Expression Affinity Dynamic REAL Operator

Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T030340Z`
Base: `57904efd88f87abfad6d70c753ea59660958850e`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-13.8`
  - `expr-13.9`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-6.*`
  - `e_expr-7.*`

## Behavior

Added a focused dynamic matrix for REAL-ish expression operands through modulo,
division, arithmetic, bitwise, shift, comparison, and logical operators. The
matrix uses the local `sqlite3` binary as the oracle for `quote()`, `typeof()`,
and NULL propagation.

The red-first run exposed modulo coercion on REAL operands outside int64 range:
PHP casted a large float directly to int, emitted warnings, and produced a
different remainder. `SQLiteSelectExpression::numericValue()` now routes `%`
through the same SQLite integer-affinity clamp path used by bitwise operators
before computing the remainder.

## Focused Evidence

- Before fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealOperatorTest.php`
  - `1 test files, 8662 assertions, 24 failures`
- After fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealOperatorTest.php`
  - `1 test files, 8710 assertions, 0 failures`

New focused PASS-line movement: `2177` PASS cases.
Mapped coverage remains `1589 / 1589`.

## Non-Overlap

This does not repeat the accepted cast-target, REAL conversion, REAL arithmetic,
syntax-diagram, IS DISTINCT, Unicode GLOB, SELECT ORDER BY, or planner range-cost
clusters. The owned gap is operator coercion for dynamic REAL expression pairs,
especially `%` after SQLite integer-affinity conversion.

## Dependency Closure

No new support component is needed. The slice reuses existing
`SQLiteSelectSql`, `SQLiteSelectExpression`, and the local `sqlite3` oracle used
by neighboring real-upstream corpus tests.
