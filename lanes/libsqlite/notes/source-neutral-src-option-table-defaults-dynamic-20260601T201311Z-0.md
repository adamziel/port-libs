# source-neutral-src-option-table-defaults-dynamic-20260601T201311Z-0

Status: ready for integration.

Source-neutral cleanup:

- Neutralized JSON schema import production defaults in `SQLiteImportJsonSchemaSavepointPlan`, `SQLiteSchemaJsonSavepointWalPlan`, and `SQLiteTenantJsonWalImportPlan`.
- Replaced default JSON-validation key patterns from plugin/theme/widget-shaped names to generic `module_`, `_settings`, and `component_` application setting keys.
- Updated directly coupled tests and examples to use `setting_id`, `key_name`, `key_value`, tenant/global setting rows, and module/component sample keys while preserving JSON validation, savepoint rollback, WAL frame, conflict, and release assertions.
- Extended `SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php` with a dynamic source scan for this JSON schema import default group and public behavior assertions for the new generic defaults.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteImportJsonSchemaSavepointTest.php lanes/libsqlite/tests/SQLiteApplicationSchemaJsonSavepointWalTest.php lanes/libsqlite/tests/SQLiteApplicationTenantJsonWalImportCurrentNext54Test.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php`
  - `4 test files, 453 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`
- Example smokes:
  - `php lanes/libsqlite/examples/application-import-json-schema-savepoint.php`
  - `php lanes/libsqlite/examples/application-schema-json-savepoint-wal.php`
  - `php lanes/libsqlite/examples/application-multisite-json-wal-import-current-next54.php`
- `php -l` passed for all changed PHP files.
- `rg -n "wp_|wp-|/tmp/wp|plugin_|theme_mods|ui_theme|widget_|siteurl|active_plugins|option_id|option_name|option_value|autoload|blog_id"` over the owned source/test/example files returned no matches.
- `rg -n "WordPress|wordpress|wp_" lanes/libsqlite/src` returned no matches.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed. This cleanup reuses the existing JSON extract/validity, JSON schema savepoint, tenant JSON WAL import, WAL frame, conflict, rollback, and savepoint planners.

Root harness: not run - isolated micro-slice.
