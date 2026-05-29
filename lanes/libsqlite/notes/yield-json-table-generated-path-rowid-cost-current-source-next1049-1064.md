# JSON table generated path rowid cost current-source next1049-1064

Implemented the next1049-1064 follow-on as additive aliases over the consolidated
`SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext236()`
cost-selection helper.

- Extends the accepted generated-path rowid current-source alias guard through
  next1064 without adding a duplicate numbered class.
- Adds focused batch tests for next1049 through next1064 dependency labels,
  reader policies, aliased selection keys, source-change replan reasons, pinned
  rowid point cost, and stable current-source reuse.
- Adds a WordPress self-test example for copied `wp_options` JSON rule scans
  that reprepare when the next source changes.

Validation:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext10331048Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext10491064Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next1033-1048.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next1049-1064.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext10331048Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext10491064Test.php`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next1033-1048.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next1049-1064.php --self-test`
- `git diff --check`
