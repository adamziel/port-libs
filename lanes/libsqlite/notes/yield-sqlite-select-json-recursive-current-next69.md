# select-json-recursive-current-next69

## Behavior

- Adds `SQLiteSelectRecursiveJsonMaterialization::recursiveJsonYieldTape()`.
- Projects each recursive queue current row into a current/next tape keyed by selected recursive columns.
- Carries projected JSON rows for the current and next recursive rows, accepted frontier rows, duplicate skips, transition labels, emitted status, generated count, accepted-next count, and queue-after count.
- Covers a Application-style `wp_options` recursive navigation graph with text JSON and JSONB option payloads, `json_each()` edge traversal, `json_tree()` rule materialization, duplicate UNION cycle evidence, and derived SELECT filtering/grouping over the materialized source.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectJsonRecursiveCurrentNext69Test.php`
  - `1 test files, 73 assertions, 0 failures`
  - 72 focused PASS lines.
- `php lanes/libsqlite/examples/application-select-json-recursive-current-next69.php`
  - Prints `materializedRows: 54`, `yieldTapeRows: 9`, first current/next keys, JSONB next atoms, and the skipped UNION duplicate cycle.

## Non-overlap

This does not repeat accepted parser-level JSON table SELECT source/cursor/hidden/visible constraint work, recursive JSON window materialization, grouped SELECT text, SQL expression ORDER BY, or WAL/B-tree/VFS clusters. The new surface is a recursive JSON current/next yield tape over already materialized SELECT output.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON, JSONB, JSON table, recursive CTE, and SELECT execution helpers.
