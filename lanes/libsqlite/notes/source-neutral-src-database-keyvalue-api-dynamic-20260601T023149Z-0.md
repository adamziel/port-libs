Source-neutral key-value API cleanup, 2026-06-01.

Owned source:

- `src/SQLiteDatabase.php` key-value row scan helpers now use neutral
  `$setting` locals instead of the remaining `$option` source term.
- `tests/SQLiteNoDomainSpecificApiTest.php` now guards the owned
  `SQLiteDatabase.php`, `SQLiteKeyValueRow*`, and tenant key-value WAL helper
  source files against `wp_`, `blog_id`, `option_*`, `autoload`, old
  OptionRow-style identifiers, and `$option` locals.

Direct examples/tests:

- `SQLiteApplicationSettingsImportWalCurrentNext34Test.php` keeps the same WAL
  current/next behavior but uses `primary_url`, `enabled_modules`,
  `module_settings`, `tenant_public`, `setting_id`, `key_name`, `key_value`,
  and `load_policy` fixtures.
- `examples/application-settings-import-wal-current-next.php` replaces the
  stale `application-options-import-wal-current-next.php` example with the
  neutral key-value input/result keys used by `SQLiteKeyValueRowsWalImportPlan`.
- `examples/application-savepoint-key-value-import-diagnostics.php` replaces
  `wp_option`, `wp-options`, `autoload-index`, and `single-option` labels with
  `app_setting`, `app-settings`, `load-policy-index`, and `single-setting`.
- `examples/application-current-smoke-key-value-import.php` now stages
  `tenant_public` instead of a blog-shaped key.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteDatabase.php`
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php`
- `php -l lanes/libsqlite/examples/application-current-smoke-key-value-import.php`
- `php -l lanes/libsqlite/examples/application-settings-import-wal-current-next.php`
- `php -l lanes/libsqlite/examples/application-savepoint-key-value-import-diagnostics.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php`
  passed: `2 test files, 67 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-current-smoke-key-value-import.php --self-test`
  passed.
- `php lanes/libsqlite/examples/application-settings-import-wal-current-next.php`
  and `php lanes/libsqlite/examples/application-savepoint-key-value-import-diagnostics.php`
  both emitted JSON successfully.
- Additional broad probe
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php`
  was attempted and is not the acceptance gate for this cleanup: it reported
  `3 test files, 9196 assertions, 16 failures` in existing broad JSON/scalar/
  SELECT/update-delete families unrelated to the key-value source-name cleanup.

Dependency closure: no new support component is needed; this is a
source-neutral naming and fixture cleanup over the existing pure-PHP
SQLiteDatabase, WAL, and savepoint helpers.
