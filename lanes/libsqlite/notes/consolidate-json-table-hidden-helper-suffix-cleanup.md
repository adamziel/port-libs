# JSON Table Hidden Helper Suffix Cleanup

Consolidated the JSON table hidden-constraint/current-source helper chain in
`SQLiteJsonTablePlan` by renaming the remaining public `Next88`, `Next94`,
`Next99`, `Next140`, `Next142`, `Next143`, `Next148`, and `Next157` entry
points to stable descriptive names. Direct focused tests and Application examples
were renamed to the same unsuffixed surface.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l` for the eight renamed focused test files and eight renamed examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenConstraintPlannerTest.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidPlannerTest.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenConstraintPlannerTest.php lanes/libsqlite/tests/SQLiteJsonTableHiddenPathRowidTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenRowidCostTest.php lanes/libsqlite/tests/SQLiteJsonTableHiddenPathGeneratedTest.php lanes/libsqlite/tests/SQLiteJsonTableHiddenGeneratedCostTest.php lanes/libsqlite/tests/SQLiteJsonTableHiddenGeneratedRowidTest.php` -> `8 test files, 482 assertions, 0 failures`
- Renamed Application examples executed with `php <example>` for all eight files.

Dependency closure: no new support component is needed; this is a production
suffix consolidation over existing JSON table planner behavior.
