# JSON Aggregate Expression ORDER Current Source Next99

Status: focused parser-level PHP behavior growth for non-window
`json_group_array()` / `jsonb_group_array()` aggregates whose aggregate-local
`ORDER BY` terms are expressions instead of plain source columns.

Before this slice, current-source JSON aggregate execution rejected
`json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC)` while
already supporting plain multi-column order terms. This patch carries parsed
ORDER BY expressions through `SQLiteSelectSql` JSON aggregate specs and
evaluates them per source row inside `SQLiteGroupedAggregate` before
SQLite-style ordered DISTINCT admission.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateExpressionOrderCurrentSourceNext99Test.php`
- Result: `1 test files, 52 assertions, 0 failures`, with 43 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctFilterOrderCurrentSourceNext94Test.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctCurrentNext76Test.php lanes/libsqlite/tests/SQLiteJsonAggregateOrderDistinctCurrentSourceNext86Test.php lanes/libsqlite/tests/SQLiteJsonAggregateExpressionOrderCurrentSourceNext99Test.php`
- Result: `4 test files, 211 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-json-aggregate-expression-order-current-source-next99.php`
- Result: copied `wp_options` grouped JSON summaries were emitted with
  expression-ranked aggregate order.

Dashboard impact: `lane-status.json` `phpPass` moves from 38278 to 38321 by
the 43 newly verified PASS lines. Mapped upstream coverage remains `568 / 1589`
because this extends the already mapped JSON aggregate ORDER/FILTER/DISTINCT
family and does not claim fresh hydrated upstream execution.

Non-overlap: avoids accepted batch94 JSON aggregate DISTINCT/FILTER/ORDER
column behavior, jsonagg76 single expression-reject baseline, jsonagg86
single-term ASC/DESC column ordering, jsonagg90 window frames, jsonagg93 object
windows, JSON table cursor/source/constraint work, WAL, VFS, B-tree, encoding,
and suite evidence.

Dependency closure: no new support component is needed. The implementation
reuses `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteGroupedAggregate`,
the existing JSON/JSONB aggregate constructors, and lane-local copied
`wp_options` fixtures.
