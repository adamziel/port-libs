# source-neutral-src-database-keyvalue-api-dynamic-20260601T133542Z-0

## Scope

- Neutralized the dynamic JSON-path preflight example that exercises `SQLiteDatabase` key-value JSON expression lookup helpers.
- Replaced the remaining `wp_options` / `option_*` / `autoload` fixture schema with `app_settings` / `setting_id` / `key_name` / `key_value` / `load_policy`.
- Updated the row-object access from the removed `optionName` shape to `SQLiteKeyValueRow::$keyName`, so the example now returns a real setting match.
- Added the example to `SQLiteNoDomainSpecificApiTest.php` key-value fixture coverage.

## Verification

- `php lanes/libsqlite/examples/application-json-path-validation-preflight.php`: passed and returned `matches: ["module_empty_label_settings"]`.
- `php -l lanes/libsqlite/examples/application-json-path-validation-preflight.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php`: 2 files / 55 assertions / 0 failures.

## Dependency Closure

No new support component is needed. This is a source-neutral fixture/API cleanup over existing SQLite key-value JSON expression lookup behavior.

## Non-Overlap

This slice does not add upstream PASS rows or new SQLite behavior. It removes unguarded domain-shaped key-value fixture text from a dynamic `SQLiteDatabase` example and extends the existing source-neutral guard. Lane counters remain unchanged.
