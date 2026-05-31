# Real Upstream Corpus Expression Affinity Overflow Dynamic

- Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T000625Z-0`
- Base accepted HEAD: `88eb6ac3e2ad25d5a4756e5a167672b605fd3e97`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Covered upstream section: `expr-1.200` through `expr-1.271`, 64-bit integer boundary arithmetic and REAL fallback on overflow.

## Change

Added `SQLiteRealUpstreamExpressionAffinityOverflowDynamicTest.php`, an oracle-backed dynamic corpus shard over the upstream overflow operand set. The shard checks `+`, `-`, `*`, and `/` across int64 boundary, 32-bit boundary, and square-root-of-int64 operands through `SQLiteSelectSql`.

The red-first run exposed two parser/evaluator parity gaps:

- `-9223372036854775808` was parsed as unary minus over an overflow REAL literal instead of as the SQLite int64 minimum literal.
- `-9223372036854775808 / -1` returned an integer through `intdiv()` instead of SQLite's REAL overflow result.

`SQLiteSelectSql` now parses signed integer literals with explicit int64 bounds, and `SQLiteSelectExpression` now routes the int64-min-divided-by-minus-one overflow case to REAL division.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityOverflowDynamicTest.php`
  - `1 test files, 6977 assertions, 0 failures`
  - `1744` focused PASS lines (`1743` dynamic oracle cases plus the ownership case)

## Non-Overlap

This does not repeat the accepted expression affinity `types2`, CAST target, host-parameter, LIKE/GLOB, NULL comparison, `e_expr-7`, `BETWEEN`, or row-context shards. It owns the upstream `expr.test` overflow arithmetic region and the narrow parser/evaluator behavior needed for that region.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteSelectSql` expression parser/evaluator and the local `sqlite3` oracle pattern used by adjacent real upstream corpus tests.
