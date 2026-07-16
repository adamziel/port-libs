# JSON Table Path Constraint Pushdown Current Source Next123

Implemented a focused current-source planner profile for `json_tree()` / `json_each()` path constraints. The slice reuses existing native JSON table validation, visible constraint filtering, indexed constraint costing, and current/next source transition tracking, then records path-specific scan strategy, selected path signature, path tape, row counts, and replan reasons.

Focused behavior:

- `SQLiteJsonTablePlan::currentSourcePathConstraintPushdown()` wraps the accepted current-source indexed-constraint path and adds path-specific transition evidence.
- Covers `path =`, `path LIKE`, `path IN`, and `path BETWEEN` over copied `wp_options` JSON rule payloads.
- Detects row-count and path-tape changes when the next source adds a new rule path.
- Preserves SQL NULL/unrunnable handling and malformed root path rejection.
- Adds Application smoke `application-json-table-path-constraint.php`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTablePathConstraintPushdownTest.php`
  - `1 test files, 57 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableConstraintCostOrderCurrentSourceNext113Test.php lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCostTest.php lanes/libsqlite/tests/SQLiteJsonTablePathConstraintPushdownTest.php`
  - `3 test files, 167 assertions, 0 failures`

Non-overlap:

- Avoids accepted JSON visible constraint pushdown, hidden constraints, SELECT-source/cursor wiring, lateral hidden/current-source constraints, and indexed fullkey/id cost work. This slice is path-specific current-source transition profiling on top of accepted filtering.

Dependency closure:

- No new support component needed. Reuses native PHP JSON table planning, current-source validation, indexed visible constraint costs, and row-array filtering.
