# JSON table generated-path rowid cost current-source next937-952

Status: focused PHP behavior growth for `json-table-generated-path-rowid-cost-current-source-next937-952`.

This slice extends the consolidated `SQLiteJsonTablePlan` generated-path rowid current-source cost-selection alias helper through next952. It continues the next921-936 pattern without adding a duplicate support class.

Application smoke: `application-json-table-generated-path-rowid-cost-current-source-next937-952.php` models copied `wp_options` JSON rule scans where a stable current generated-path rowid point remains costed at `1`, while changed next-source JSON/path generation forces the next reader through reprepare.

Validation:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext937952Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next937-952.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext921936Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext937952Test.php`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next937-952.php --self-test`

Dependency closure: no new support component needed; next937-952 reuses current-source generated-path rowid yield guard and cost selection aliases.
