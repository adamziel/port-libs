# source-neutral-src-option-table-defaults-dynamic-20260601T095011Z-0

Status: ready for integration.

Source-neutral cleanup:

- Replaced `SQLiteJsonUpsertMigrationPlan` hardcoded option-style JSON UPSERT defaults with generic application settings defaults:
  `key_name`, `key_value`, `load_policy`, and `decoded_key_value`.
- Updated the directly coupled JSON UPSERT migration test and example to use generic `app_settings` fixture names and application module rows while preserving the same UPSERT conflict, JSON mutation, skipped-row, and RETURNING-style decoded-output assertions.
- Extended `SQLiteNoDomainSpecificApiTest` to guard this source file, test, and example against the same source-neutral key-value vocabulary scan used by the existing application settings API guard.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationJsonUpsertMigrationCurrentNext27Test.php`
  - `1 test files, 64 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteJsonUpsertMigrationPlan.php && php -l lanes/libsqlite/tests/SQLiteApplicationJsonUpsertMigrationCurrentNext27Test.php && php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php && php -l lanes/libsqlite/examples/application-json-upsert-migration-current-next27.php`
  - no syntax errors detected in all changed PHP files
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationJsonUpsertMigrationCurrentNext27Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `2 test files, 69 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-upsert-migration-current-next27.php --self-test`
  - passed
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component is needed. This reuses the existing JSON mutation, JSON extraction, JSON subtype, and UPSERT DO UPDATE helper surfaces.

Root harness: not run - isolated micro-slice.
