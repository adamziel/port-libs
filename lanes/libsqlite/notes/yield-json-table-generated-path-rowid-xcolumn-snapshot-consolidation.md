# JSON table generated-path rowid xColumn snapshot consolidation

## Summary

- Consolidated the numbered production entry point `currentSourceGeneratedPathRowidCostCurrentSourceNext181()` into the stable `currentSourceGeneratedPathRowidXColumnSnapshotPlan()`.
- Renamed the direct test and WordPress smoke from numbered `CurrentSourceNext181` / `current-source-next181` names to descriptive xColumn snapshot names.
- Removed numbered suffixes from the direct private helper methods and the direct plan keys, dependency marker, reader policies, cost classes, and replan reasons for the xColumn snapshot layer.
- Kept downstream `next184` / `next187` / `next190` behavior intact by updating their direct references to the stable xColumn snapshot plan keys.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXColumnSnapshotTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext184Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-xcolumn-snapshot.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXColumnSnapshotTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext184Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext187Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext190Test.php`
  - `4 test files, 230 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-xcolumn-snapshot.php --self-test`
  - `wordpress-json-table-generated-path-rowid-xcolumn-snapshot self-test passed`

## Dependency Closure

No new support component is needed. This consolidation reuses the existing native JSON table row materialization, generated-path rowid cache/yield profiles, and focused TestRunner coverage.
