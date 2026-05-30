# JSON Aggregate DISTINCT FILTER ORDER Current Source Next94

Status: focused parser-level PHP behavior growth for non-window
`json_group_array()` / `jsonb_group_array()` aggregates that combine
`DISTINCT`, `FILTER`, and multi-term aggregate-local `ORDER BY`.

The previous grouped/implicit aggregate path parsed only one aggregate
`ORDER BY` term. SQL such as
`json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC)
FILTER (WHERE enabled)` failed before execution, while window aggregates
already had a multi-term path. This slice carries aggregate order terms through
`SQLiteSelectSql` and sorts grouped JSON aggregate input by each term before
SQLite-style DISTINCT admission.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctFilterOrderCurrentSourceNext94Test.php`
- Result: `1 test files, 58 assertions, 0 failures`, with 46 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateOrderDistinctCurrentSourceNext86Test.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctOrderWindowCurrentSourceNext90Test.php lanes/libsqlite/tests/SQLiteJsonAggregateFilterOrderCurrentNext73Test.php`
- Result: `3 test files, 159 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-json-aggregate-distinct-filter-order-current-source-next94.php`
- Result: copied `wp_options` grouped summaries matched the expected
  multi-term current-source aggregate order.

Dashboard impact: `lane-status.json` `phpPass` moves from 36393 to 36439 by
the 46 newly verified PASS lines. Mapped upstream coverage remains `534 / 1589`
because this extends the already mapped JSON aggregate family and does not
claim fresh hydrated upstream execution.

Non-overlap: avoids accepted jsonagg73 filter/order basics, jsonagg86
single-term ASC/DESC DISTINCT array behavior, jsonagg90 multi-term window
frames, batch90 JSON aggregate DISTINCT multi-term ORDER window behavior,
JSON table cursor/source/constraint work, WAL, VFS, B-tree, encoding, and
suite evidence.

Dependency closure: no new support component is needed. The implementation
reuses `SQLiteSelectSql`, `SQLiteGroupedAggregate`, the existing JSON/JSONB
aggregate constructors, and lane-local Application copied `wp_options` fixtures.
