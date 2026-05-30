# SQLite JSON Table Constraint Planner Current/Next70

## Behavior

- Adds `SQLiteJsonTablePlan::currentNextConstraintPlan()` for JSON table-valued cursor planning.
- The helper preserves the existing xBestIndex-style constraint argument tape, then materializes current/next row adjacency after hidden and visible constraints, residual filtering, ordering, and LIMIT/OFFSET.
- SQL NULL and superficially malformed JSONB hidden `json` inputs produce empty row-pair plans, matching the accepted JSON table cursor/source behavior instead of throwing during row materialization.

## Evidence

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableConstraintPlannerCurrentNext70Test.php`
- Result: `1 test files, 53 assertions, 0 failures`
- New focused PASS-line delta: `+53`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-current-next70.php`

## Non-Overlap

This slice avoids accepted JSONB CHECK optional-path/SQL NULL admission, parser-level JSON table SELECT source wiring, `SQLiteJsonTableCursor`, hidden/visible constraint extraction, duplicate hidden constraints, JSON table host joins, LIMIT/OFFSET pushdown, and recursive JSON SELECT materialization. It only adds the missing current/next cursor-row plan after already selected constraints are applied.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP JSON, JSONB, JSON path, and JSON table-valued helpers.
