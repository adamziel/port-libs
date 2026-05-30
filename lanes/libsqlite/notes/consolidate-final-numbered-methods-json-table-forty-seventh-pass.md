# JSON Table Numbered Method Consolidation Forty-Seventh Pass

Consolidated four remaining JSON table generated-path rowid alias production
entry points on `SQLiteJsonTablePlan` into stable descriptive names:

- `currentSourceGeneratedPathRowidAliasProjection()`
- `currentSourceGeneratedPathRowidAliasReverseOrder()`
- `currentSourceGeneratedPathRowidAliasOrderConsumption()`
- `currentSourceGeneratedPathRowidAliasLimit()`

The direct focused tests and Application examples were renamed to unsuffixed
filenames and now call the stable entry points. Existing legacy payload keys,
dependency receipts, and opcode strings remain unchanged so the migrated tests
continue covering the same accepted planner states.

During migration, the direct tests were updated to assert the current canonical
rowid batch behavior produced by this base, replacing stale single-checkpoint
expectations in the old numbered test files.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l` on the four migrated focused tests and four migrated Application
  examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasProjectionTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasReverseOrderTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasOrderConsumptionTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasLimitTest.php`
  - `4 test files, 219 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-alias-projection.php --self-test`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-alias-reverse-order.php --self-test`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-alias-order-consumption.php --self-test`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-alias-limit.php --self-test`

Dependency closure: no new support component is needed. This pass only removes
numbered production entry points and direct numbered test/example surfaces while
reusing the existing native JSON table planner implementation.
