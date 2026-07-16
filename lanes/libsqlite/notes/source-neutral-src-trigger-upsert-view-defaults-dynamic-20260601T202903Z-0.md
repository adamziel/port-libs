# Source-neutral trigger/upsert/view defaults dynamic cleanup

Micro-slice: `source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T202903Z-0`

## Scope

- Neutralized the directly coupled trigger/view/defaults fixture surface in:
  - `tests/SQLiteViewTriggerDdlCorpusTest.php`
  - `tests/SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceTest.php`
- Replaced legacy table/column/default fixture names with generic application terms:
  - `app_settings`, `app_setting_audit`, `app_loadable_settings`
  - `setting_id`, `key_name`, `key_value`, `key_slug`, `key_value_len`, `load_policy`
- Extended `SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php` so the trigger/upsert/view source-neutral guard now scans the two neutralized fixture tests plus the generic DDL source helpers:
  - `src/SQLiteInsertDefaultValuesSql.php`
  - `src/SQLiteSchemaAlterGeneratedTriggerViewPlan.php`
  - `src/SQLiteViewTriggerDdlCorpus.php`

## Source-truth note

The owned trigger/upsert/view production source group had no remaining exact `wp_options`, `wp_`, `option_id`, `option_name`, `option_value`, `autoload`, `blog_id`, `target_option`, or `parent_option` hits on this accepted base. This patch keeps the production behavior unchanged and removes the directly coupled fixture defaults so future trigger/view corpus tests stay source-neutral.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteViewTriggerDdlCorpusTest.php lanes/libsqlite/tests/SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceTest.php`
  - `2 test files, 125 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `2 test files, 41 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteViewTriggerDdlCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteViewTriggerDdlCorpusTest.php`
- `php -l lanes/libsqlite/tests/SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceTest.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses existing schema-record, trigger/view DDL corpus, schema-alter generated-column, and source-neutral guard coverage.
