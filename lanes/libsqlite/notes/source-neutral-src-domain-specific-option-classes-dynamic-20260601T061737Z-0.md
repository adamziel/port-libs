# Source-neutral trigger/FK option-class cleanup

Slice: `source-neutral-src-domain-specific-option-classes-dynamic-20260601T061737Z-0`

## Scope

Neutralized a bounded trigger/foreign-key source group that still exposed
historical option-row defaults in production source:

- `SQLiteTriggerReturningFkSavepointCurrentNextPlan` now defaults yielded row
  identifiers to `setting_id`, uses `key_name` as the default label field, and
  accepts explicit `rowid_column` / `label_column` options.
- `SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan` now defaults
  DELETE RETURNING row identifiers to `setting_id`.
- `SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan` now defaults row
  identifiers to `setting_id` and uses `app_recursive_delete` as the generic
  recursive savepoint name.
- `SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan` now defaults
  row identifiers to `setting_id`, records trigger effects from the configured
  row-id column, and no longer special-cases `old.option_id` / `new.option_id`.

Directly coupled tests and examples now use `app_settings`, `setting_id`,
`key_name`, `key_value`, and `load_policy` rows. No compatibility alias,
legacy map, or WordPress-specific wrapper was added.

## Verification

- `rg -n "wp_|wp_options|wp_sitemeta|option_id|option_name|option_value|autoload|blog_id|blogId|BlogId|siteurl|blogname|active_plugins|plugin|Plugin|\bhome\b" ...owned source/test/example files...` => no matches.
- `git diff --name-only -- lanes/libsqlite '*.php' | xargs -r -n1 php -l` => no syntax errors in all 13 changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningFkSavepointCurrentNextTest.php lanes/libsqlite/tests/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNext120Test.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveFkCurrentSourceNext124Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNext111Test.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `6 test files, 332 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCurrentTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicYieldCurrentTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeySavepointDeferredCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php` => `4 test files, 11310 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-trigger-returning-fk-savepoint-current-next.php --self-test` => passed.
- `php lanes/libsqlite/examples/application-trigger-returning-fk-delete-savepoint-current-source-next120.php --self-test` => passed.
- `php lanes/libsqlite/examples/application-trigger-returning-recursive-fk-current-source-next124.php` => self-test passed.
- `php lanes/libsqlite/examples/application-trigger-recursive-returning-deferred-fk-current-source-next111.php` => printed generic rollback JSON with `base_url` / `setting_id` rows.
- `git diff --check -- lanes/libsqlite` => passed.

## Dependency Closure

No new support component is needed. This cleanup preserves the existing native
PHP trigger, RETURNING, FK, savepoint, and recursive trigger behavior while
removing production-source domain-specific defaults from the owned helper group.

`lane-status.json` was intentionally not changed: this source-neutral slice
does not claim new PASS-line growth or mapped-denominator movement.
