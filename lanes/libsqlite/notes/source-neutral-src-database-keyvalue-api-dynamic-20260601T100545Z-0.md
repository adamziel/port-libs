# Source-neutral database key-value API dynamic cleanup

Slice: `source-neutral-src-database-keyvalue-api-dynamic-20260601T100545Z-0`

Scope:
- Neutralized directly coupled key-value example fixtures that still built `wp_options`-shaped schemas.
- `application-composite-indexed-generated-option-insert-plan.php` now uses `app_settings`, `setting_id`, `key_name`, `key_value`, and `load_policy`.
- `application-index-split-option-replacement-plan.php` now uses the same generic schema and output labels.
- Extended `SQLiteNoDomainSpecificApiTest.php` to include both fixtures in the key-value neutral fixture guard.

Verification:
- `php -l lanes/libsqlite/examples/application-composite-indexed-generated-option-insert-plan.php`
- `php -l lanes/libsqlite/examples/application-index-split-option-replacement-plan.php`
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `php lanes/libsqlite/examples/application-composite-indexed-generated-option-insert-plan.php`
- `php lanes/libsqlite/examples/application-index-split-option-replacement-plan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 5 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php` -> `1 test files, 30 assertions, 0 failures`

Dependency closure:
- No new support component is needed. This reuses the existing `SQLiteDatabase` key-value insert/replace APIs and `SQLiteKeyValueRow` result mapping.

Next:
- Continue bounded cleanup of remaining directly coupled key-value examples and notes that still carry legacy setting-table names.
