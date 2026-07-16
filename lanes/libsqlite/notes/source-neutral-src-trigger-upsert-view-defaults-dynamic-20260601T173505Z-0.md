# Source-Neutral Trigger/Upsert/View Defaults Dynamic

Slice: `source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T173505Z-0`
Base accepted HEAD: `a25bbddc9233ed27761d6d1c0152bb434c9c08f2`

## Scope

- Neutralized the directly coupled `SQLiteTriggerReturningUpsertViewCurrentNextPlan`
  current/next view UPSERT tests and examples from legacy setting-table fixture
  names to generic `app_settings`, `setting_id`, `key_name`, `key_value`, and
  `load_policy` terms.
- Extended `SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php` so the
  trigger/upsert/view defaults guard now owns
  `SQLiteTriggerReturningUpsertViewCurrentNextPlan.php` and rejects legacy
  domain terms in the two direct current-next tests and examples.
- No lane counters or mapped upstream denominator rows are changed; this is
  source-neutral cleanup over existing trigger/view UPSERT behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteTriggerReturningUpsertViewCurrentNext52Test.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewUniqueCurrentSourceNext140Test.php`
  - Result: `3 test files, 276 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 7 assertions, 0 failures`.
- `php -l` for changed PHP test/example files
  - Result: all changed PHP files reported no syntax errors.
- `php lanes/libsqlite/examples/application-trigger-returning-upsert-view-current-next52.php`
  - Result: emitted source-neutral `app_settings` trigger/view UPSERT JSON with `changes: 2`.
- `php lanes/libsqlite/examples/application-trigger-upsert-returning-view-unique-current-source-next140.php --self-test`
  - Result: self-test passed.
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output.

## Dependency Closure

No new support component is needed. This cleanup reuses the existing native
PHP view-trigger name resolution, UPSERT RETURNING, deferred foreign-key, and
current/next row stream helpers.

## Non-Overlap

This slice does not add upstream PASS rows, runner metadata, compatibility
aliases, dashboard/root edits, or new domain-shaped wrappers. It avoids
accepted recursive view/upsert source-neutral defaults and only cleans the
current-next view UPSERT fixtures tied to the already generic production
planner.

Root harness: not run - isolated micro-slice.
