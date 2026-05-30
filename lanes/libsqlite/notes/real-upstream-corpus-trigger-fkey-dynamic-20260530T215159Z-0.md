# real-upstream-corpus-trigger-fkey-dynamic-20260530T215159Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T215159Z-0`
- Base accepted HEAD: `4d354e3a7fdb39040e393b5132f7de86a7766ad9`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger8.test`
  - Scenario range: `trigger8-1.1`

## Behavior

Added `SQLiteDynamicTriggerForeignKeyPlan::largeTriggerBodyExecution()` and
`SQLiteRealUpstreamTriggerFkeyDynamicTrigger8LargeBodyTest.php`.

The batch ports real upstream `trigger8.test` large-trigger behavior into a
bounded native PHP model: an `AFTER INSERT` trigger body with many statements
must drain every statement exactly once, preserve statement order, and rerun the
full trigger body for each outer inserted row.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger8LargeBodyTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger8LargeBodyTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger8LargeBodyTest.php`
  - `1 test files, 14006 assertions, 0 failures`

Focused delta:

- New distinct TestRunner PASS cases: `1003`
- New focused assertions: `14006`

## Non-Overlap

This does not repeat accepted `trigger2.test` timing/program/conflict/view
coverage, `trigger3.test` RAISE coverage, `trigger5.test` undo coverage,
`trigger7.test` catalog/pruning coverage, `triggerG.test` recursive SELECT
coverage, `triggerC.test` OR-REPLACE delete-trigger or rowid-mutation coverage,
or existing FK action/deferred/restrict/nocase repair corpora. The new surface
is specifically `trigger8.test` large trigger-body statement drain and
per-outer-row trigger program replay.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local dynamic
trigger/FK planner and adds a bounded large-trigger body execution model.

## Next

Continue trigger/FK corpus work with a non-overlapping upstream range such as
`trigger9.test` OLD/NEW record materialization or remaining `fkey*.test`
sections that are not already represented in focused PHP corpus coverage.
