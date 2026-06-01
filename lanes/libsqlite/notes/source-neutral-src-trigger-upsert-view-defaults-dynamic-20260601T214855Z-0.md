# Source-Neutral Trigger/Upsert/View Defaults Dynamic

Micro-slice: `source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T214855Z-0`

## Scope

- Neutralized the current-source-next trigger/view UPSERT tests and examples:
  - `tests/SQLiteTriggerUpsertReturningViewCurrentSourceNextTest.php`
  - `tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php`
  - `examples/application-trigger-upsert-returning-view-current-source-next.php`
  - `examples/application-trigger-returning-upsert-view-current-source-next.php`
- Replaced legacy option-table fixture values with generic application setting
  names: `base_url`, `landing_url`, `site_title`, `module_registry`,
  `routing_rules`, and `cache_rules`.
- Extended `SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php` so the
  source-neutral trigger/upsert/view guard now owns
  `SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan.php` plus the two
  directly coupled current-source-next tests and examples.

## Source-Truth Note

The owned production source already used neutral `key_name`, `key_value`,
`setting_id`, and `load_policy` defaults on this accepted base. This slice
keeps behavior unchanged and removes the remaining directly coupled
current-source-next fixture terms so the existing guard prevents reintroducing
legacy option-table defaults.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-trigger-upsert-returning-view-current-source-next.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-trigger-upsert-returning-view-current-source-next.php`
- `php -l lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-source-next.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 194 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-upsert-returning-view-current-source-next.php --self-test`
  - `application-trigger-upsert-returning-view-current-source-next144 self-test passed`
- `php lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-source-next.php --self-test`
  - `application-trigger-returning-upsert-view-current-source-next self-test passed`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This is a source-neutral fixture and guard
cleanup over existing native trigger/view UPSERT RETURNING current-source
helpers.

## Non-Overlap

This patch does not add upstream PASS rows, mapped denominator rows,
compatibility aliases, production wrappers, root harness edits, or dashboard
publication changes. It only extends the source-neutral trigger/upsert/view
guard to the remaining current-source-next fixture group and keeps the existing
SQLite behavior assertions intact.
