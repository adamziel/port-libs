# JSON table constraint planner current-source next86

## Behavior

Adds `SQLiteJsonTablePlan::currentSourceConstraintPlannerNext86()` for the parser/planner handoff where a `json_each()` or `json_tree()` cursor is already reading one source row while the next source row is being prepared.

The planner pins the current source `json`/`root` values until cursor reset, prepares the next source constraint tape separately, reports source JSON/root/kind/validity transitions, preserves xBestIndex filter argument and usage transitions, and materializes current/next row previews from the corresponding source value. SQL NULL and malformed JSONB next sources become unrunnable and produce empty next-row tapes.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableConstraintPlannerCurrentSourceNext86Test.php`
- Result: `1 test files, 78 assertions, 0 failures`
- PASS lines: 78

## Application Smoke

- `php lanes/libsqlite/examples/application-json-table-current-source-next86.php`
- The smoke uses copied `wp_options` plugin settings and shows an active `json_tree()` cursor pinned to the current `option_value` while preparing the next `option_value` plan.

## Non-Overlap

This does not repeat accepted JSON hidden/visible constraint extraction, JSON table cursor behavior, parser-level JSON table SELECT sources, JSON host joins, lateral rowid planning, or optional JSONB CHECK/SQL NULL admission. It is scoped to current-source to next-source planner state for an already active JSON table cursor.

## Dependency Closure

No new support component is needed. The slice reuses the existing native JSON table planner, JSONB validator, JSON path resolver, and JSON table row materializers.
