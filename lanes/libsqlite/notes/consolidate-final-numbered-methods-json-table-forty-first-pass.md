# JSON table numbered method consolidation forty-first pass

Consolidated three remaining generated-path rowid JSON table production entry points into stable descriptive methods on `SQLiteJsonTablePlan`:

- `currentSourceGeneratedPathRowidCostBestIndex()`
- `currentSourceGeneratedPathRowidYieldPlan()`
- `currentSourceGeneratedPathRowidSourceFence()`

The behavioral array keys, dependency receipt strings, and direct test/example scenarios remain unchanged so existing accepted coverage keeps proving the same next163, next166, and next172 planner states without exposing numbered production method names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext163Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext166Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext172Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next163.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next166.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next172.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext163Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext166Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext172Test.php` -> `3 test files, 171 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next163.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next166.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next172.php --self-test`

Dependency closure: no new support component is needed; this only renames existing JSON table planner entry points and reuses the accepted generated-path rowid cost-source, best-index, yield, and source-fence helpers.

Non-overlap: this is consolidation-only and does not add new JSON table behavior, phpPass, mapped coverage, or WordPress scenarios. It avoids accepted JSON visible/hidden constraint pushdown, JSON table cursor/source wiring, WAL/VFS, B-tree, planner, trigger, PRAGMA, and suite evidence clusters.
