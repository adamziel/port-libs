# yield-sqlite-select-json-table-recursive-window-current-next54

## Scope

- Added focused parser-level SELECT coverage for recursive CTE rows sourced from `json_tree()` / `json_each()` over copied `wp_options` text JSON and JSONB option values, then consumed by current/next window functions.
- Added the Application smoke `application-select-json-recursive-window-current-next54.php` for navigation import previews using `lead()` and `last_value()` over recursive JSON rows.

## Focused Evidence

- Red-first focused run initially exposed failures in the new expectations before tightening the test routing/expected rowsets.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectJsonRecursiveWindowCurrentNext54Test.php`
  - `1 test files, 80 assertions, 0 failures`
  - `56` PASS lines
- `php lanes/libsqlite/examples/application-select-json-recursive-window-current-next54.php --self-test`
  - `application-select-json-recursive-window-current-next54 self-test passed`

## Non-Overlap

This slice avoids accepted JSON table cursor/source wiring, hidden/visible constraint pushdown, JSON host joins, recursive lateral JSON materialization batch49, SELECT SQL expression `ORDER BY`, GROUP BY/HAVING text, subqueries, B-tree page moves/root collapse/overflow freelist, WAL checkpoint/savepoint/rollback apply, VFS writer/lock/sync clusters, and Unicode GLOB work. The added behavior is the combined SELECT execution path where recursive JSON table rows feed current/next window value functions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `SQLiteSelectSql`, `SQLiteJsonTablePlan`, `SQLiteJsonB`, and the existing window executor; no ext/sqlite or external service is required.
