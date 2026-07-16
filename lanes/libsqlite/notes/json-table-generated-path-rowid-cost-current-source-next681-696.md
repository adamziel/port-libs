# SQLite JSON table generated path rowid cost current-source next681-696

This slice extends the existing generated-path rowid current-source cost-selection aliases from next680 through next696. It keeps the consolidated factory behavior in `SQLiteJsonTablePlan`, adds the direct next681-696 focused range test, and updates the next665-680 boundary to hand off to next681.

Validation:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext665680Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext681696Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next681-696.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext665680Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext681696Test.php`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next681-696.php --self-test`
- `git diff --check`
