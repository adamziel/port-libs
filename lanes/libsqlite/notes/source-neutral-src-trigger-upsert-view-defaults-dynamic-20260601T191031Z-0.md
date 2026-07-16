# Source-Neutral Trigger/Upsert/View Defaults Dynamic

Slice: `source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T191031Z-0`
Base accepted HEAD: `d9a4a1ae066505e0e30c01eaa033b1421011fc65`

## Scope

- Neutralized the directly coupled trigger-view RETURNING savepoint and view
  UPSERT RETURNING savepoint tests/examples from legacy setting-table fixture
  names to generic `app_settings`, `app_setting_audit`, `setting_id`,
  `key_name`, `key_value`, and `load_policy` terms.
- Extended `SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php` so the
  trigger/upsert/view defaults guard owns
  `SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan.php`,
  `SQLiteViewUpsertReturningSavepointPlan.php`, and their direct current-next
  tests/examples.
- No lane counters, lane status JSON, mapped upstream denominator rows,
  compatibility aliases, or production wrappers are changed.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNext123Test.php lanes/libsqlite/tests/SQLiteViewUpsertReturningSavepointCurrentNext49Test.php`
  - Result: `3 test files, 292 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 8 assertions, 0 failures`.
- `php -l` for all changed PHP test/example files
  - Result: all changed PHP files reported no syntax errors.
- `php lanes/libsqlite/examples/application-trigger-view-returning-savepoint-recursive-current-source-next123.php --self-test`
  - Result: self-test passed.
- `php lanes/libsqlite/examples/application-view-upsert-returning-savepoint-current-next49.php`
  - Result: emitted source-neutral `app_settings` JSON with `committedChanges: 2`.
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output.

## Dependency Closure

No new support component is needed. This cleanup reuses the existing native PHP
view-trigger name resolution, UPSERT RETURNING, trigger savepoint, current/next
source, and deferred foreign-key behavior.

## Non-Overlap

This slice does not add upstream PASS rows or touch root/dashboard files. It
continues the prior trigger/upsert/view source-neutral cleanup by covering the
next direct trigger-view savepoint fixture group without changing SQLite
behavior.

Root harness: not run - isolated micro-slice.
