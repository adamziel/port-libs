# real-upstream-corpus-expression-affinity-dynamic-20260530T220601Z-0

Added `SQLiteRealUpstreamExpressionAffinityEExpr7DynamicTest.php` as an
additive real upstream expression-affinity corpus batch.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Covered section: `e_expr-7.*`

Behavior:

- Ports the real upstream result storage-class matrix for binary operators
  across integer, real, text, BLOB, empty, and NULL operands.
- The selected operator set is `||`, arithmetic, bitwise shifts, comparison,
  equality/inequality, `IS`, `IS NOT`, `LIKE`, and `GLOB`.
- The test uses local `sqlite3` as an oracle for expected `typeof(...)` values
  and exercises native `SQLiteSelectSql` for each port result.
- Red-first result: the first focused run exposed 30 modulo storage-class
  failures where real operands returned integer storage. `SQLiteSelectExpression`
  now preserves SQLite's real storage class for `%` when either numeric operand
  is real while leaving the integer remainder value unchanged.

Focused verification:

- Before fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityEExpr7DynamicTest.php`
  failed with `1 test files, 11129 assertions, 30 failures`.
- After fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityEExpr7DynamicTest.php`
  passed with `1 test files, 11159 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityEExpr7DynamicTest.php`

Countability and non-overlap:

- Adds 3,719 focused TestRunner PASS cases and 11,159 assertions.
- This is real upstream behavior from `e_expr.test`, not generated fake script
  ids or metadata-only admission rows.
- It does not repeat accepted expression precedence bulk coverage, CAST target
  affinity, `types2` matrices, `affinity2`/`affinity3`, broad CAST helper
  tests, SQL expression `ORDER BY`, Unicode GLOB ranges, or LIKE/GLOB residual
  planner tests. The new surface is `e_expr-7` storage-class parity for
  binary expression results.

Dependency closure:

- No new support component is needed. The batch reuses the existing native
  `SQLiteSelectSql` executor and local `sqlite3` oracle pattern used by other
  real upstream corpus tests.
