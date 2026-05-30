# JSON aggregate filter window frame current-source next117

Status: focused parser-level JSON aggregate window behavior growth.

This slice admits SQLite single-bound window frame syntax and explicit
`UNBOUNDED` bounds for JSON aggregate windows, including `FILTER` composition:
`ROWS CURRENT ROW`, `ROWS N PRECEDING`, `RANGE CURRENT ROW`,
`GROUPS CURRENT ROW`, `ROWS UNBOUNDED PRECEDING`, and
`BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonAggregateFilterWindowFrameCurrentSourceNext117Test.php`
- `php -l lanes/libsqlite/examples/application-json-aggregate-filter-window-frame-current-source-next117.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateFilterWindowFrameCurrentSourceNext117Test.php`
- `php lanes/libsqlite/examples/application-json-aggregate-filter-window-frame-current-source-next117.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The patch reuses the
existing native PHP SELECT SQL parser, JSON aggregate/window executor, JSONB
encoder, predicate filter, and Application smoke harness.

Non-overlap: avoids accepted JSON aggregate FILTER/ORDER/DISTINCT window
coverage, JSON object aggregate/window coverage, JSON table
source/cursor/hidden/visible constraint work, and accepted WAL/B-tree/VFS/SQL
clusters. The narrower surface is parser admission and execution of SQLite
single-bound and explicit-unbounded window frame source syntax for JSON
aggregate FILTER windows.
