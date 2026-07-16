# real-upstream-corpus-trigger-fkey-dynamic-20260531T014342Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey3.test`
- Ported sections: `fkey3-3.1.1..3.1.3`, `fkey3-3.2.1..3.3.2`, `fkey3-3.4.1..3.4.8`, `fkey3-3.5.1..3.5.5`, and `fkey3-3.6.1..3.6.5`.

## Behavior

- Added `SQLiteDynamicTriggerForeignKeyPlan::selfReferencingCompositeForeignKeyPlan()` for self-referencing composite foreign-key statements.
- Covers inserted rows satisfying their own parent key, composite child-key NULL short-circuit behavior, failed statement rollback, declared parent-column lookup order when the available unique index is in a different column order, valid leaf delete, and invalid update violations.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicSelfReferenceTest.php` with 6,450 focused assertions/PASS lines from real upstream `fkey3.test` behavior.

## Non-overlap

- This does not repeat accepted `fkey2` deferred graph/cascade/restrict/count-changes, `fkey6` defer-foreign-keys repair, `fkey8` replace-counter, trigger9 OLD-row, triggerG recursive SELECT, triggerB wide-column mask, or trigger/FK nocase repair batches.
- This owns the `fkey3.test` self-referencing composite insert/update/delete edge cases.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSelfReferenceTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSelfReferenceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSelfReferenceTest.php`
  - `1 test files, 6450 assertions, 0 failures`

## Dependency closure

- No new support component is needed. The slice reuses the existing generic trigger/FK dynamic planner surface and upstream hydrated corpus.
