# source-neutral-src-database-keyvalue-api-dynamic-20260601T190430Z-0

## Scope

- Neutralized directly coupled `SQLiteDatabase` key-value example fixtures that still built `wp_options` / `option_*` / `autoload` schemas.
- Renamed generated insert example entrypoints to setting language:
  - `application-generated-setting-insert-plan.php`
  - `application-indexed-generated-setting-insert-plan.php`
  - `application-automatic-indexed-generated-setting-insert-plan.php`
  - `application-partial-indexed-generated-setting-insert-plan.php`
- Updated `application-table-leaf-page-assembly.php` and `application-json-operator-minmax-rhs.php` to use `app_settings`, `setting_id`, `key_name`, `key_value`, and `load_policy`.
- Fixed the JSON operator min/max example to read `SQLiteKeyValueRow::$keyName` instead of the removed domain-shaped row property.
- Extended `SQLiteNoDomainSpecificApiTest.php` so these direct fixtures are covered by the key-value neutral fixture guard.

## Verification

- `php -l` changed PHP files: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php`: passed, 2 files / 16 assertions / 0 failures.
- Example smokes passed:
  - `php lanes/libsqlite/examples/application-generated-setting-insert-plan.php`
  - `php lanes/libsqlite/examples/application-indexed-generated-setting-insert-plan.php`
  - `php lanes/libsqlite/examples/application-automatic-indexed-generated-setting-insert-plan.php`
  - `php lanes/libsqlite/examples/application-partial-indexed-generated-setting-insert-plan.php`
  - `php lanes/libsqlite/examples/application-table-leaf-page-assembly.php`
  - `php lanes/libsqlite/examples/application-json-operator-minmax-rhs.php`
- `git diff --check -- lanes/libsqlite`: passed.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`: passed.

## Dependency Closure

No new support component is needed. This reuses native `SQLiteDatabase` b-tree page assembly, key-value row insert planning, expression-index lookup, and `SQLiteKeyValueRow` serialization with generic application settings identifiers.

## Non-Overlap

This slice does not add upstream PASS rows or change lane counters. It removes directly coupled key-value fixture/API domain debt left outside the already-neutralized production `SQLiteDatabase` and `SQLiteKeyValueRow*` source surface.
