# real-upstream-corpus-expression-affinity-dynamic-20260530T233602Z-0

Added `SQLiteRealUpstreamExpressionAffinityDynamicBitwiseTest.php` as a real
upstream expression/affinity corpus batch backed by SQLite upstream
`test/expr.test`.

Upstream source sections:

- `expr-1.42..1.46`: bitwise OR, AND, NOT, and signed shift behavior.
- `expr-1.56`: remainder operator behavior.
- `expr-1.96..1.109`: NULL propagation and divide/remainder by zero behavior.

Focused coverage:

- 4,374 distinct dynamic cases plus one ownership case.
- 8,754 focused assertions.
- 4,375 focused TestRunner PASS lines.
- Operand matrix covers integers, REAL casts, numeric text, numeric-tail text,
  BLOB text, NULL, negative right-hand bitwise operands, high shift counts, and
  negative shift counts.

Behavior fix:

- Red-first focused run exposed 62 mismatches where signed right-hand operands
  to `&` and `|` were incorrectly made positive.
- `SQLiteSelectExpression::bitwiseValue()` now only flips negative right-hand
  operands for `<<` and `>>`, preserving SQLite signed semantics for `&` and
  `|`.

Non-overlap:

- This does not repeat accepted arithmetic, cast-target, real-conversion,
  BETWEEN, NULL-comparison, LIKE/GLOB, expression ORDER BY, grouped SELECT,
  JSON table source/cursor/constraint, B-tree, WAL, VFS, or metadata-only
  runner coverage.
- It avoids the rejected distinct-null source behavior and keeps the source
  change limited to the signed bitwise evaluator primitive proven by upstream
  `expr.test`.

Dependency closure:

- No new support component is needed. The batch reuses the existing
  `SQLiteSelectSql` parser/executor, `SQLiteSelectExpression` evaluator, and a
  local `sqlite3` oracle for expected values.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicBitwiseTest.php`
  - Initial red run: `1 test files, 8754 assertions, 62 failures`.
  - Final run: `1 test files, 8754 assertions, 0 failures`.
