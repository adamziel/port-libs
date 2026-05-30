# JSON Table Hidden Constraint Source Current Source Next102

This slice adds `SQLiteJsonTablePlan::hiddenConstraintSourceCurrentSourceNext102()` for JSON table scans whose hidden `json`/`root`/rowid constraints and visible residual constraints are sourced from the current and next host rows.

The new planner records source-column provenance, current-to-next constraint value transitions, runnable/unrunnable state for SQL NULL root paths, JSONB kind transitions, row materialization for the pinned current cursor, and next-reader policy. It avoids accepted hidden constraint extraction, JSON table SELECT source wiring, rowid alias normalization, cursor iteration, visible-column pushdown, and lateral host-join materializers.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableHiddenConstraintSourceCurrentSourceNext102Test.php
php -l lanes/libsqlite/examples/application-json-table-hidden-constraint-source-current-source-next102.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenConstraintSourceCurrentSourceNext102Test.php
# 1 test files, 52 assertions, 0 failures
php lanes/libsqlite/examples/application-json-table-hidden-constraint-source-current-source-next102.php --self-test
# application-json-table-hidden-constraint-source-current-source-next102 self-test passed
```

Dashboard delta: `phpPass` +52 from newly verified focused PASS lines. Mapped upstream coverage is unchanged because this composes already mapped JSON table planner/current-source primitives.

Dependency closure: no new support component is needed. The slice reuses native PHP JSON table planning, JSONB validation, JSON path handling, and the existing lane test runner.
