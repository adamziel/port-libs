# Source Neutral Trigger/Upsert/View Defaults Dynamic

- Slice: `source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T024131Z-0`
- Base accepted HEAD: `d66a5b3de6df2dc65a32a2f70e37d0a3eee8d74f`
- Scope: neutralized production defaults in `SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan`, `SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan`, and `SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan`.
- Behavior: default key columns now use `key_name`; default trigger/savepoint tokens now use `app_*`; small returning/violation diagnostics now emit `key_name` instead of option-shaped keys.
- Direct coverage: neutralized the three directly coupled focused tests and application examples to use `app_settings`, `setting_id`, `key_name`, `key_value`, `load_policy`, and `parent_key_name`.
- New guard: `SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php` scans the owned production files for legacy option/table/load-policy strings and exercises the generic defaults dynamically.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningViewCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteTriggerDeferredViewReturningCurrentSourceNext131Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `5 test files, 233 assertions, 0 failures`
  - PHP lint passed for changed source, test, and example PHP files.
  - Example self-tests passed for the three updated application trigger/view examples.
- Dependency closure: no new support component needed; this reuses existing native PHP row-array trigger, view, upsert, deferred-FK, and recursive-delete helpers.
- Dashboard movement: source-neutral cleanup only; no `phpPass` or mapped-denominator counter update claimed in this worker patch.
