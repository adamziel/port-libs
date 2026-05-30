# JSON table lateral hidden planner current-source next90

## Behavior

Adds `SQLiteJsonTablePlan::lateralHiddenPlannerCurrentSourceNext90()` for pinned host-row JSON table scans where each lateral `json_each()` / `json_tree()` cursor has current and next source rows, duplicate hidden constraints, visible pushdown constraints, and LEFT JOIN null-extension state.

The planner composes accepted next88 single-source hidden planner evidence across host rows, but does not treat stable duplicate hidden residual presence as a global replan by itself. It reports per-host row counts, null-extension transitions, added/removed host rows, JSON/JSONB kind changes, root changes, hidden residual columns, and current/next reader policies.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralHiddenPlannerCurrentSourceNext90Test.php`
- Result: `1 test files, 60 assertions, 0 failures`
- PASS lines: 60

## Application Smoke

- `php lanes/libsqlite/examples/application-json-table-lateral-hidden-planner-current-source-next90.php`
- The smoke uses copied `wp_options` plugin settings rows and reports current/next lateral JSON rule scans, row-count growth, LEFT JOIN null-extension removal, added host rows, and dependency markers without requiring `ext/sqlite`.

## Non-Overlap

This does not repeat accepted parser-level JSON table SELECT sources, `SQLiteJsonTableCursor`, JSON visible/hidden constraint extraction, next88 single-source hidden residual planning, next85 lateral rowid SQL execution, JSON table host joins, JSON table left-join rowid behavior, JSON NULL path, or JSONB CHECK admission. It is scoped to multi-host lateral current-source planner transitions that combine hidden residuals with LEFT JOIN null extension and source-tape stability.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON table planner, JSON path validation, JSONB decoding/validation, and row materialization helpers.
