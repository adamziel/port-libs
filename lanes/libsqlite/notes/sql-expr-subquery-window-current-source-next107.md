# sql-expr-subquery-window-current-source-next107

Status: focused PHP behavior growth for parser-level SELECT named `WINDOW`
clauses used by window expressions whose partition, ordering, frame, and
FILTER expressions depend on correlated scalar subqueries.

Behavior:

- `SQLiteSelectSql` now recognizes a top-level `WINDOW` clause between
  `HAVING` and final `ORDER BY`/`LIMIT`.
- Bounded named-window definitions are expanded into existing `OVER (...)`
  window parsing, including multiple definitions, final ordering/limits, CTEs,
  derived tables, and scalar subqueries.
- The slice intentionally rejects duplicate named windows, missing names,
  malformed definitions, and base-window chaining rather than silently
  producing an incorrect plan.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlNamedWindowSubqueryCurrentSourceNext107Test.php`
- Result: `1 test files, 46 assertions, 0 failures`
- PASS lines: `46`
- `php lanes/libsqlite/examples/application-select-sql-named-window-subquery-current-source-next107.php --self-test`
- Result: `application-select-sql-named-window-subquery-current-source-next107 self-test passed`

Non-overlap:

This avoids accepted parser-level SELECT SQL text/JOIN/GROUP/subquery/ORDER
expression clusters, JSON table source/cursor/constraint work, JSON aggregate
window FILTER/ORDER and JSONB DISTINCT handling, encoding LIKE/GLOB current
source work, WAL/pager/VFS current-source work, and B-tree pointer-map/freelist
work. The narrower surface is the `WINDOW name AS (...)` clause boundary and
named-window reuse when subqueries provide current-row values to window
partition/order/filter/frame execution.

Dependency closure:

No new support component is needed. This reuses the existing bounded
`SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectExpression`, and
`SQLiteWindowFunction` PHP components.
