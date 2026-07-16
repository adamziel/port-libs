# real-upstream-corpus-expression-affinity-dynamic-20260531T050045Z-0

Added `SQLiteRealUpstreamExpressionAffinityCollatePostfixDynamicTest.php` as an additive real upstream expression/affinity corpus slice.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario range: `e_expr-9.10..9.23`
- Behavior: postfix `COLLATE` binds to the operand expression for comparison and `BETWEEN` expressions, not to an already-computed boolean result.

## Implementation

- Fixed `SQLiteSelectPredicate::between()` so a collation attached to the upper `BETWEEN` bound does not leak into the lower-bound comparison.
- Left-expression collations still override both bounds, matching SQLite comparison collation precedence.

## Focused Evidence

- Red-first: the new focused test initially failed one upstream-derived case:
  - `'ALPHA' BETWEEN 'aaa' AND NULL COLLATE NOCASE` expected `0`, port returned `NULL`.
- After fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCollatePostfixDynamicTest.php`
  - Result: `1 test files, 35865 assertions, 0 failures`
- Adjacent regression:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityBetweenDynamicTest.php`
  - Result: `1 test files, 3008 assertions, 0 failures`

## Countability

- Focused TestRunner PASS cases: `+8965`
- Focused behavior assertions: `+35865`
- Mapped denominator coverage: unchanged at `1589 / 1589`; this is PASS-line growth over already mapped real upstream `e_expr.test` inventory.

## Non-Overlap

This slice does not repeat accepted REAL arithmetic, CAST target affinity, `types2` matrices, boolean truthiness, `BETWEEN` precedence, IN-list expression, LIKE/GLOB exact matching, expression `ORDER BY`, SELECT subqueries, JSON table, WAL/VFS, B-tree, or source-neutral cleanup batches. It owns the postfix `COLLATE` expression-binding branch of upstream `e_expr.test` section 9.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `SQLiteSelectSql` expression executor, `SQLiteSelectPredicate` comparison logic, and the local `sqlite3` oracle pattern used by adjacent real upstream corpus tests.
