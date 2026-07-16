# JSON table hidden constraint current-source next88

## Behavior

Adds `SQLiteJsonTablePlan::currentSourceHiddenConstraintPlannerNext88()` for the parser/planner handoff where a `json_each()` or `json_tree()` cursor is already pinned to one source row and duplicate hidden `json`/`root` constraints remain as residual planner evidence.

The next88 planner preserves the accepted next86 current-source behavior, then reports duplicate hidden residual constraints, current/next hidden residual tapes, row-count transitions, and next88 replan reasons. This covers the SQLite-style case where the first usable hidden argument drives the virtual-table cursor while later hidden constraints still affect current/next rowset viability.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenConstraintPlannerCurrentSourceNext88Test.php`
- Result: `1 test files, 50 assertions, 0 failures`
- PASS lines: 50

## Application Smoke

- `php lanes/libsqlite/examples/application-json-table-hidden-constraint-current-source-next88.php`
- The smoke uses copied `wp_options` plugin rule JSON and shows duplicate hidden root constraints preserved as residual planner evidence while the active cursor remains pinned to the current option row and the next source row grows.

## Non-Overlap

This does not repeat accepted next86 source JSON/root transitions, JSON hidden-constraint extraction, visible-column pushdown, parser-level JSON table SELECT sources, JSON table cursor behavior, lateral rowid planning, or JSON NULL/JSONB admission. It is scoped to duplicate hidden residual constraints across current-source and next-source planner state.

## Dependency Closure

No new support component is needed. The slice reuses the existing native JSON table planner, JSON path validator, JSONB validator, and JSON table row materializers.
