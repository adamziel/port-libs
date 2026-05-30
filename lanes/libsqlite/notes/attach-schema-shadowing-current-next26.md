# SQLite Attach Schema Shadowing Current Next26

## Behavior

- Added parser-level `SQLiteSelectSql` table-source resolution for schema-qualified table names such as `main.wp_options`, `temp.wp_options`, and `site.wp_options`.
- Unqualified table names now prefer `temp.<name>`, then `main.<name>`, then attached schema-qualified table keys in provided attach order, while legacy bare table arrays still work.
- Qualified table names pin the requested source and bypass TEMP shadowing for copied Application import/staging previews.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaShadowingCurrentNext26Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 45 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaShadowingCurrentNext26Test.php lanes/libsqlite/tests/SQLiteAttachSchemaShadowingCurrentNext16Test.php lanes/libsqlite/tests/SQLiteAttachTempSchemaCorpusTest.php`
  - `Focused test run: 3 selected test files (root lock skipped)`
  - `3 test files, 166 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php && php -l lanes/libsqlite/tests/SQLiteAttachSchemaShadowingCurrentNext26Test.php && php -l lanes/libsqlite/examples/application-select-sql-attach-shadowing.php`
  - no syntax errors
- `php lanes/libsqlite/examples/application-select-sql-attach-shadowing.php`
  - reports TEMP `wp_options` shadowing for unqualified SELECT, plus explicit main/attached schema results.
- `git diff --check -- lanes/libsqlite`
  - clean
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`

## Status Delta

- `phpPass`: `8739 -> 8784` (+45 focused PASS lines)
- `benchmarkDenominator.mapped`: unchanged (`461 / 1589`)

## Non-overlap

This slice avoids accepted batch23 ATTACH temp/VFS open planning, PRAGMA current-source rebasing, JSON table SELECT source/cursor work, SELECT JOIN/GROUP/ORDER-expression text dispatch, VFS writer/lock/sync work, WAL savepoint/checkpoint work, and B-tree page-move/freeblock/freelist clusters.

## Dependency Closure

No new support component is needed. The implementation reuses the existing bounded `SQLiteSelectSql` row-array executor and table-source planner.
