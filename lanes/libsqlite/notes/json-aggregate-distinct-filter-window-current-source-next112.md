# JSON aggregate DISTINCT/FILTER window current-source next112

Status: focused PHP behavior growth for `json_group_object()` and
`jsonb_group_object()` window frames with DISTINCT and FILTER composition.

This slice adds the missing direct current-source helper for
`json_group_object(DISTINCT label, value) FILTER (...) OVER (...)` without an
aggregate-local ORDER BY. The helper applies the window frame first, honors
`EXCLUDE`, applies FILTER before DISTINCT admission, de-duplicates exact
label/value pairs, and dispatches JSONB output through the same SQL-function
wrapper as existing object aggregate windows.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctFilterWindowCurrentSourceNext112Test.php`
- Result: `1 test files, 47 assertions, 0 failures` with 41 PASS lines.

Example smoke:

- `php lanes/libsqlite/examples/application-json-object-distinct-filter-window-current-source-next112.php`
- Result: JSON summary printed after self-checking text JSON and JSONB frames.

Non-overlap: avoids accepted JSON array DISTINCT/ORDER/window and JSONB
payload work, accepted JSON object DISTINCT ORDER BY window work, JSON table
cursor/source/constraint work, and current WAL/B-tree/VFS/encoding batches.
This is limited to object aggregate DISTINCT+FILTER window frames with no
aggregate-local ORDER BY.

Dependency closure: no new support component is needed. The patch reuses
lane-local JSON constructors, JSONB encoding, and window frame primitives.
