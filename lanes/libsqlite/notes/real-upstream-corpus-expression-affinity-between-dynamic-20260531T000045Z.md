# Real upstream expression affinity BETWEEN dynamic slice

- Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T000045Z`
- Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T000045Z-0`
- Base accepted HEAD: `8c83cd38b21e6ef37afec24c7a1c1aa06c561658`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Upstream sections: `e_expr-13.1` (`BETWEEN` equivalence/single-evaluation semantics) and `e_expr-13.2.1..13.2.30` (`BETWEEN` precedence relative to equality, `LIKE`, `AND`, and range comparisons).

## Behavior

Added `SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php`, a `sqlite3`-oracle-backed dynamic matrix covering:

- 12 left expression families, including integers, reals, numeric text, lexical text, and `NULL`;
- 12 lower/upper bound families, including numeric, text, reversed, and `NULL` bounds;
- `BETWEEN`, `NOT BETWEEN`, explicit comparison-pair equivalence, and `IS TRUE` / `IS FALSE` wrappers;
- the 30 literal upstream precedence expressions from `e_expr-13.2`.

The first red run exposed six parser/executor failures around `BETWEEN` tail precedence. `SQLiteSelectSql` now parses the upper bound with a first top-level `AND`, then applies same-precedence equality/`LIKE` or lower-precedence trailing `AND` outside the `BETWEEN` expression. Range comparisons after the upper bound remain inside the upper expression, matching upstream `e_expr-13.2.28`.

## Evidence

- Red-first focused command before fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php` -> `1 test files, 3004 assertions, 6 failures`.
- Focused command after fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php` -> `1 test files, 3008 assertions, 0 failures`.
- Related expression-affinity regression command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealConversionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityExpr2TruthDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php` -> `5 test files, 22321 assertions, 0 failures`.
- New focused TestRunner PASS-line movement: `+1501` from the new dynamic file.
- Mapped denominator movement: none; upstream inventory is already complete.

## Non-overlap

This slice does not repeat the accepted arithmetic, bitwise, `expr2` truthiness, affinity2 equality, cast, or real-conversion matrices. It owns only `e_expr.test` `BETWEEN` dynamic affinity/precedence behavior and the narrow `SQLiteSelectSql` parser fix needed to pass those cases.

## Dependency closure

No new support component is needed. The existing `sqlite3` CLI oracle pattern used by adjacent real-upstream dynamic tests is reused for expected output generation; native PHP execution remains through `SQLiteSelectSql`.
