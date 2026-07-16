# source-neutral-src-domain-specific-option-classes-dynamic-20260601T213531Z-1

## Scope

- Renamed production `SQLiteTenantKeyValueWalPlan` to `SQLiteScopedKeyValueWalPlan` with no compatibility shim.
- Renamed the stale option/multisite example to `application-scoped-settings-wal-current-next42.php` and replaced the old `wp_*`/`blog_id`/`option_*` input fixture with neutral scoped `setting_id`, `key_name`, `key_value`, and `load_policy` rows.
- Extended `SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` to guard the renamed source file, direct scoped settings example, and direct test against the old class/dependency/example names.

## Verification

- `php -l lanes/libsqlite/src/SQLiteScopedKeyValueWalPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationSettingsTenantWalCurrentNext42Test.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-scoped-settings-wal-current-next42.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationSettingsTenantWalCurrentNext42Test.php` -> `1 test files, 79 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` -> `1 test files, 52 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 8 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-scoped-settings-wal-current-next42.php --self-test` -> self-test passed.
- `git diff --check -- lanes/libsqlite` -> clean.

## Dependency Closure

No new support component is needed. The renamed scoped key-value WAL plan reuses the existing WAL append, reader snapshot, and VFS writer behavior while preserving the same scoped/global setting-row semantics under neutral production class and example names.
