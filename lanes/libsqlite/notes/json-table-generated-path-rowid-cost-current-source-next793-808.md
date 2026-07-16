# SQLite JSON table generated path rowid cost current source next793-808

Extended the consolidated generated-path rowid cost current-source alias coverage through next793-808 without introducing a new source class.

- Source: `SQLiteJsonTablePlan.php`
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext793808Test.php`
- Boundary handoff: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext777792Test.php` now confirms next792 hands off to next793.

Validation slice:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext777792Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext793808Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext777792Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext793808Test.php`
- `git diff --check`
