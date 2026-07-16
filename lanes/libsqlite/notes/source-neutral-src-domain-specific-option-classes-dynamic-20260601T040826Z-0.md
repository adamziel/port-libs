# Source-neutral trigger/DML option-class cleanup

Slice: `source-neutral-src-domain-specific-option-classes-dynamic-20260601T040826Z-0`

## Scope

Neutralized a bounded trigger/DML source group that still enforced historical
option-table defaults in production source:

- `SQLiteDmlTriggerCurrentNextPlan` now defaults row identifiers to
  `setting_id` and accepts `app_settings` trigger targets.
- `SQLiteUpdateDeleteTriggerOrderPlan` now defaults row identifiers to
  `setting_id` and accepts `app_settings` trigger targets.
- `SQLiteUpsertReturningTriggerPlan` now accepts `app_settings` trigger
  targets.

The directly coupled tests and examples now use `app_settings`, `setting_id`,
`key_name`, `key_value`, and `load_policy` rows. No legacy compatibility alias
or hidden table map was added.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteDmlTriggerCurrentNextTest.php lanes/libsqlite/tests/SQLiteUpsertReturningTriggerCurrentNext18Test.php lanes/libsqlite/tests/SQLiteTriggerOrderUpdateDeleteCorpusTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `5 test files, 213 assertions, 0 failures`.
- `git ls-files --others --modified --exclude-standard -- lanes/libsqlite | rg '\.php$' | xargs -r -n1 php -l` => no syntax errors in 10 changed/new PHP files.
- `php lanes/libsqlite/examples/application-dml-trigger-current-next.php` => printed generic `app_settings` audit output.
- `php lanes/libsqlite/examples/application-upsert-returning-trigger-current.php --self-test` => passed and printed generic UPSERT trigger output.
- `php lanes/libsqlite/examples/application-trigger-order-update-delete.php` => printed generic update/delete trigger output.
- `git diff --check -- lanes/libsqlite` => passed.

## Dependency Closure

No new support component is needed. This cleanup preserves the existing native
PHP DML trigger, trigger-order, and UPSERT RETURNING behavior while removing a
production-source domain-specific table and row-id default.
