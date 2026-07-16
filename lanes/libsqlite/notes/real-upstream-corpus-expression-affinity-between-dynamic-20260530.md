# Real Upstream Corpus: Expression Affinity BETWEEN Dynamic

Session: `port-dev-sqlite-yield-dyn-real-expr-20260530T212211Z`
Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T212211Z-0`
Base accepted HEAD: `0c8f3edfb501039f3334d15acf03c96514063bb1`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-1.86` through `expr-1.95`: `BETWEEN` / `NOT BETWEEN`, including NULL lower and upper bounds.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  - `affinity2-110` through `affinity2-300`: comparison affinity across INTEGER, REAL, BLOB, NUMERIC, and TEXT columns.

## Behavior

Added `SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php`, a 1,000-case sqlite3-oracle dynamic matrix for `BETWEEN` / `NOT BETWEEN` over five upstream affinity columns and ten lower/upper bound literal forms.

The red-first run exposed two real behavior gaps:

- `SQLiteSelectPredicate::between()` compared raw storage classes instead of applying the same operand affinity path used by ordinary comparisons.
- `SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities()` treated INTEGER column affinity like `CAST(... AS INTEGER)`, truncating fractional numeric text such as `'4.5'`; SQLite column affinity stores this as REAL.

## Non-Overlap

This avoids the accepted cast matrix, `affinity2` equality/range comparison matrix, and NULL-aware equality matrix by targeting `BETWEEN`'s two-comparison semantics and INTEGER-column insertion of fractional numeric text.

## Verification

- Red-first focused run before fixes: `1 test files, 1005 assertions, 286 failures`, then `1 test files, 1005 assertions, 12 failures`.
- Focused new test after fixes: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php` -> `1 test files, 1005 assertions, 0 failures`.
- Focused family: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity2DynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php` -> `4 test files, 10975 assertions, 0 failures`.
- API guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- PHP lint passed for `SQLiteSelectPredicate.php`, `SQLiteRealExpressionAffinityCorpusPlan.php`, and `SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php`.
- `git diff --check -- lanes/libsqlite` passed.

## Dependency Closure

No new support component is needed. The slice reuses the existing `sqlite3` oracle pattern for real upstream corpus tests and existing native PHP `SQLiteSelectSql`, `SQLiteSelectPredicate`, and affinity helpers.
