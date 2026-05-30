# JSON table constraint planner current-next72

## Behavior

Adds `SQLiteJsonTablePlan::constraintPlannerCurrentNext72()` for current/next
virtual-table planning. The helper compares the active `json_each()` /
`json_tree()` xBestIndex plan with the next statement's constraint set, keeps
the current reader policy explicit, and reports whether the next cursor must be
replanned.

This is intentionally distinct from accepted JSON hidden constraint extraction,
visible-column pushdown, JSON table cursors, parser-level JSON table FROM/JOIN
sources, and recursive JSON SELECT materialization. It only models the
current-to-next planner transition and the constraint/argument tapes that
decide cursor reuse versus reprepare.

## Evidence

Focused command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableConstraintPlannerCurrentNext72Test.php`

Expected focused delta: `1 test file / 72 assertions / 0 failures` when run
from this lane worktree.

Application smoke:

`php lanes/libsqlite/examples/application-json-table-constraint-planner-current-next72.php`

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP JSON table planner, path validation, and xBestIndex metadata.
