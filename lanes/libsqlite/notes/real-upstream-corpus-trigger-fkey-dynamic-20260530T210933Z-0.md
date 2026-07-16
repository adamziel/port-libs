# real-upstream-corpus-trigger-fkey-dynamic-20260530T210933Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T210933Z-0`
- Base accepted HEAD: `140c9861a340b8e75fdc8ea93863883edb030323`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test`
  - Scenario ranges: `trigger7-1.1` through `trigger7-3.1`

## Behavior

Added bounded trigger7 dynamic corpus coverage for:

- qualified trigger-name diagnostics for temporary and unknown-schema triggers;
- `UPDATE OF` trigger program pruning as visible through EXPLAIN-style sentinel
  output;
- selective `DROP TRIGGER` behavior when many triggers share one table.

The implementation is source-neutral and uses generic trigger/table naming only.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger7BatchTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger7BatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger7BatchTest.php`
  - `1 test files, 2114 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamic*.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamic*.php lanes/libsqlite/tests/SQLiteTriggerForeignKeyDynamicCorpusTest.php`
  - `24 test files, 154311 assertions, 0 failures`

Focused delta:

- New focused assertions: `2114`
- New focused test file: `SQLiteRealUpstreamTriggerFkeyDynamicTrigger7BatchTest.php`

## Non-Overlap

This does not repeat the accepted `trigger2.test` row timing/selective trigger
program batch, `trigger3.test` RAISE action batch, `trigger4.test` view batch,
`trigger5.test` undo batch, `triggerG.test` recursive trigger batch, or the
existing FK action/deferred/restrict/nocase repair corpora. The new surface is
specifically `trigger7.test` trigger-name diagnostics, `UPDATE OF` compile-time
program pruning, and selective DROP trigger catalog behavior.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local dynamic
trigger/FK planner and adds bounded trigger catalog/explain-pruning behavior.

## Next

Continue trigger/FK corpus work with a non-overlapping upstream range, such as
`trigger8.test` or remaining `fkey*.test` behavior that is not already accepted.
