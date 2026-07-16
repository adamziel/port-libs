# JSON Aggregate Window FILTER ORDER Current Source Next109

Status: focused current-source JSON aggregate window behavior growth for
aggregate-local `ORDER BY ... NULLS FIRST/LAST` terms combined with `FILTER`.

This slice extends parser-level JSON aggregate window execution so
`json_group_array()`, `jsonb_group_array()`, and `json_group_object()` carry
aggregate-local NULL placement metadata into the window frame comparator. It
matches SQLite ordering-term behavior for Application option summaries where
NULL priority metadata must sort explicitly before or after numeric scores
while the window frame still advances over the current source row.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowFilterOrderCurrentSourceNext109Test.php`
  -> `1 test files, 47 assertions, 0 failures` with 41 PASS lines.
- `php lanes/libsqlite/examples/application-json-aggregate-window-filter-order-current-source-next109.php --self-test`
  -> `application-json-aggregate-window-filter-order-current-source-next109 self-test passed`.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  -> no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`
  -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteJsonAggregateWindowFilterOrderCurrentSourceNext109Test.php`
  -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-json-aggregate-window-filter-order-current-source-next109.php`
  -> no syntax errors.
- `git diff --check -- lanes/libsqlite`
  -> passed.

Dashboard delta: `phpPass` moves from 42491 to 42532 (+41 focused PASS lines)
after verification; no mapped upstream denominator row is claimed.

Non-overlap: avoids accepted batch104/105 JSON aggregate window FILTER/ORDER,
JSONB DISTINCT, JSON table cursor/source/constraint work, and WAL/VFS/B-tree/
encoding/planner clusters. The new behavior is the narrower current-source
aggregate-local NULL placement ordering term inside JSON aggregate window
frames with FILTER.

Dependency closure: no new support component is needed. The patch reuses the
existing native PHP SELECT parser/executor, JSON aggregate, JSONB, and window
frame helpers.
