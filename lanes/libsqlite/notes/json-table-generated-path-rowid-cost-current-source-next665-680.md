# SQLite JSON table generated path rowid cost current-source next665-680

This slice extends the existing generated-path rowid current-source cost-selection aliases from next664 through next680. It keeps the consolidated factory behavior in `SQLiteJsonTablePlan`, adds the direct next665-680 focused range test, and updates the next649-664 boundary to hand off to next665.

Validation:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext649664Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext665680Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next665-680.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext649664Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext665680Test.php`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next665-680.php --self-test`
- `git diff --check`
