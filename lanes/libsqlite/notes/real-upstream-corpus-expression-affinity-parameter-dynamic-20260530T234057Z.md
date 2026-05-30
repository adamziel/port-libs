# Real Upstream Expression Affinity Parameter Dynamic

- Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T234057Z-0`
- Base accepted HEAD: `1e28a5dbe5f8813a907a64ec2d403f8339418de7`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-11.2` through `e_expr-11.6` host parameter token forms
  - `e_expr-7`, `e_expr-10`, and `expr.test` `expr-13` expression result class and numeric conversion behavior

## Change

Added `SQLiteRealUpstreamExpressionAffinityParameterDynamicTest.php`, an oracle-backed real upstream corpus shard that binds qmark, numbered qmark, colon, at-sign, and dollar parameters through `SQLiteSelectSql` and checks arithmetic, comparison, `IS`/`IS NOT`, `quote()`, `typeof()`, and NULL propagation against `sqlite3` literal-equivalent output.

The first focused run exposed a SQLite parity bug in `quote()` for negative zero. `SQLiteCoreScalarFunction::formatFloat()` now canonicalizes `-0.0` to SQLite's `0.0`.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`: pass
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityParameterDynamicTest.php`: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityParameterDynamicTest.php`: `1 test files, 32768 assertions, 0 failures`; 16381 focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealExprTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealConversionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealOracleTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `4 test files, 24220 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`: pass

## Non-Overlap

This avoids the rejected expression-affinity distinct-null source handoff and does not change comparison affinity rules. It covers the upstream `e_expr-11` parameter-token family combined with expression evaluation and the narrow `quote(-0.0)` parity fix.

## Dependency Closure

No new support component is needed. The shard reuses the existing `SQLiteSelectSql` parameter binder, `SQLiteCoreScalarFunction`, and local `sqlite3` oracle pattern already used by adjacent real upstream expression tests.
