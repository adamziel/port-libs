# real-upstream-corpus-trigger-fkey-dynamic-20260530T182337Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T182337Z-0`
- Base accepted HEAD: `f9e4e2d5498742752e9304fb10cad66aa60851fc`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - Scenario ranges: `trigger2-7.1` through `trigger2-7.4`, `trigger2-8.1` through `trigger2-8.6`, with regression citations for `trigger2-9.1` and `trigger2-10.1`

## Behavior

Added bounded dynamic trigger/FK corpus coverage for view-trigger behavior:

- `SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewTriggerLog()` models INSTEAD OF view triggers over joined view rows, preserving OLD values for UPDATE/DELETE and NEW values for UPDATE/INSERT.
- `SQLiteDynamicTriggerForeignKeyPlan::expressionViewTriggerRows()` models expression-view columns feeding OLD/NEW trigger rows for DELETE, INSERT, and UPDATE.
- The focused corpus also cites the upstream empty view-delete regression and omitted-column INSTEAD OF INSERT regression without adding fabricated script ids.

The new coverage is source-neutral and uses generic row data only.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicViewCorpusTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicViewCorpusTest.php`
  - `1 test files, 6312 assertions, 0 failures`

Focused delta:

- New PASS cases: `3644`
- New focused assertions: `6312`
- Expected `phpPass` movement: `287773 -> 291417`
- Mapped coverage: unchanged, `1189 / 1589`

## Non-Overlap

This extends the accepted trigger/FK dynamic corpus without repeating the earlier `fkey1.test` replacement cascade, `fkey2.test` recursive cascade/RESTRICT/composite mapping, `fkey6.test` deferred boundary cases, `trigger1.test` schema and statement-preservation cases, `trigger2.test` row-trigger order, `trigger2-3` selective UPDATE OF/WHEN, `trigger2-4` cascades, `trigger2-5` changes count, `trigger2-6` conflict propagation, or `triggerC.test` OR REPLACE delete-trigger coverage.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local dynamic trigger/FK planner and adds bounded view-trigger row-image behavior for native PHP trigger execution parity.

## Next

Continue trigger/FK corpus work with a non-overlapping upstream range such as `trigger3.test` RAISE() action semantics or `fkey3.test`/`fkey4.test` DDL-validation and mismatch behavior.
