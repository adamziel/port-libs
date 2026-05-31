## Application Tenant JSON WAL Import Source Neutralization

- Reworked `SQLiteTenantJsonWalImportPlan` away from legacy domain table vocabulary while preserving the same JSON import, conflict, rollback, release, WAL frame, and page-isolation behavior.
- Public result keys and diagnostics now use generic `tenant_id`, `group_id`, `setting_id`, `key_name`, `key_value`, `load_policy`, `tenant`, and `global` terms.
- Default table/path behavior now targets `app_settings`, `app_tenant_N_settings`, and `app_tenant_settings`; no new support component is needed.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationTenantJsonWalImportCurrentNext54Test.php` passed with `1 test files, 85 assertions, 0 failures`.
