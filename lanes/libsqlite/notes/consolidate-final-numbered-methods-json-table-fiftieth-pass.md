# JSON Table Numbered Method Consolidation Fiftieth Pass

Consolidated five remaining numbered JSON-table generated hidden/path/rowid
production entry methods into stable descriptive names on
`SQLiteJsonTablePlan`:

- `currentSourceGeneratedHiddenResidualCost()`.
- `currentSourceGeneratedHiddenPath()`.
- `currentSourceGeneratedRowidOrder()`.
- `currentSourceRowidHiddenGenerated()`.
- `currentSourceGeneratedPathRowidCost()`.

The direct JSON-table tests and WordPress examples were renamed to unsuffixed
files and updated to assert descriptive replan-reason keys and dependency
markers. Later generated-path rowid cost tests that depend on this base
dependency marker were also migrated to the stable dependency name. Private
helper names in this subfamily were renamed away from worker-number suffixes.
The generated-path rowid constraint-cost helper was kept distinct from the
existing cursor-cost helper with descriptive helper names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenResidualCostTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenPathTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedRowidOrderTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenGeneratedTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostTest.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-rowid-hidden-generated.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext158Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext160Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidSeekCostCurrentSourceNext159Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-hidden-residual-cost.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-hidden-path.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-rowid-order.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenResidualCostTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenPathTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedRowidOrderTest.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenGeneratedTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostTest.php`
  - `5 test files, 285 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext158Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext160Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidSeekCostCurrentSourceNext159Test.php`
  - `3 test files, 175 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-rowid-hidden-generated.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-hidden-residual-cost.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-hidden-path.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-rowid-order.php --self-test`
- Exact removed JSON-table method/dependency scan across `src`, `tests`, and
  `examples`: no matches.
- Exact user-named `Next150` scan across `src`, `tests`, and `examples`: no
  matches.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this pass only renames
existing JSON-table planner entry points/helpers and keeps their native PHP
behavior covered by the migrated focused tests and examples.
