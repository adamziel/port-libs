## Consolidation

- Verified the exact `CurrentSourceNext15[0]` / `CurrentNext15[0]` production/test/example suffix is absent.
- Renamed three public JSON generated-path rowid production entrypoints from numbered wrappers to stable descriptive methods:
  - `generatedPathRowidAliasPlan()`
  - `generatedPathRowidXFilterProgramPlan()`
  - `generatedPathRowidMaterializationPlan()`
- Migrated direct tests and WordPress examples to unsuffixed file names and method calls.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasPlanTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXFilterProgramPlanTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidMaterializationPlanTest.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-xfilter-program.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-materialization.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasPlanTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXFilterProgramPlanTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidMaterializationPlanTest.php` -> `3 test files, 181 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-xfilter-program.php --self-test` -> `wordpress-json-table-generated-path-rowid-xfilter-program self-test passed`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-materialization.php --self-test` -> `wordpress-json-table-generated-path-rowid-materialization self-test passed`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed; this is a production suffix consolidation only.
