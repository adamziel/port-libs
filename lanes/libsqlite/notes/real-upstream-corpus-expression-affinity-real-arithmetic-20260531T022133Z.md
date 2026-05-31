# real-upstream-corpus-expression-affinity-dynamic-20260531T022133Z-0

Added `SQLiteRealUpstreamExpressionAffinityRealArithmeticDynamicTest.php` as an additive real upstream expression/affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Scenario range: `expr-2.1` through `expr-2.10`
- Behavior: REAL arithmetic, division, modulo, comparison, NULL propagation, truthiness, `quote()`, and `typeof()` parity through parser-level `SQLiteSelectSql` expression execution.

Focused coverage:

- 3,895 distinct real upstream-shaped TestRunner cases.
- 23,375 focused assertions when run alone.
- The cases are oracle-backed by local `sqlite3` and widen the fixed upstream REAL rows over numeric, text-numeric, BLOB-text, and NULL operands.

Non-overlap:

- This does not repeat accepted `types2` literal/list/subquery affinity matrices, `affinity2` and `affinity3` storage/join behavior, CAST target-affinity batches, IS DISTINCT affinity, parameter token behavior, signed/large literal behavior, bitwise behavior, LIKE/GLOB predicates, integer overflow arithmetic promotion, exponent-text modulo parity, or huge out-of-range modulo parity. The narrower surface is upstream `expr.test` `expr-2.*` REAL arithmetic and comparison expression parity.

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteSelectSql`, expression arithmetic/comparison dispatch, scalar `quote()`/`typeof()`, and the existing local `sqlite3` oracle pattern used by adjacent real upstream expression tests.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealArithmeticDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealArithmeticDynamicTest.php`
- `git diff --check -- lanes/libsqlite`
