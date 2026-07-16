# Source-neutral trigger/upsert view defaults dynamic

## Scope

- Neutralized `SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan` production defaults by replacing the hardcoded deferred UPSERT savepoint with `app_import_deferred_upsert`.
- Replaced the deferred violation row-id fallback from the legacy setting-table column to `setting_id`, preserving the existing `rowid` and ordinal fallbacks.
- Expanded `SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest` so the trigger/upsert/view defaults guard now owns this source file.
- Migrated the direct trigger-upsert deferred test and Application smoke rows to generic `setting_id`, `key_name`, `key_value`, `load_policy`, and `parent_setting_id` terms.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerUpsertDeferredReturningCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php`
- `php -l lanes/libsqlite/examples/application-trigger-upsert-deferred-returning-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteTriggerUpsertDeferredReturningCurrentSourceNextTest.php`
  - Result: `2 test files, 115 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredUpsertReturningCurrentSourceNext135Test.php lanes/libsqlite/tests/SQLiteTriggerUpsertDeferredReturningCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php`
  - Result: `3 test files, 213 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredUpsertReturningCurrentSourceNext135Test.php lanes/libsqlite/tests/SQLiteTriggerUpsertDeferredReturningCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `4 test files, 218 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 5 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-upsert-deferred-returning-current-source-next.php --self-test`
  - Result: `application-trigger-upsert-deferred-returning-current-source-next137 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - Result: no whitespace errors.

## Dependency Closure

No new support component is needed. This source-neutral cleanup reuses the existing trigger UPSERT, deferred foreign-key, savepoint, and RETURNING row-array implementation.
