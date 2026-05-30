# Source-neutral trigger/upsert/view defaults

Session: `port-dev-sqlite-neutral-trigger`
Micro-slice: `source-neutral-src-trigger-upsert-view-defaults`

## Change

- Neutralized owned defaults in `SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan`.
- Replaced the default key column and savepoint from legacy application-specific names to `key_name` and `app_view_trigger_upsert_next149`.
- Replaced trigger-effect payload keys with generic `key_name`, `old_key_value`, and `new_key_value`.
- Updated the direct focused test and application example to use generic `app_settings`-style row fields: `setting_id`, `key_name`, `key_value`, and `load_policy`.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `php lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-source-next.php --self-test`

## Dependency Closure

No new support component is needed. This is a source-neutral naming/default cleanup in an existing trigger/upsert/view helper and its direct focused coverage.
