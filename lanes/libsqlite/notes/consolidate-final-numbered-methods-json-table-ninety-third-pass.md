# JSON Table Numbered Method Consolidation Ninety-Third Pass

Consolidated the private JSON-table hidden-rowid source helper cluster on
`SQLiteJsonTablePlan` by removing the `94` suffix from internal helper method
names:

- `hiddenRowidResidualConstraints()`
- `sourceRowidResidualConstraints()`
- `rowidsFromRows()`
- `sourceRowidSummary()`
- `sourceRowTransitions()`
- `sourceRowTransitionReason()`

Observable receipt keys, action labels, replan reasons, reader policies, and
dependency strings are preserved. The pass also restored legacy dependency
aliases that affected JSON-table domain tests still assert:

- `sqlite-json-table-hidden-constraint-planner-current-source-next88`
- `sqlite-json-table-hidden-rowid-source-current-next94`
- `sqlite-json-table-rowid-hidden-constraint-current-source-next99`
- `sqlite-json-table-generated-hidden-rowid-cost-current-source-next142`

The direct `next219` generated-path rowid limit-admission test and WordPress
smoke were migrated to the existing canonical
`currentSourceGeneratedPathRowidCurrentSourceLimitAdmission()` entry point so
their expected `next219` observable keys remain produced by the canonical
implementation.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext219Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next219.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidPlannerTest.php`
  - `1 test files, 55 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext219Test.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidPlannerTest.php`
  - `2 test files, 107 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedRowidOrderTest.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidOrderTest.php lanes/libsqlite/tests/SQLiteJsonTableLateralHiddenConstraintCurrentSourceNext103Test.php lanes/libsqlite/tests/SQLiteJsonTableLateralHiddenPlannerTest.php lanes/libsqlite/tests/SQLiteJsonTableLateralRowidHiddenCurrentSourceNext105Test.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenGeneratedTest.php`
  - `6 test files, 369 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTable*Test.php`
  - `305 test files, 20187 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next219.php --self-test`
  - `wordpress-json-table-generated-path-rowid-cost-current-source-next219 self-test passed`
- `php lanes/libsqlite/examples/wordpress-json-table-hidden-rowid-planner.php --self-test`
  - exited 0 with expected JSON payload
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses existing
JSON-table planner, rowid-source, generated-hidden-rowid, and lateral planner
metadata paths.

Non-overlap: this is consolidation-only. It does not add new JSON-table
behavior and avoids accepted JSON table cursor/source/hidden/visible
constraint and ranked planner behavior.
