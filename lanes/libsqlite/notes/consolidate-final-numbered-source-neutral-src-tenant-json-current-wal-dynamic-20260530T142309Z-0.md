## Source Neutral Tenant JSON Current WAL

- Neutralized `SQLiteTenantJsonWalCurrentNextPlan` source defaults and observable internals from tenant-specific legacy naming to tenant/global setting names.
- Renamed the focused test and smoke example to generic application names and updated direct assertions for `app_settings`, `app_tenant_settings`, `tenant_id`, `key_name`, `key_value`, and `load_policy`.
- Dependency closure: no new support component required; this reuses existing JSON validity, JSON extraction, JSONB, and WAL current/next frame accounting helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTenantJsonWalCurrentNextPlan.php && php -l lanes/libsqlite/tests/SQLiteTenantJsonWalCurrentNextTest.php && php -l lanes/libsqlite/examples/application-tenant-json-wal-current-next.php` - pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTenantJsonWalCurrentNextTest.php` - 1 test file, 142 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` - 1 test file, 3 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-tenant-json-wal-current-next.php` - emitted the generic tenant/global WAL scenario with 2 frames.
- `git diff --check -- lanes/libsqlite` - pass.
