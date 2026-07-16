# Source-neutral trigger UPSERT savepoint cleanup

Slice: `source-neutral-src-domain-specific-option-classes-dynamic-20260601T114539Z-0`

## Scope

Neutralized one additional trigger/UPSERT source class that still exposed
historical option-shaped diagnostics:

- `SQLiteTriggerUpsertSavepointCurrentNextPlan` now reports `key_name`,
  `key_value`, and `old_key` in row/yield diagnostics instead of
  option-shaped result keys.
- `SQLiteTriggerUpsertSavepointCurrentNextTest.php` and
  `application-trigger-upsert-savepoint-current-next.php` now use
  `app_settings`, `setting_id`, `key_name`, `key_value`, and `load_policy`
  fixtures.
- `SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` now guards
  this production file plus its direct test/example fixture against legacy
  hardcoded domain strings.

No compatibility alias or hidden legacy table/key map was added.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertSavepointCurrentNextTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `3 test files, 155 assertions, 0 failures`.
- `git diff --name-only -- lanes/libsqlite | rg '\.php$' | xargs -r -n1 php -l` => no syntax errors in 4 changed PHP files.
- `php lanes/libsqlite/examples/application-trigger-upsert-savepoint-current-next.php --self-test` => `application-trigger-upsert-savepoint-current-next73 self-test passed`.
- `git diff --check -- lanes/libsqlite` => passed.

## Dependency Closure

No new support component is needed. This is a behavior-preserving
source-neutral API/fixture rename over the existing native PHP trigger UPSERT
savepoint planner.

## Counters

This source-neutral cleanup does not claim new upstream PASS rows, `phpPass`,
or mapped coverage movement.
