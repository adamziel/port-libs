# JSON Aggregate ORDER Window Current-Source Next88

Status: focused PHP behavior growth for parser-level `json_group_array()` and
`jsonb_group_array()` window aggregates with aggregate-local `DISTINCT` and
`ORDER BY` direction.

This slice wires `DISTINCT` parsing through `SQLiteSelectSql` window aggregate
expressions and applies distinct de-duplication after aggregate-local ordering
inside `SQLiteSelectQuery` JSON aggregate window frames. It also preserves
aggregate-local `ORDER BY ... DESC`, which was previously lost on the window
path.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateOrderWindowCurrentSourceNext88Test.php
# 1 test files, 41 assertions, 0 failures
# 15 PASS lines

php lanes/libsqlite/examples/application-json-aggregate-order-window-current-source-next88.php --self-test
# application-json-aggregate-order-window-current-source-next88 self-test passed
```

Non-overlap: avoids accepted JSON object aggregate/window coverage, JSON
aggregate FILTER/ORDER window next84, JSON aggregate DISTINCT non-window
next76/next86, JSON table cursor/source/constraint work, and VFS/WAL/B-tree/SQL
planner accepted clusters. The new surface is parser-level array JSON aggregate
window execution where `DISTINCT` and aggregate-local order direction compose
inside each current-source frame.

Dependency closure: no new support component is needed; this reuses the native
PHP SELECT SQL parser, JSON aggregate encoder, JSONB encoder, and row-array
window executor already present under `lanes/libsqlite/src`.
