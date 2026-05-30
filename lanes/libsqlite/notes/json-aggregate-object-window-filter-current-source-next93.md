# JSON aggregate object window filter current-source next93

Status: focused PHP behavior growth for parser-level `json_group_object()` and
`jsonb_group_object()` window aggregates with `FILTER` over current-source
frames.

This slice adds SELECT SQL dispatch for JSON object aggregate window frames.
The executor now accepts `json_group_object(label, value ORDER BY key) FILTER
(WHERE predicate) OVER (...)`, evaluates the filter per current source row
before frame aggregation, preserves aggregate-local ordering, supports
`DISTINCT` over label/value pairs, and returns JSONB blobs for
`jsonb_group_object()`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateObjectWindowFilterCurrentSourceNext93Test.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonAggregateObjectWindowFilterCurrentSourceNext93Test.php`
- `php -l lanes/libsqlite/examples/application-json-aggregate-object-window-filter-current-source-next93.php`
- `php lanes/libsqlite/examples/application-json-aggregate-object-window-filter-current-source-next93.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted JSON array aggregate FILTER/ORDER window slices,
accepted JSON array DISTINCT/ORDER window slices, accepted standalone JSON
object aggregate/window helpers, JSON table cursor/source/constraint work,
VFS/WAL/B-tree/storage clusters, expression ORDER BY, grouped SELECT text, and
suite evidence handoffs. The new surface is parser-level JSON object aggregate
window execution with current-source `FILTER` predicates.

Dependency closure: no new support component is needed. The patch reuses the
native PHP SELECT SQL parser/executor, JSON aggregate encoder, JSONB encoder,
JSON subtype handling, and existing frame/filter primitives.
