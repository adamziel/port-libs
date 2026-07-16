# real-upstream-corpus-trigger-fkey-dynamic-20260530T170619Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T170619Z-0`
- Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - Scenario range: `trigger2-1.1` through `trigger2-1.3`

## Behavior

Added `SQLiteDynamicTriggerForeignKeyPlan::rowTriggerExecutionOrder()` and
focused corpus coverage for BEFORE/AFTER row-trigger timing. The dynamic cases
model the upstream `trigger2.test` behavior where:

- BEFORE UPDATE triggers see the pre-update table sums.
- AFTER UPDATE triggers see the current row already changed.
- WHEN-filtered update triggers use the old row image.
- BEFORE DELETE triggers see the row still present, while AFTER DELETE triggers
  see it removed.
- BEFORE INSERT triggers see the pre-insert table, while AFTER INSERT triggers
  see the inserted row.

The new coverage is source-neutral and uses generic row data only.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
  - `1 test files, 4676 assertions, 0 failures`

Focused delta inside the existing real trigger/FK corpus:

- New PASS cases: `45`
- New focused assertions: `1485`
- Previous focused assertion baseline for this file: `3191`
- New focused assertion total for this file: `4676`

## Non-Overlap

This extends the accepted trigger/FK dynamic corpus without repeating the
existing `fkey1.test` replacement cascade, `fkey2.test` recursive cascade,
RESTRICT repair-trigger, composite cascade mapping, or `trigger1.test` schema
lifecycle and AFTER trigger preservation cases. The new surface is specifically
`trigger2.test` row-trigger BEFORE/AFTER visibility and WHEN old-row filtering.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local dynamic
trigger/FK planner and adds a bounded row-trigger timing model for native PHP
trigger execution parity.

## Next

Continue trigger/FK corpus work with a non-overlapping upstream range, preferably
`trigger2.test` selective UPDATE OF / WHEN clauses or `trigger3.test` RAISE()
semantics if not already covered by a current-base handoff.
