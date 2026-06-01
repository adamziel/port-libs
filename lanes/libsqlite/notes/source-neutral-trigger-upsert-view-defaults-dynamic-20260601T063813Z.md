# Source Neutral Trigger/Upsert Defaults Dynamic

- Slice: `source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T063813Z-0`
- Base accepted HEAD: `263ff1b299519d64e76087161433531b7a3e8cf2`
- Scope: neutralized the remaining bounded trigger/upsert default tokens in `SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan` and `SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan`.
- Behavior: default savepoint/transaction names now use `app_*` tokens, deferred violation rowid fallback uses generic `setting_id`, and directly coupled focused fixtures/examples use `app_settings`, `tenant_id`, `setting_id`, `key_name`, `key_value`, `load_policy`, and generic parent/child settings names.
- Guard: extended `SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php` to scan these two production files and dynamically exercise their generic defaults.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteTriggerDeferredUpsertReturningCurrentSourceNext135Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `4 test files, 212 assertions, 0 failures`
  - PHP lint passed for both changed source files, three changed test files, and two changed examples.
  - `php lanes/libsqlite/examples/application-trigger-upsert-do-nothing-returning-current-source-next.php --self-test` passed.
  - `php lanes/libsqlite/examples/application-trigger-deferred-upsert-returning-current-source-next135.php --self-test` passed.
  - `git diff --check -- lanes/libsqlite` passed.
- Dependency closure: no new support component is needed; this reuses existing native PHP trigger, UPSERT, RETURNING, deferred-FK, transaction, and savepoint helpers.
- Dashboard movement: source-neutral cleanup only; no `phpPass` or mapped-denominator counter update claimed.
