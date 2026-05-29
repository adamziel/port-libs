# SQLite JSON table generated path rowid cost current source next809-824

Extended the consolidated generated-path rowid cost current-source alias coverage through next809-824 without introducing a new source class.

- Source: `SQLiteJsonTablePlan.php`
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext809824Test.php`
- Boundary handoff: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext793808Test.php` now confirms next808 hands off to next809.

Validation slice:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext793808Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext809824Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext793808Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext809824Test.php`
- `git diff --check`
