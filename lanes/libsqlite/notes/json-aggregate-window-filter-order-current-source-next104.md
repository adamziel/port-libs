# JSON aggregate window filter/order current-source next104

Status: focused PHP behavior growth for parser-level JSON aggregate windows
where no aggregate-local `ORDER BY` is present. `json_group_array()`,
`jsonb_group_array()`, and `json_group_object()` now preserve the window
current-source iteration order instead of re-sorting filtered frame rows by
the peer key ascending.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowFilterOrderCurrentSourceNext104Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 52 PASS lines
# 1 test files, 60 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteJsonAggregateWindowFilterOrderCurrentSourceNext104Test.php
php -l lanes/libsqlite/examples/application-json-aggregate-window-filter-order-current-source-next104.php
# No syntax errors detected

php lanes/libsqlite/examples/application-json-aggregate-window-filter-order-current-source-next104.php --self-test
# application-json-aggregate-window-filter-order-current-source-next104 self-test passed
```

Expected dashboard movement: `phpPass` +52, from `40110` to `40162`.
Mapped coverage is unchanged; this is a focused behavior refinement inside
the already mapped JSON aggregate/window family.

Non-overlap: avoids accepted JSON aggregate expression ORDER BY, DISTINCT
ORDER BY windows, object aggregate windows, default-window frame coverage,
JSON table cursor/source/hidden/visible constraints, WAL/VFS/B-tree/encoding,
and suite evidence clusters. The new surface is current-source ordering for
filtered JSON aggregate window frames when aggregate-local order is absent.

Dependency closure: no new support component is needed. The patch reuses the
existing parser-level `SQLiteSelectSql`, `SQLiteSelectQuery`, JSON aggregate,
JSONB, and Application row-array test infrastructure.
