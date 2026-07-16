# JSON Aggregate ORDER DISTINCT Current Source Next86

Status: focused PHP behavior growth for parser-level
`json_group_array(DISTINCT value ORDER BY key ASC|DESC)` and
`jsonb_group_array(DISTINCT value ORDER BY key ASC|DESC)`.

This slice adds aggregate-local `ASC` / `DESC` direction parsing and execution
for JSON array aggregates. The aggregate input sort now runs in the requested
direction before DISTINCT admission, so duplicate values keep the first row in
the aggregate's sorted current-source order. Separate ASC and DESC summaries
also receive distinct hidden summary columns, allowing both to appear in the
same SELECT, HAVING, or ORDER BY plan.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateOrderDistinctCurrentSourceNext86Test.php`
- Result: `1 test files, 64 assertions, 0 failures`, with 51 PASS lines.
- `php lanes/libsqlite/examples/application-json-aggregate-order-distinct-current-source-next86.php`
- Result: copied `wp_options` grouped ASC/DESC distinct JSON summaries matched
  the expected current-source order.

Non-overlap: avoids accepted jsonagg75/76/81/82 coverage for aggregate ORDER
BY basics, DISTINCT basics, object/window DISTINCT ORDER BY, and grouped
HAVING/ORDER hidden JSON summaries. This patch is limited to aggregate-local
ORDER BY direction semantics and ASC/DESC summary identity for JSON array
aggregates; it does not touch JSON table cursor/source/constraint work, WAL,
VFS, B-tree, encoding, or suite evidence.

Dependency closure: no new support component is needed. The implementation
reuses the existing parser-level `SQLiteSelectSql` aggregate plan,
`SQLiteGroupedAggregate` summary executor, JSON/JSONB constructors, and focused
lane test harness.
