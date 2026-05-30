# JSON Table Lateral Planner Constraint Current-Next75

This slice adds `SQLiteJsonTablePlan::lateralConstraintPlannerCurrentNext75()`, a planner-only bridge for lateral `json_each()` / `json_tree()` scans where each host row supplies the hidden `json` argument and, optionally, the hidden `root` argument.

The focused behavior is intentionally narrower than accepted JSON table cursor/source work: it does not add another row materializer. It records per-host xBestIndex tapes, validates SQL NULL and malformed JSONB as unrunnable plans, and reports current-to-next host-row transition reasons so a prepared statement can keep the current cursor stable until the host row advances.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralPlannerConstraintCurrentNext75Test.php`
- `php lanes/libsqlite/examples/application-json-table-lateral-planner-current-next75.php`

Dependency closure: no new support component is needed. The slice reuses the existing native PHP JSON text/JSONB decoders, JSON path validator, and JSON table xBestIndex planner.
