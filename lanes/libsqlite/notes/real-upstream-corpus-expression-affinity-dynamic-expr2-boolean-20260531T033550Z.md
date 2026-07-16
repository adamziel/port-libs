# Real Upstream Corpus: Expression Affinity Dynamic Expr2 Boolean

- Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T033550Z-0`
- Base accepted HEAD: `eb22516d8f29af7145a28b1cc2453b19311c1d0b`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test`
- Upstream scenarios: `expr2-1.1`, `expr2-1.2.1`, `expr2-1.2.2`, `expr2-1.3`, `expr2-1.4.1`, and `expr2-1.4.2`
- Added focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicExpr2BooleanTest.php`
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicExpr2BooleanTest.php` passed with `1 test files, 9607 assertions, 0 failures`
- PASS-line delta: `+2401` focused TestRunner PASS cases (`2400` oracle-backed dynamic cases plus one ownership/count test)
- Updated `lane-status.json` `phpPass`: `1878158 -> 1880559`

## Non-Overlap

This owns upstream `expr2.test` boolean identity row-context expressions with
`IS TRUE/FALSE`, `IS NOT TRUE/FALSE`, `NOT`, `OR`, and `c0 = 1` predicates
expanded across dynamic integer, REAL, NUMERIC, TEXT, and NULL operands. It does
not repeat the already accepted expression modulo, overflow arithmetic,
expr.test REAL arithmetic, cast prefix, affinity3 APR/view REAL division,
operator-precedence, EXISTS projection, unary-plus, or expression-index
range-cost clusters.

## Dependency Closure

No new support component is needed. The focused test reuses existing
`SQLiteSelectSql` expression execution and uses local `sqlite3` only as an
oracle for expected scalar results; the upstream source file remains the
hydrated SQLite `.test` file above.
