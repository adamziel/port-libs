# real-upstream-corpus-expression-affinity-collate-dynamic-20260531T024445Z-0

Added `SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531T024445ZTest.php`
as an additive real upstream expression-affinity corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `e_expr-9.1` through `e_expr-9.25`: `COLLATE` as a postfix expression
  operator, including operand-level collation versus collation on the already
  computed comparison result.

Behavior covered:

- `BINARY` and `NOCASE` postfix `COLLATE` on left operands, right operands, and
  parenthesized comparison results.
- Comparison operators `<`, `<=`, `>`, `>=`, `=`, `==`, `!=`, `<>`, `IS`, and
  `IS NOT` across text, case variants, numeric-looking text, numeric literals,
  empty text, and `NULL`.
- Oracle-backed `quote()`, `typeof()`, and `IS NULL` parity through
  `SQLiteSelectSql`.
- Narrow parser fix: `SQLiteSelectSql::valueExpression()` now recognizes
  `IS NOT NULL`, `IS NULL`, and bare `NOT NULL` before generic comparison
  splitting so `COLLATE` expressions are not split as bare `IS`.

Verification:

- Red-first focused run exposed 48 failures where `IS NOT NULL` following a
  left-side or parenthesized `COLLATE` expression was parsed as bare `IS`.
- After the parser fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollateDynamic20260531T024445ZTest.php`
  passed with `1 test files, 34569 assertions, 0 failures`.
- Focused PASS-line delta: `8641` TestRunner PASS cases, including the
  ownership guard.

Non-overlap:

- This does not repeat accepted REAL arithmetic affinity, cast target, host
  parameter, types2 comparison, affinity2 storage, affinity3 view/join, LIKE,
  GLOB, or expression ORDER BY batches. The owned surface is upstream
  `e_expr-9.*` postfix `COLLATE` binding and comparison-affinity behavior.

Dependency closure:

- No new support component is needed. The shard reuses `SQLiteSelectSql`,
  existing expression/predicate/collation dispatch, and the local `sqlite3`
  oracle pattern already used by adjacent real upstream expression tests.
