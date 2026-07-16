# Source-neutral JSON UPSERT migration option-class cleanup

Slice: `source-neutral-src-domain-specific-option-classes-dynamic-20260601T095646Z-0`

## Scope

- Neutralized `SQLiteJsonUpsertMigrationPlan` production defaults:
  - conflict key default is now `key_name`
  - JSON text column default is now `key_value`
  - load flag default is now `load_policy`
  - decoded RETURNING payload is now exposed as `decoded_key_value`
- Updated the directly coupled JSON UPSERT migration test and application
  example to use `app_settings`-style rows with `setting_id`, `key_name`,
  `key_value`, and `load_policy`.
- Extended `SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` so
  this source file is covered by the legacy domain string guard.

No compatibility alias, hidden legacy map, or domain-specific wrapper was
added. `lane-status.json` counters are unchanged because this cleanup claims no
new upstream PASS-line or mapped-denominator movement.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonUpsertMigrationPlan.php` => no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationJsonUpsertMigrationCurrentNext27Test.php` => no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` => no syntax errors.
- `php -l lanes/libsqlite/examples/application-json-upsert-migration-current-next27.php` => no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationJsonUpsertMigrationCurrentNext27Test.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `3 test files, 117 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-json-upsert-migration-current-next27.php --self-test` => passed.
- `git diff --check -- lanes/libsqlite` => passed.

## Dependency Closure

No new support component is needed. This is a source-neutral API/defaults
cleanup over existing native JSON mutation and UPSERT RETURNING behavior.

## Non-Overlap

This slice is bounded to the JSON UPSERT migration helper and its direct
test/example. It avoids accepted key-value API cleanup, trigger/FK recursive
cleanup, source-neutral option table defaults, and all throughput/upstream
corpus surfaces.
