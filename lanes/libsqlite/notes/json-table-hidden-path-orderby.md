# JSON Table Hidden Path ORDER BY Current Source Next128

This slice adds `SQLiteJsonTablePlan::currentSourceHiddenPathOrderBy()`
for current/next `json_each()` / `json_tree()` plans that combine hidden path,
rowid aliases, and ORDER BY coverage. It reuses the accepted path constraint,
hidden rowid cost, and partial ORDER BY profiles, then records the combined
path/order prefix, remaining sort suffix, ordered rowid tape, effective cost,
and replan reasons when the next JSON source changes output order.

WordPress smoke:

- `lanes/libsqlite/examples/wordpress-json-table-hidden-path-orderby.php`
  models copied `wp_options` plugin settings where an import adds a rule under
  the same `$.rules` root. The plan keeps hidden `path`/`rowid` constraints but
  detects that `ORDER BY path, atom DESC` requires a new ordered rowid tape.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableHiddenPathOrderByTest.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-hidden-path-orderby.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenPathOrderByTest.php`
- `php lanes/libsqlite/examples/wordpress-json-table-hidden-path-orderby.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This does not repeat accepted JSON table source/cursor execution, hidden
constraint extraction, visible constraint pushdown, path hidden rowid cost, or
standalone partial ORDER BY cost work. The new behavior is the combined hidden
path plus ORDER BY current/next planner state.

Dependency closure:

No new support component is needed. The slice reuses native PHP JSON path,
JSON table, residual constraint, rowid alias, and ORDER BY planning helpers.
