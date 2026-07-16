## Slice

- Lane: libsqlite
- Micro-slice: real-upstream-corpus-expression-affinity-dynamic-20260531T062259Z-0
- Base accepted HEAD: 68a3731675769814ce7d56857d9182ac7f8b3613

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Sections: `e_expr-13.1` and `e_expr-13.2`
- Behavior: `BETWEEN` is equivalent to paired comparisons except for single
  evaluation of the left expression, and `BETWEEN` precedence matches equality
  and `LIKE` while binding more tightly than `AND`.

## Handoff

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicBetweenPrecedence20260531T062259ZTest.php`.
- The test expands the upstream `BETWEEN` precedence forms across numeric,
  text, NULL, `LIKE`, `GLOB`, comparison, `AND`, and `OR` operands.
- Expected selected movement if accepted: `+39272` focused TestRunner PASS
  lines, moving lane-local selected throughput from `2495399` to `2534671`.
- Focused assertion count: `78549`.
- Mapped denominator remains `1589 / 1589`.

## Non-Overlap

This shard owns `e_expr.test` `e_expr-13` `BETWEEN` precedence expansion. It
does not repeat the accepted expression where/collate, expression `ORDER BY`,
expression-index range-cost, `IS DISTINCT`, unary-plus, `LIKE`/`GLOB` exact,
or whereB comparison-affinity shards.

## Verification

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicBetweenPrecedence20260531T062259ZTest.php`
  - `1 test files, 78549 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicBetweenPrecedence20260531T062259ZTest.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/libsqlite`
  - passed

## Dependency Closure

No new support component is needed. The shard reuses `SQLiteSelectSql`
constant expression execution and a local `sqlite3` oracle. Volatile function
single-evaluation tracing remains a future executor hook if a later slice
ports upstream `x()` evaluation-count assertions directly.
