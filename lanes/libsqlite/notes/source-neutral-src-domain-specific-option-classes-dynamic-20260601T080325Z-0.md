# Source-neutral recursive trigger/upsert option-class cleanup

Slice: `source-neutral-src-domain-specific-option-classes-dynamic-20260601T080325Z-0`

## Scope

- Neutralized the next recursive trigger/upsert/view source group that still
  exposed historical option-row defaults:
  - `SQLiteRecursiveUpsertConflictYieldPlan`
  - `SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan`
  - `SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan`
  - `SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan`
  - `SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan`
  - `SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan`
  - `SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan`
  - `SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan`
- Replaced production-source defaults with generic `setting_id`, `key_name`,
  `key_value`, and `app_*` savepoint/view tokens.
- Updated the directly coupled trigger/upsert tests and examples to use
  `app_settings` rows and generic module/settings naming.
- Extended `SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` so
  this source group is covered by the source-neutral guard.

No compatibility alias, hidden legacy map, or WordPress-specific wrapper was
added.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveUpsertConflictYieldCurrentNext30Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveUpsertReturningCurrentSourceNext126Test.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteTriggerUpsertRecursiveViewCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNext121Test.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointTest.php lanes/libsqlite/tests/SQLiteTriggerSavepointReturningRecursiveCurrentSourceNext122Test.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningCommitBarrierTest.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveCurrentNext51Test.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` => `11 test files, 1155 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php` => `2 test files, 48 assertions, 0 failures`.
- `git diff --name-only -- lanes/libsqlite | rg '\.php$' | xargs -r -n1 php -l` => no syntax errors in changed PHP files.
- `git diff --check -- lanes/libsqlite` => passed.
- `for f in $(git diff --name-only -- lanes/libsqlite/examples | sort); do php "$f" --self-test >/dev/null || { echo "FAIL $f"; exit 1; }; echo "PASS $f"; done` => passed for all changed examples:
  - `application-recursive-upsert-conflict-yield-current-next30.php`
  - `application-trigger-deferred-returning-commit-barrier.php`
  - `application-trigger-deferred-returning-savepoint.php`
  - `application-trigger-recursive-deferred-returning-current-source-next121.php`
  - `application-trigger-recursive-returning-fk-current-source-next133.php`
  - `application-trigger-recursive-upsert-returning-current-source-next126.php`
  - `application-trigger-recursive-view-upsert-current-source-next232.php`
  - `application-trigger-recursive-view-upsert-current-source-next235.php`
  - `application-trigger-recursive-view-upsert-current-source-next238.php`
  - `application-trigger-returning-deferred-view-current-source-next128.php`
  - `application-trigger-returning-recursive-upsert-current-source-next118.php`
  - `application-trigger-savepoint-returning-recursive-current-source-next122.php`
  - `application-trigger-upsert-returning-recursive-current-source-next.php`
  - `application-trigger-upsert-recursive-view-current-source-next.php`
  - `application-trigger-upsert-returning-recursive-current-next51.php`

## Dependency Closure

No new support component is needed. This is a source-neutral API/defaults
cleanup over existing native recursive trigger, UPSERT, RETURNING, savepoint,
and view-source behavior.

## Non-Overlap

This slice does not add upstream PASS rows, dashboard counters, or mapped
denominator rows. It is bounded to production-source domain cleanup for the
recursive trigger/upsert option-class helper group and preserves the focused
behavior tests.

`lane-status.json` was intentionally not changed because this cleanup claims no
new pass-line or mapped-coverage movement.
