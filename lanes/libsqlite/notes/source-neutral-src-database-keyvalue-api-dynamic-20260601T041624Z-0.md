Source-neutral key-value dynamic API cleanup, 2026-06-01.

Owned source:

- `src/SQLiteTenantImportSavepointPlan.php` now exposes
  `continue_on_tenant_error` and uses tenant import locals instead of
  site-shaped option and variable names.
- `tests/SQLiteNoDomainSpecificApiTest.php` now guards the direct key-value
  source family plus coupled key-value tests/examples against the remaining
  option/blog/site/plugin-shaped fixture strings owned by this slice.

Direct examples/tests:

- `SQLiteApplicationCurrentSmokePlanTest.php` and
  `examples/application-current-smoke-key-value-import.php` keep the same
  current/staged key-value behavior while using `primary_url`,
  `dashboard_url`, `route_map`, `tenant_public`, `layout_template`,
  `visual_skin`, and `completed_migrations` fixtures.
- `SQLiteApplicationSettingsTenantWalCurrentNext42Test.php` keeps the tenant
  WAL current/next behavior but uses `display_name`, `enabled_modules`,
  `primary_url`, `dashboard_url`, `route_map`, and `registration` fixtures.
- `SQLiteTenantKeyValueImportSavepointCurrentNext37Test.php` and
  `examples/application-tenant-keyvalue-import-savepoint-current-next37.php`
  keep tenant savepoint release/rollback behavior while using tenant/import,
  endpoint/module, display/summary, and shared-catalog names.
- `examples/application-savepoint-key-value-import-diagnostics.php` keeps the
  savepoint diagnostic output but uses module/setting names.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteTenantImportSavepointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationCurrentSmokePlanTest.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationSettingsTenantWalCurrentNext42Test.php`
- `php -l lanes/libsqlite/tests/SQLiteTenantKeyValueImportSavepointCurrentNext37Test.php`
- `php -l lanes/libsqlite/examples/application-current-smoke-key-value-import.php`
- `php -l lanes/libsqlite/examples/application-savepoint-key-value-import-diagnostics.php`
- `php -l lanes/libsqlite/examples/application-tenant-keyvalue-import-savepoint-current-next37.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteApplicationCurrentSmokePlanTest.php lanes/libsqlite/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php lanes/libsqlite/tests/SQLiteApplicationSettingsTenantWalCurrentNext42Test.php lanes/libsqlite/tests/SQLiteTenantKeyValueImportSavepointCurrentNext37Test.php`
  passed: `5 test files, 242 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-current-smoke-key-value-import.php --self-test`
  passed.
- `php lanes/libsqlite/examples/application-settings-import-wal-current-next.php`
  emitted JSON successfully.
- `php lanes/libsqlite/examples/application-savepoint-key-value-import-diagnostics.php`
  emitted JSON successfully.
- `php lanes/libsqlite/examples/application-tenant-keyvalue-import-savepoint-current-next37.php`
  emitted JSON successfully.

Dependency closure: no new support component is needed; this is a
source-neutral naming and fixture cleanup over existing pure-PHP key-value,
WAL, VFS writer, and savepoint helpers.
