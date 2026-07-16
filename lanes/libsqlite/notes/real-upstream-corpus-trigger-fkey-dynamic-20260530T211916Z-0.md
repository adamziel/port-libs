# real-upstream-corpus-trigger-fkey-dynamic-20260530T211916Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T211916Z-0`
- Base accepted HEAD: `79fe7adeaeaffcf972bbb3cc5bff694c367cc63d`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
  - Scenario range: `triggerC-7.1` through `triggerC-7.9`

## Behavior

Added `SQLiteDynamicTriggerForeignKeyPlan::beforeTriggerRowidMutation()` and a
focused dynamic corpus for upstream `triggerC.test` rowid mutation behavior.
The batch models the upstream cases where a BEFORE trigger deletes or moves a
rowid before the outer UPDATE or DELETE continues, and AFTER triggers only fire
for surviving outer-row changes.

The coverage is source-neutral and uses generic `rowid`, `a`, and `b` rows.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRowidMutationTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRowidMutationTest.php`
  - `1 test files, 11528 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-Overlap

This does not repeat accepted `trigger2.test` timing/program/conflict/view
coverage, `trigger3.test` RAISE coverage, `trigger5.test` undo coverage,
`triggerG.test` recursive SELECT coverage, or existing `triggerC-5.*`
OR-REPLACE delete-trigger firing coverage. The new surface is specifically
`triggerC-7.*` BEFORE trigger rowid mutation affecting the outer DML target.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local dynamic
trigger/FK planner and adds a bounded rowid mutation model for native PHP
trigger execution parity.

## Next

Continue trigger/FK corpus work with a non-overlapping upstream range such as
`triggerC-9.*` recursive delete chains or a remaining `fkey*.test` section that
is not already represented in focused PHP corpus coverage.
